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
[cesarandev_ficha_tecnica]
```

El shortcode detecta automaticamente el producto actual. Si necesitas usarlo fuera de una pagina de producto, puedes pasar el ID:

```text
[cesarandev_ficha_tecnica product_id="123"]
```

## Shortcode para CTAs al CRM

Usa este shortcode para un formulario de cotizacion general:

```text
[cesarandev_crm_cta type="quote"]
```

Usa este shortcode para newsletter:

```text
[cesarandev_crm_cta type="newsletter"]
```

Ambos guardan o actualizan un lead por correo en `WooCommerce > CRM Fichas Tecnicas > Leads / CTAs` sin crear usuario WordPress ni pedido WooCommerce. El producto de interes del formulario de cotizacion se carga desde las categorias de producto de WooCommerce.

Puedes personalizar textos:

```text
[cesarandev_crm_cta type="quote" title="Solicitar cotizacion" eyebrow="Cotizacion" description="Cuentenos sobre su cultivo y le responderemos."]
```

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
