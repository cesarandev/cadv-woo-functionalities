# CADV Tailored To Calculator

Plugin independiente de WordPress para preparar solicitudes de formulación personalizada Tailored To. Se integra visualmente con CADV Woo Functionalities, pero conserva su propio repositorio, almacenamiento y ciclo de versiones.

## Alcance de la versión 0.2.0

- Asistente profesional de seis etapas compatible con Elementor mediante shortcode.
- Divulgación progresiva: los detalles de suelo, foliar, agua y fertirriego aparecen solo cuando corresponden.
- Captura de variedad, edad, etapa, sistema productivo, objetivo, rendimiento actual y problema observado.
- Estado de análisis de suelo, foliar y agua, con variables técnicas básicas para preparar la revisión.
- Manejo actual, fraccionamiento, método de aplicación y riesgos reconocidos.
- Perfil N–P₂O₅–K₂O **simulado**, construido en el servidor con reglas deterministas.
- Conversor técnico de P, K, Ca y Mg entre forma elemental y óxido.
- Precauciones explícitas para boro y cobre.
- Indicador visual de preparación del expediente técnico.
- Dosis por hectárea y cantidad total bloqueadas hasta contar con análisis de suelo, análisis foliar y validación de la meta de rendimiento.
- Expediente propio en `Solicitudes Tailored To`.
- Envío opcional al CRM de CADV Woo mediante un contrato desacoplado.
- WhatsApp con resumen no prescriptivo.

La fórmula que aparece en pantalla no representa una composición comercial aprobada ni una recomendación agronómica. Tailored To se trata como una capacidad de formulación personalizada, no como un producto de fórmula fija.

## Instalación

1. Comprima la carpeta `cadv-tailored-to-calculator`.
2. En WordPress, vaya a **Plugins → Añadir plugin → Subir plugin**.
3. Active **CADV Tailored To Calculator**.
4. En Elementor, inserte un widget **Shortcode**.
5. Use:

```text
[cadv_tailored_to_calculator]
```

Atributos opcionales:

```text
[cadv_tailored_to_calculator title="Construya su fórmula" description="Prepare su solicitud Tailored To" accent="#203212"]
```

El plugin no exige Elementor y funciona en cualquier área de WordPress que procese shortcodes.

## Integración con CADV Woo

Sin el puente, las solicitudes se guardan normalmente y aparecen con estado CRM `pending`. Para sincronizarlas, implemente el filtro versionado:

```php
apply_filters( 'cadv_woo_functionalities_ingest_lead', $default_result, $payload );
```

La instrucción completa y el contrato están en [docs/cadv-woo-crm-integration.md](docs/cadv-woo-crm-integration.md).

## Datos y privacidad

El formulario guarda los datos de contacto y el contexto declarado por el usuario. No solicita archivos de laboratorio en esta versión. El CRM recibe únicamente un resumen comercial y un enlace al expediente técnico; el plugin de la calculadora conserva el detalle.

Antes de producción:

- publique una Política de Privacidad en WordPress;
- confirme el número de WhatsApp;
- limite el acceso administrativo a los expedientes según los roles internos;
- configure backups y retención de solicitudes;
- pruebe el flujo completo en staging.

## Fuentes públicas usadas para delimitar la simulación

Consulta: 23 de julio de 2026.

- [Agrobrokers](https://agrobrokers.com.co/): soluciones personalizadas y mezclas NPK.
- [Agrobrokers en LinkedIn](https://co.linkedin.com/company/agrobrokers): formulación basada en información del sistema productivo y enfoque 4R.
- [Haifa Fertilizer Conversion Calculator](https://www.haifa-group.com/fertilizer-conversion-calculator): referencia funcional para el conversor de unidades.

Las fuentes públicas no se usan como mediciones del predio ni para calcular dosis.

## Arquitectura

- `cadv-tailored-to-calculator.php`: bootstrap.
- `includes/class-cadv-tt-formula-engine.php`: simulación no prescriptiva.
- `includes/class-cadv-tailored-to-calculator.php`: shortcode, AJAX, expediente y adaptador CRM.
- `assets/`: interfaz.
- `tests/`: verificaciones básicas sin WordPress.
