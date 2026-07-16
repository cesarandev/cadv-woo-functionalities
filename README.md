# CADV Woo Functionalities

Plugin de WordPress para WooCommerce que reemplaza el boton de compra en la pagina individual del producto por acciones comerciales.

## Estructura

```text
archivo principal del plugin             Bootstrap conservado por compatibilidad.
includes/class-cadv-woo-functionalities.php
includes/class-cadv-woo-functionalities-marketplace.php
includes/class-cadv-woo-functionalities-updater.php
assets/css/cadv-woo-functionalities.css
assets/css/cadv-woo-marketplace.css
assets/js/cadv-woo-functionalities.js
assets/js/cadv-woo-marketplace.js
update-server/                       Servidor privado de actualizaciones.
```

## Funcionalidad

- Boton "Consultar aqui" con enlace de WhatsApp configurable.
- Boton "Ver ficha tecnica" en el producto individual.
- Modal de solicitud con nombre completo, empresa, cargo, correo y telefono.
- Creacion o asociacion de cliente WooCommerce por correo electronico.
- Creacion de pedido WooCommerce de valor cero y estado completado.
- Acceso a los archivos descargables del producto desde `Mi cuenta > Descargas`.
- Evita duplicar fichas si el cliente ya tiene acceso al descargable solicitado.
- Notifica por correo a clientes con sesion iniciada cuando solicitan una ficha nueva.
- CRM administrativo de solicitudes, clientes, descargas y eliminaciones desde `WooCommerce > CRM Fichas Tecnicas`.
- Exportacion CSV filtrada desde el CRM.
- Portal restringido para clientes creados por solicitudes de ficha tecnica.
- Shortcode de CTAs generales para cotizaciones y newsletter, guardados como leads CRM sin crear usuarios.
- Shortcode de marketplace filtrable por linea/categoria, busqueda y Registro ICA.
- Actualizaciones privadas mediante servidor propio compatible con el sistema nativo de WordPress.

## Configuracion

1. Ve a `WooCommerce > CADV Woo Functionalities`.
2. Configura el numero de WhatsApp con codigo de pais, por ejemplo `573001234567`.
3. Ajusta el mensaje automatico si lo necesitas.
4. En cada producto, configura la ficha tecnica como archivo descargable nativo de WooCommerce.

## CRM de fichas tecnicas

Ve a `WooCommerce > CRM Fichas Tecnicas` para revisar solicitudes, filtrar por cliente, empresa, producto, estado CRM, descargas y solicitudes de eliminacion. Desde esta pantalla puedes actualizar estado, notas internas, fecha de seguimiento y descargar un CSV respetando los filtros activos.

Los usuarios creados por el formulario de ficha tecnica ven un portal reducido en `Mi cuenta`: Descargas, Detalles de la cuenta y Cerrar sesion. Sus detalles se muestran en modo solo lectura y el boton de eliminacion crea una solicitud pendiente en el CRM.

## Shortcode para Elementor

Usa este shortcode dentro de una plantilla o pagina de producto:

```text
[cadv_ficha_tecnica]
```

El shortcode detecta automaticamente el producto actual. Si necesitas usarlo fuera de una pagina de producto, puedes pasar el ID:

```text
[cadv_ficha_tecnica product_id="123"]
```

Alias heredado compatible:

```text
[cesarandev_ficha_tecnica]
```

Si el producto no tiene archivos descargables configurados en WooCommerce, el boton de ficha se muestra deshabilitado con el texto `Sin ficha existente`.

Para mostrar el Registro ICA del producto:

```text
[cadv_registro_ica]
```

Para mostrar la categoria/linea principal del producto:

```text
[cadv_categoria_producto]
```

Ambos detectan automaticamente el producto actual y tambien aceptan `product_id="123"`. Si el dato no existe, no imprimen nada.

## Shortcode para CTAs al CRM

Usa este shortcode para un formulario de cotizacion general:

```text
[cesarandev_crm_cta type="quote"]
```

Usa este shortcode para newsletter:

```text
[cesarandev_crm_cta type="newsletter"]
```

Usa este shortcode para solicitar servicios:

```text
[cesarandev_crm_cta type="services"]
```

El formulario permite elegir entre AgroPilot (dron agricola), analisis de suelo y foliar, asesoria agronomica en campo y plan de fertilizacion personalizado.

Los formularios guardan o actualizan un lead por correo en `WooCommerce > CRM Fichas Tecnicas > Leads / CTAs` sin crear usuario WordPress ni pedido WooCommerce. El producto de interes del formulario de cotizacion se carga desde las categorias de producto de WooCommerce y el formulario de servicios registra el servicio seleccionado.

Puedes personalizar textos:

```text
[cesarandev_crm_cta type="quote" title="Solicitar cotizacion" eyebrow="Cotizacion" description="Cuentenos sobre su cultivo y le responderemos."]
```

### Usarlo como enlace de un boton de Elementor

En el campo **Enlace** del boton, selecciona la etiqueta dinamica **Shortcode** y usa:

```text
[cesarandev_crm_cta type="quote" mode="url"]
```

Para un boton de servicios usa:

```text
[cesarandev_crm_cta type="services" mode="url"]
```

Puedes dejar un servicio seleccionado por defecto en cada boton:

```text
[cesarandev_crm_cta type="services" mode="url" service="agropilot"]
[cesarandev_crm_cta type="services" mode="url" service="analisis"]
[cesarandev_crm_cta type="services" mode="url" service="asesoria"]
[cesarandev_crm_cta type="services" mode="url" service="fertilizacion"]
```

Cada valor genera su propio modal, por lo que puedes usar varios botones con servicios diferentes en la misma pagina.

El shortcode devuelve un enlace interno y agrega automaticamente el formulario en un modal. Para newsletter puedes cambiar `type="quote"` por `type="newsletter"`. El shortcode sin `mode="url"` sigue siendo un formulario completo y debe colocarse en el widget **Shortcode**, no directamente en el campo Enlace.

Si la version de Elementor no ofrece la etiqueta dinamica **Shortcode**, agrega un widget **Shortcode** en cualquier parte de la pagina con:

```text
[cesarandev_crm_cta type="quote" mode="modal"]
```

Luego usa `#cesarandev-crm-cta-quote` como enlace normal del boton. El widget no ocupa espacio: solo registra el formulario modal.

Para servicios usa `type="services" mode="modal"` y enlaza el boton a `#cesarandev-crm-cta-services`.

## Shortcode de WhatsApp

El shortcode usa el numero guardado en `WooCommerce > CADV Woo Functionalities` y permite personalizar el mensaje y el texto del boton:

```text
[cesarandev_whatsapp message="Hola, me interesan los servicios de AgroBrokers." text="Hablar por WhatsApp"]
```

Para usarlo como URL dinamica en el campo **Enlace** de un boton de Elementor:

```text
[cesarandev_whatsapp mode="url" message="Hola, me interesa el servicio AgroPilot."]
```

El mensaje acepta las variables `{page_title}`, `{page_url}`, `{product_name}` y `{product_url}`. Tambien puedes pasar `product_id="123"` cuando necesites tomar los datos de un producto especifico.

## Shortcode de marketplace

Usa este shortcode en la pagina de marketplace:

```text
[cadv_marketplace]
```

Opciones disponibles:

```text
[cadv_marketplace per_page="12" columns="3" show_ica_filter="yes"]
```

Buscador externo para ubicarlo en otra seccion de Elementor:

```text
[cadv_marketplace_search target="/marketplace/"]
```

Este buscador envia a `/marketplace/?cadv_search=...` y el marketplace carga los resultados filtrados. La busqueda revisa nombre, descripcion, categoria/linea, etiquetas, segmento, tipo, Registro ICA y SKU.

El filtro de linea usa las categorias padre de producto WooCommerce. Cada categoria de producto permite configurar un color para el marketplace. Cada producto permite configurar un campo `Registro ICA`, que se muestra en las tarjetas y habilita el filtro de productos con registro.

## Carga masiva de productos del marketplace

Para importar productos desde el CSV nativo de WooCommerce, puedes usar estas columnas:

```text
Nombre
Segmento
Linea comercial
Tipo
Descripcion comercial-tecnica
Registro ICA
Categorias
Imagenes
```

Equivalencias:

- `Nombre`: producto.
- `Linea comercial`: crea o asigna una categoria padre de producto, que se usa como linea en el filtro del marketplace.
- `Descripcion comercial-tecnica`: se guarda como dato del marketplace y tambien se usa como descripcion corta si el producto no trae una.
- `Segmento`, `Tipo` y `Registro ICA`: se guardan como datos del producto y se exportan nuevamente en CSV.
- `Categorias`: sigue siendo la columna nativa de WooCommerce para categorias adicionales o subcategorias.

## Actualizaciones privadas

El plugin incluye un cliente de actualizaciones en `includes/class-cadv-woo-functionalities-updater.php` y un servidor PHP basico en `update-server/`.

Para activar actualizaciones en un sitio, configura el endpoint en `wp-config.php`:

```php
define( 'CADV_WOO_FUNCTIONALITIES_UPDATE_SERVER', 'https://tu-dominio.com/update-server/index.php?token=TU_TOKEN' );
```

El servidor se configura copiando `update-server/config.example.php` a `update-server/config.php`, subiendo el ZIP del plugin a `update-server/packages/` y actualizando la version en el config.

## Variables del mensaje de WhatsApp

- `{product_name}`: nombre del producto.
- `{product_url}`: enlace del producto.

## Mensaje por defecto

```text
Hola, estoy viendo el producto {product_name} en la pagina web y quisiera mas informacion. {product_url}
```
