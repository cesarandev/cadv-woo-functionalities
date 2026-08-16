# Resumen ejecutivo de seguridad

**Proyecto:** CADV Woo Functionalities 1.1.55

**Fecha del análisis:** 24 de julio de 2026

**Alcance:** código del plugin, servidor privado de actualizaciones, WordPress y WooCommerce instalados localmente

**Resultado global:** **riesgo alto**, impulsado principalmente por WordPress 7.0.1

## Conclusión

No encontré una inyección SQL, ejecución remota, escalada de privilegios ni exposición de secretos atribuible directamente al código del plugin. El plugin aplica controles útiles: capacidades y nonces en administración, validación de propiedad para PDFs, saneamiento de entradas, escape de salidas, límites en formularios, protección CSV y verificación HTTPS/SHA-256 de actualizaciones.

Sin embargo, el entorno usa **WordPress 7.0.1**, versión afectada por una cadena de inyección SQL y ejecución remota sin autenticación. La vulnerabilidad **CVE-2026-63030** tiene CVSS 9.8 y está en el catálogo de vulnerabilidades explotadas de CISA. Esta actualización debe hacerse antes de cualquier otro trabajo.

Después de actualizar WordPress, los riesgos principales del plugin son abuso automatizado de formularios cuando reCAPTCHA no está configurado, agotamiento de recursos en el renderizado PDF y en el AJAX público de entradas, y ausencia de integración con las herramientas de exportación/borrado de datos personales de WordPress.

## Riesgos priorizados

| Prioridad | Riesgo | Severidad | Acción |
|---|---|---:|---|
| P0 | WordPress 7.0.1 vulnerable a RCE/SQLi explotada activamente | Crítica, 9.9/10 | Actualizar a WordPress 7.0.2 o posterior inmediatamente |
| P1 | CAPTCHA matemático automatizable si no hay claves reCAPTCHA | Alta condicional, 7.6/10 | Exigir protección antiautomatización fuerte en producción |
| P1 | Renderizado Imagick sin presupuesto de memoria, tamaño o páginas | Media, 6.5/10 | Limitar archivo/páginas/recursos, cachear y rate-limit |
| P1 | AJAX público del shortcode de posts sin rate limit ni límite de página | Media, 6.2/10 | Añadir cuota, límite de página y retirar `rand` |
| P2 | Datos CRM sin exportador, borrador ni política de retención integrada | Media, 5.8/10 | Integrar privacidad de WordPress y definir retención |
| P2 | Rate limits con transients no atómicos y dependencia de IP/proxy | Media, 5.6/10 | Usar contador atómico y documentar proxies confiables |
| P2 | Confianza del actualizador concentrada en un solo servidor | Media, 5.4/10 | Firmar paquetes de forma asimétrica y retirar token por URL |
| P3 | Respuesta de ficha técnica permite enumerar correos existentes | Baja, 3.8/10 | Usar respuesta genérica y flujo fuera de banda |

## Plan recomendado

**Hoy**

1. Actualizar WordPress a 7.0.2 o superior y verificar integridad.
2. Si el sitio estuvo expuesto a Internet con 7.0.1, revisar indicadores de compromiso, usuarios administradores, archivos PHP nuevos o alterados y registros de acceso.
3. Confirmar que las claves reCAPTCHA están configuradas en producción. La base de datos local estaba detenida durante este análisis, así que ese estado no pudo verificarse.

**Próximos 7 días**

1. Endurecer el AJAX de posts y el visor PDF.
2. Hacer que los formularios fallen de forma segura si falta la protección antiautomatización de producción.
3. Corregir la enumeración de correos y la estrategia de rate limiting.

**Próximos 30 días**

1. Implementar exportación, borrado y retención de datos CRM.
2. Añadir firma Ed25519 al canal de actualizaciones y eliminar compatibilidad con `?token=`.
3. Incorporar las pruebas de seguridad del repositorio a CI.

## Alcance y limitaciones

El sitio y su base de datos estaban detenidos. No se hicieron pruebas dinámicas autenticadas, pruebas de penetración ni lectura de opciones guardadas en la base de datos. No hay manifiestos Composer/npm en el plugin, por lo que el análisis automatizado de dependencias reportó cero paquetes; WordPress y WooCommerce se revisaron manualmente. WooCommerce 10.8.1 está detrás de 10.9.4, pero las publicaciones oficiales consultadas no identifican las versiones 10.9.1–10.9.4 como actualizaciones de seguridad.

El detalle técnico, evidencia y criterios de validación están en `security-report-technical.md`.
