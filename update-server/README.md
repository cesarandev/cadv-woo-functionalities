# Servidor privado de actualizaciones

Este directorio es un servidor PHP simple para publicar actualizaciones privadas de `cesarandev-woo-func`.

## Instalacion

1. Sube la carpeta `update-server` a un hosting con PHP.
2. Copia `config.example.php` como `config.php`.
3. Cambia `download_token` por un token largo y privado.
4. Sube el ZIP del plugin a `packages/`.
5. Ajusta `version`, `package_file` y la URL HTTPS `base_url` en `config.php`.

## Endpoint

Usa esta URL como servidor de actualizaciones:

```text
https://tu-dominio.com/update-server/index.php
```

En cada sitio WordPress que tenga el plugin instalado, configura el endpoint con una constante en `wp-config.php`:

```php
define( 'CADV_WOO_FUNCTIONALITIES_UPDATE_SERVER', 'https://tu-dominio.com/update-server/index.php' );
define( 'CADV_WOO_FUNCTIONALITIES_UPDATE_TOKEN', 'TU_TOKEN_LARGO' );
```

Tambien puedes configurarlo con filtro:

```php
add_filter(
	'cadv_woo_functionalities_update_server',
	function () {
		return 'https://tu-dominio.com/update-server/index.php';
	}
);
```

El cliente envía el token como `Authorization: Bearer`. El parámetro legado `?token=` solo se conserva para migrar instalaciones anteriores.

## Publicar una version

1. Actualiza la cabecera `Version` y `CADV_WOO_FUNCTIONALITIES_VERSION` del plugin.
2. Genera un ZIP con la carpeta exacta del plugin instalado en la raiz del ZIP.
3. Sube el ZIP a `update-server/packages/`.
4. Cambia `version`, `package_file` y `changelog` en `config.php`.
5. En WordPress, ve a `Escritorio > Actualizaciones` o espera el chequeo automatico.

## Seguridad

- Usa HTTPS.
- No compartas el token.
- No guardes `config.php` en repositorios publicos.
- El endpoint de descarga solo entrega archivos dentro de `packages/`.
- Las URL de descarga expiran, están firmadas y no incluyen el token privado.
- El cliente verifica el SHA-256 del ZIP antes de permitir su instalación.
