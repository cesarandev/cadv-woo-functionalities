# Informe técnico de seguridad

**Proyecto:** CADV Woo Functionalities 1.1.55

**Rama revisada:** `main`

**Fecha:** 24 de julio de 2026

**WordPress local:** 7.0.1

**WooCommerce local:** 10.8.1

**Método:** inventario automatizado, consulta de vulnerabilidades, revisión estática manual, búsqueda de secretos en Git y lint PHP

## 1. Resumen

| Severidad | Hallazgos |
|---|---:|
| Crítica | 1 |
| Alta | 1 condicional |
| Media | 5 |
| Baja | 2 |
| Informativa | 3 |

El hallazgo crítico pertenece al entorno WordPress sobre el que corre el plugin. No se confirmó una vulnerabilidad crítica o alta explotable dentro del código propio del plugin. El código presenta una postura razonable de autorización, saneamiento y manejo de archivos, pero necesita controles adicionales contra abuso y agotamiento de recursos.

La puntuación interna usa:

`riesgo = CVSS × 0.3 + explotabilidad × 0.3 + criticidad del activo × 0.2 + exposición × 0.2`

Cuando no existe CVSS, se usó una estimación conservadora de impacto técnico equivalente y se señala como evaluación interna, no como puntuación CVE.

## 2. Alcance y herramientas

### Incluido

- PHP, JavaScript y configuración versionada del plugin.
- Endpoints públicos AJAX y `admin-post`.
- Formularios, autenticación, CRM, CSV y visor PDF.
- Cliente y servidor privado de actualizaciones.
- Estado local de WordPress y WooCommerce.
- Historial Git para rutas sensibles y patrones de secretos.

### No incluido

- Pruebas dinámicas: el sitio `agrobrokers.local` y MySQL estaban detenidos.
- Estado de opciones en la base de datos, incluyendo claves reCAPTCHA.
- Infraestructura del servidor de producción, WAF, CDN, proxy, TLS y permisos del sistema.
- Pruebas destructivas, fuzzing, carga o explotación.

### Resultados automatizados

- `inventory.json`: 0 manifiestos de dependencias, 0 contenedores, 0 IaC y riesgo bajo de secretos.
- `scan_results.json`: 0 CVE de paquetes, porque el plugin no declara dependencias Composer/npm.
- Búsqueda de secretos en archivos e historial Git: sin coincidencias de claves privadas o tokens comunes.
- `update-server/config.php`: ignorado por Git, ausente y sin historial versionado.
- Lint con PHP 8.2.29: todos los archivos PHP pasan.
- Herramientas no disponibles: Semgrep, PHPCS, Gitleaks, Trivy y WP-CLI.

## 3. Hallazgos

### SEC-001 — WordPress 7.0.1 expuesto a cadena SQLi/RCE explotada activamente

**Severidad:** Crítica

**Puntuación:** 9.9/10

**CVE:** CVE-2026-63030 encadenada con CVE-2026-60137

**CISA KEV:** Sí

**Componente:** `wp-includes/version.php` del sitio local

**Estado observado:** `$wp_version = '7.0.1'`

#### Evidencia

WordPress 7.0.x anterior a 7.0.2 es vulnerable a una confusión de rutas del endpoint batch de REST. Combinada con la inyección SQL de `author__not_in`, permite inyección SQL y ejecución remota sin autenticación. WPScan asigna CVSS 9.8; CISA reporta explotación activa, automatizable e impacto técnico total.

Fuentes:

- [WordPress 7.0.2 Security Release](https://wordpress.org/news/2026/07/wordpress-7-0-2-release/)
- [NVD CVE-2026-63030](https://nvd.nist.gov/vuln/detail/CVE-2026-63030)
- [NVD CVE-2026-60137](https://nvd.nist.gov/vuln/detail/CVE-2026-60137)

#### Impacto

Compromiso total del sitio y su información: credenciales, clientes, pedidos, CRM, fichas técnicas, configuración del actualizador y capacidad de introducir código persistente.

#### Remediación

1. Actualizar WordPress inmediatamente a 7.0.2 o posterior.
2. Si la instalación estuvo accesible desde Internet, asumir posible compromiso hasta revisar:
   - integridad del core con hashes oficiales;
   - usuarios administradores y sesiones;
   - plugins/mu-plugins/temas y archivos PHP recientes;
   - tareas programadas, opciones `active_plugins`, `siteurl` y `home`;
   - logs de servidor/WAF para solicitudes anómalas a REST batch.
3. Rotar secretos si aparecen indicadores de compromiso.

#### Validación

- Confirmar versión ≥ 7.0.2.
- Verificar integridad del core.
- Ejecutar regresión funcional del plugin después de actualizar.

---

### SEC-002 — CAPTCHA matemático automatizable si reCAPTCHA no está configurado

**Severidad:** Alta condicional

**Puntuación interna:** 7.6/10

**CWE:** CWE-799, control inadecuado de frecuencia/interacción

**Archivo:** `includes/class-cadv-woo-functionalities.php:3246-3358`

#### Evidencia

`render_public_captcha_fields()` usa reCAPTCHA únicamente cuando existen ambas claves. En caso contrario muestra una suma con operandos visibles y guarda esos mismos operandos en un token firmado. La firma evita alterar el reto, pero no evita que un bot lea y resuelva la suma. `validate_public_submission()` acepta este reto como control alternativo.

Los formularios mantienen nonce, honeypot y rate limit, por lo que no es un bypass directo de autorización. Sin embargo, un bot puede cargar el formulario, obtener un nonce público, resolver la suma y automatizar solicitudes, creación de cuentas/órdenes y recuperación de credenciales a través de IP distribuidas.

No se encontraron constantes reCAPTCHA en `wp-config.php`. Las claves también pueden estar guardadas en opciones de WordPress, pero la base de datos estaba detenida y ese estado no se pudo verificar.

#### Remediación

1. En producción, exigir reCAPTCHA/Turnstile u otro control de riesgo fuerte y fallar de forma segura si falta configuración.
2. Mostrar un aviso administrativo y un health check cuando el control no esté operativo.
3. Permitir el reto matemático solamente en desarrollo local, mediante una constante explícita.
4. Combinar protección por IP con email/cuenta/dispositivo y señales de velocidad.

#### Validación TDD

- Pre-fix: `CurrentRiskDetectionTests.test_public_forms_have_machine_solvable_captcha_fallback`.
- Post-fix: `RemediatedTargetStateTests.test_public_forms_fail_closed_without_strong_bot_protection`.

---

### SEC-003 — Renderizado PDF sin presupuesto de recursos

**Severidad:** Media

**Puntuación interna:** 6.5/10

**CWE:** CWE-400, consumo no controlado de recursos

**Archivo:** `includes/class-cadv-woo-functionalities.php:2156-2320`

#### Evidencia

El visor aplica controles de acceso sólidos:

- sesión obligatoria;
- nonce específico para descarga/pedido/producto;
- coincidencia con las descargas WooCommerce del usuario actual;
- resolución de ruta local mediante `realpath`;
- validación de firma `%PDF-`;
- cabeceras `no-store`, `nosniff`, `DENY` y CSP.

Después de autorizar, cada página ejecuta Imagick a 144 DPI y llama `readImage()` antes de reducir a 1800 px. `pingImage()` calcula el número de páginas. No hay límite de bytes del PDF, número de páginas, dimensiones, tiempo, memoria o cuota de solicitudes; tampoco hay caché del JPEG generado.

Un cliente autenticado con acceso legítimo a una ficha grande o malformada puede repetir solicitudes y agotar CPU/memoria de PHP/Imagick.

#### Remediación

1. Rechazar PDFs que excedan límites documentados de bytes y páginas.
2. Configurar `Imagick::setResourceLimit()` para memoria, mapa, disco, área y tiempo, además de límites de ImageMagick en `policy.xml`.
3. Añadir rate limit por usuario e IP.
4. Renderizar una vez en un proceso aislado y cachear páginas con expiración.
5. Validar el PDF al asignarlo como descarga, no durante la petición pública.

#### Validación TDD

- Pre-fix: `CurrentRiskDetectionTests.test_pdf_rasterizer_has_no_resource_budget`.
- Post-fix: `RemediatedTargetStateTests.test_pdf_rasterizer_enforces_a_resource_budget`.
- Prueba dinámica: PDF con muchas páginas y gran `MediaBox`; verificar 413/422 sin incremento sostenido de memoria.

---

### SEC-004 — AJAX público de posts permite consultas costosas sin rate limit

**Severidad:** Media

**Puntuación interna:** 6.2/10

**CWE:** CWE-400

**Archivo:** `includes/class-cadv-post-grid.php:48-49,131-219`

#### Evidencia

`handle_ajax()` está disponible para usuarios autenticados y no autenticados. Comprueba nonce y limita `per_page` a 24, pero:

- no consume un rate limit;
- no limita el máximo de `page`;
- permite `orderby=rand`;
- acepta `categories` desde el propio cliente y solo valida la categoría elegida contra esa lista controlada por el cliente.

No se observó fuga de contenido privado: la consulta trabaja con publicaciones públicas. El riesgo es de disponibilidad, enumeración innecesaria y consultas SQL costosas.

#### Remediación

1. Reutilizar el patrón de rate limit ya existente en el AJAX del marketplace.
2. Limitar `page` a un valor razonable y rechazar offsets excesivos.
3. Quitar `rand` del endpoint público o reemplazarlo por una semilla/cache.
4. Firmar la configuración del shortcode o resolver en servidor las categorías autorizadas.
5. Cachear respuestas y evaluar `no_found_rows` cuando no se necesite total exacto.

#### Validación TDD

- Pre-fix: `CurrentRiskDetectionTests.test_post_grid_ajax_has_no_rate_limit_and_accepts_rand`.
- Post-fix: `RemediatedTargetStateTests.test_post_grid_ajax_has_rate_limit_page_cap_and_no_rand`.
- Prueba dinámica: ráfaga de peticiones y páginas extremas deben producir 429/400.

---

### SEC-005 — Datos CRM sin integración de exportación, borrado y retención

**Severidad:** Media

**Puntuación interna:** 5.8/10

**CWE:** CWE-359, exposición/gestión de información personal

**Componentes:** usuarios, pedidos WooCommerce, leads privados y snapshots de CRM

#### Evidencia

El plugin almacena nombre, email, teléfono, empresa, ubicación, necesidad, actividad comercial y métricas de visualización. Existe una solicitud manual de eliminación de cuenta, pero no se encontraron hooks:

- `wp_privacy_personal_data_exporters`;
- `wp_privacy_personal_data_erasers`;
- `wp_add_privacy_policy_content`.

Tampoco se encontró una política automática de retención para leads, snapshots o métricas. Esto no demuestra exposición pública, pero aumenta impacto, carga operativa y riesgo de incumplir solicitudes de titulares.

#### Remediación

1. Registrar exportadores y borradores de privacidad de WordPress.
2. Declarar qué datos deben anonimizarse versus conservarse por obligación contable.
3. Añadir una política configurable de retención para leads abandonados, snapshots y estadísticas.
4. Incluir texto sugerido para la política de privacidad.
5. Registrar auditoría mínima de exportación/borrado sin duplicar PII.

#### Validación TDD

- Pre-fix: `CurrentRiskDetectionTests.test_plugin_has_no_wordpress_privacy_exporter_or_eraser`.
- Post-fix: `RemediatedTargetStateTests.test_plugin_registers_privacy_export_and_erasure_hooks`.

---

### SEC-006 — Rate limits no atómicos y sensibles a la topología del proxy

**Severidad:** Media

**Puntuación interna:** 5.6/10

**CWE:** CWE-362 y CWE-799

**Archivo:** `includes/class-cadv-woo-functionalities.php:3444-3480`

#### Evidencia

El contador realiza `get_transient()` seguido de `set_transient()`. Peticiones concurrentes pueden leer el mismo valor y perder incrementos. Además, la IP por defecto es `REMOTE_ADDR`; existe un filtro para proxies confiables, pero no se encontró documentación operativa que obligue a configurarlo.

En un proxy mal configurado, todos los usuarios podrían compartir la IP del balanceador y un atacante podría agotar la cuota global. Con headers reenviados sin una lista explícita de proxies confiables, ocurriría el problema contrario: falsificación de IP y evasión.

#### Remediación

1. Usar contador atómico en Redis/object cache o tabla con incremento transaccional.
2. Documentar proxies/CDN confiables y nunca confiar ciegamente en `X-Forwarded-For`.
3. Aplicar cuotas combinadas por IP, email normalizado, usuario y acción.
4. Añadir `Retry-After` a respuestas 429 y métricas de abuso.

---

### SEC-007 — Canal de actualización con raíz de confianza única

**Severidad:** Media, defensa en profundidad

**Puntuación interna:** 5.4/10

**CWE:** CWE-494, descarga de código sin verificación suficiente

**Archivos:** `includes/class-cadv-woo-functionalities-updater.php:209-336`, `update-server/index.php:1-82`, `update-server/download.php:1-57`

#### Controles positivos

- metadata y descarga HTTPS;
- host de descarga restringido al host del endpoint;
- token Bearer de al menos 32 caracteres;
- comparación constante con `hash_equals`;
- paquete limitado a `update-server/packages` mediante `realpath`;
- URL HMAC con vencimiento;
- SHA-256 del ZIP verificado antes de que WordPress lo extraiga.

#### Riesgo residual

El ZIP y su SHA-256 provienen del mismo servidor. Si ese servidor o su token se comprometen, un atacante puede publicar simultáneamente un paquete y un hash válidos. Además:

- el endpoint todavía acepta `?token=`, que puede quedar en logs y analítica;
- la URL firmada dura 24 horas;
- una construcción ingenua del ZIP desde la carpeta de trabajo podría incluir archivos ignorados por Git como `update-server/config.php`, aunque actualmente no existe ni tiene historial.

#### Remediación

1. Firmar el manifiesto/paquete con Ed25519 fuera del servidor y fijar la clave pública en el plugin.
2. Retirar el soporte de token en query string.
3. Reducir la URL firmada a 5–15 minutos.
4. Separar físicamente el servidor de actualizaciones del paquete distribuible.
5. Crear un script de build con allowlist y comprobar el contenido del ZIP en CI.

#### Validación TDD

- Pre-fix: `CurrentRiskDetectionTests.test_update_server_accepts_legacy_query_token`.
- Post-fix: `RemediatedTargetStateTests.test_update_server_no_longer_accepts_query_token`.

---

### SEC-008 — Enumeración de cuentas mediante solicitud de ficha técnica

**Severidad:** Baja

**Puntuación interna:** 3.8/10

**CWE:** CWE-204, discrepancia observable de respuesta

**Archivo:** `includes/class-cadv-woo-functionalities.php:4096-4110`

#### Evidencia

Cuando el correo ya corresponde a una cuenta, `get_or_create_customer()` devuelve un mensaje explícito indicando que la cuenta existe. El flujo de recuperación de contraseña sí usa una respuesta genérica. CAPTCHA y rate limit reducen, pero no eliminan, la enumeración distribuida.

#### Remediación

Responder siempre con un mensaje genérico. Para cuentas existentes, enviar instrucciones de acceso/recuperación al correo registrado sin confirmar su existencia en pantalla.

---

### SEC-009 — CSP del visor requiere `script-src 'unsafe-inline'`

**Severidad:** Baja, defensa en profundidad

**Puntuación interna:** 3.2/10

**Archivo:** `includes/class-cadv-woo-functionalities.php:2118`

No se identificó una fuente de XSS en el visor: los valores dinámicos se escapan y la CSP niega fuentes externas. Sin embargo, `script-src 'unsafe-inline'` reduce el valor de la CSP ante una futura regresión de escape.

**Mejora:** mover el script a un archivo propio permitido por `'self'`, o emitir un nonce/hash CSP. Los estilos inline pueden tratarse de forma separada.

## 4. Observaciones informativas

### INF-001 — WooCommerce está detrás de la versión estable

WooCommerce local es 10.8.1 y la estable consultada es 10.9.4. Los comunicados 10.9.1–10.9.4 indican que no son actualizaciones de seguridad; por eso no se contabiliza como vulnerabilidad. Conviene actualizar tras probar staging, sobre todo porque 10.9.2 corrige un posible error fatal durante actualizaciones.

Fuentes:

- [WooCommerce releases](https://developer.woocommerce.com/releases/)
- [WooCommerce changelog](https://developer.woocommerce.com/changelog/)

### INF-002 — El plugin no declara versiones mínimas en su cabecera

`cesarandev-woo-func.php` declara `Requires Plugins: woocommerce`, pero no `Requires at least`, `Requires PHP` ni `WC requires at least`. Añadirlas reduce instalaciones incompatibles y hace explícita la matriz probada. No es una vulnerabilidad por sí sola.

### INF-003 — Ocultar menús de WooCommerce no es autorización

La personalización del escritorio solo cambia navegación. Los usuarios que conservan `manage_woocommerce` pueden acceder a URLs directas. El README ya lo documenta; mantener esta expectativa clara evita tratar ocultamiento visual como control de acceso.

## 5. Controles positivos observados

- Protección `ABSPATH` en archivos principales.
- Capacidades `manage_woocommerce`/`activate_plugins` y nonces en acciones administrativas.
- Nonces, honeypot, límites de longitud y rate limit en formularios públicos.
- Respuesta genérica en recuperación de contraseña y contraseñas mínimas de 12 caracteres.
- Saneamiento extensivo de entradas y escape contextual de salidas.
- Neutralización de fórmulas en exportación CSV.
- Validación de propiedad y ruta local en fichas PDF.
- Cabeceras restrictivas en visor y páginas JPEG.
- HTTPS, allowlist de host y verificación SHA-256 en actualizaciones.
- Token/configuración del servidor ignorados por Git; sin evidencia de secretos en historial.
- Sin `eval`, `exec`, `shell_exec`, `system`, `passthru` o deserialización insegura detectados.

## 6. Plan de remediación

### Fase 0 — Inmediata

1. Actualizar WordPress a 7.0.2+.
2. Hacer triage forense si 7.0.1 estuvo expuesto.
3. Verificar reCAPTCHA en producción y bloquear despliegue si no existe.

### Fase 1 — 0 a 7 días

1. Añadir límites al AJAX de posts.
2. Limitar y cachear renderizado PDF.
3. Fortalecer rate limiting.
4. Hacer genérica la respuesta de cuenta existente.

### Fase 2 — 7 a 30 días

1. Integrar privacidad WordPress y retención.
2. Añadir firma asimétrica al actualizador.
3. Retirar `?token=` y acortar URLs firmadas.
4. Añadir cabecera de compatibilidad y automatizar builds.

### Fase 3 — Continua

1. Ejecutar análisis estático y pruebas en cada cambio.
2. Monitorizar 403/429, fallos reCAPTCHA y errores Imagick.
3. Revisar CVE de WordPress/WooCommerce antes de cada despliegue.
4. Realizar revisión dinámica autenticada en staging.

## 7. Ejecución de pruebas

Archivo: `test_security_validations.py`

Estado esperado en la revisión actual:

- 5 pruebas de detección: pasan.
- 5 pruebas de estado remediado: omitidas.

Para activar las pruebas de aceptación después de implementar correcciones:

```powershell
$env:CADV_SECURITY_POST_FIX='1'
python -m unittest -v test_security_validations.py
```

## 8. Veredicto

**No desplegar o mantener expuesto WordPress 7.0.1.** Tras actualizar el core, el plugin puede considerarse con una base de seguridad razonable, pero requiere las mejoras P1 antes de clasificarlo como endurecido para una superficie pública de comercio/CRM.
