# Servidor privado de actualizaciones

Este directorio es un servidor PHP simple para publicar actualizaciones privadas de `cadv-woo-functionalities`.

## Instalacion

1. Sube la carpeta `update-server` a un hosting con PHP.
2. Copia `config.example.php` como `config.php`.
3. Cambia `download_token` por un token largo y privado.
4. Sube el ZIP del plugin a `packages/`.
5. Ajusta `version` y `package_file` en `config.php`.

## Endpoint

Usa esta URL como servidor de actualizaciones:

```text
https://tu-dominio.com/update-server/index.php?token=TU_TOKEN
```

En cada sitio WordPress que tenga el plugin instalado, configura el endpoint con una constante en `wp-config.php`:

```php
define( 'CADV_WOO_FUNCTIONALITIES_UPDATE_SERVER', 'https://tu-dominio.com/update-server/index.php?token=TU_TOKEN' );
```

Tambien puedes configurarlo con filtro:

```php
add_filter(
	'cadv_woo_functionalities_update_server',
	function () {
		return 'https://tu-dominio.com/update-server/index.php?token=TU_TOKEN';
	}
);
```

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
