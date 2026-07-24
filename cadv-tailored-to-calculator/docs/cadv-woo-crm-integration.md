# Instrucción completa para CADV Woo Functionalities

## Objetivo

Permitir que `CADV Tailored To Calculator` cree o actualice un lead en el CRM existente sin acceder directamente a métodos privados ni escribir metadatos internos desde el plugin nuevo.

La calculadora ya emite este filtro:

```php
apply_filters( 'cadv_woo_functionalities_ingest_lead', $default_result, $payload );
```

CADV Woo debe implementar el receptor. No se debe exponer un endpoint AJAX nuevo: la solicitud pública ya fue validada, limitada y almacenada por la calculadora; la comunicación ocurre dentro del mismo proceso de WordPress.

## 1. Registrar el receptor

En el constructor de `Cesarandev_Woo_Functionalities`, agregue:

```php
add_filter(
	'cadv_woo_functionalities_ingest_lead',
	array( $this, 'ingest_external_lead' ),
	10,
	2
);
```

## 2. Aceptar el tipo Tailored To

En `normalize_cta_type()`, antes del `return ''`, agregue:

```php
if ( in_array( $type, array( 'tailored_to', 'tailored-to', 'tailoredto' ), true ) ) {
	return 'tailored_to';
}
```

En `format_cta_types_label()`, agregue al arreglo `$labels`:

```php
'tailored_to' => __( 'Tailored To', 'cadv-woo-functionalities' ),
```

En la validación del filtro CRM, donde actualmente se permiten `quote`, `services` y `newsletter`, incluya `tailored_to`. También agregue la opción al selector visible:

```php
<option value="tailored_to" <?php selected( $filters['cta_type'], 'tailored_to' ); ?>>
	<?php esc_html_e( 'Tailored To', 'cadv-woo-functionalities' ); ?>
</option>
```

## 3. Implementar el receptor

Agregue estos métodos a la clase principal. El método usa los servicios privados existentes `find_crm_lead_id_by_email()` y `upsert_crm_lead()`, evita duplicar el mismo expediente y mantiene un historial compacto.

```php
/**
 * Ingest a lead emitted by a trusted local CADV plugin.
 *
 * @param mixed $current Result supplied by a previous receiver.
 * @param mixed $payload Versioned lead payload.
 * @return array|WP_Error
 */
public function ingest_external_lead( $current, $payload ) {
	if ( ! is_wp_error( $current ) ) {
		return $current;
	}

	if ( ! is_array( $payload ) ) {
		return new WP_Error( 'cadv_wf_external_invalid_payload', __( 'Payload externo inválido.', 'cadv-woo-functionalities' ) );
	}

	$schema  = isset( $payload['schema_version'] ) ? absint( $payload['schema_version'] ) : 0;
	$source  = isset( $payload['source'] ) ? sanitize_key( $payload['source'] ) : '';
	$request = isset( $payload['external_request_id'] ) ? sanitize_text_field( $payload['external_request_id'] ) : '';
	$email   = isset( $payload['email'] ) ? sanitize_email( $payload['email'] ) : '';

	if ( 1 !== $schema || 'cadv-tailored-to-calculator' !== $source ) {
		return new WP_Error( 'cadv_wf_external_unsupported_source', __( 'Origen o versión no soportados.', 'cadv-woo-functionalities' ) );
	}

	if ( '' === $request || ! is_email( $email ) ) {
		return new WP_Error( 'cadv_wf_external_missing_identity', __( 'Falta la identidad de la solicitud.', 'cadv-woo-functionalities' ) );
	}

	$lead_id  = $this->find_crm_lead_id_by_email( $email );
	$requests = $lead_id ? (array) get_post_meta( $lead_id, '_cesarandev_wf_tailored_to_requests', true ) : array();

	foreach ( $requests as $saved_request ) {
		if ( isset( $saved_request['external_request_id'] ) && $request === $saved_request['external_request_id'] ) {
			return array(
				'lead_id' => (int) $lead_id,
				'status'  => 'already_synced',
			);
		}
	}

	$data = array(
		'cta_type'         => 'tailored_to',
		'full_name'        => sanitize_text_field( $payload['full_name'] ?? '' ),
		'company'          => sanitize_text_field( $payload['company'] ?? '' ),
		'position'         => sanitize_text_field( $payload['position'] ?? '' ),
		'phone'            => sanitize_text_field( $payload['phone'] ?? '' ),
		'email'            => $email,
		'product_interest' => 'Tailored To',
		'demo_date'        => '',
		'crop_type'        => sanitize_text_field( $payload['crop_type'] ?? '' ),
		'source_url'       => esc_url_raw( $payload['source_url'] ?? '' ),
	);

	if ( '' === $data['full_name'] || '' === $data['phone'] ) {
		return new WP_Error( 'cadv_wf_external_missing_contact', __( 'Faltan datos de contacto.', 'cadv-woo-functionalities' ) );
	}

	$lead_id = $this->upsert_crm_lead( $data );

	if ( is_wp_error( $lead_id ) ) {
		return $lead_id;
	}

	$requests   = (array) get_post_meta( $lead_id, '_cesarandev_wf_tailored_to_requests', true );
	$requests[] = array(
		'external_request_id' => $request,
		'technical_status'    => sanitize_key( $payload['technical_status'] ?? 'simulation' ),
		'formula_summary'     => sanitize_text_field( $payload['formula_summary'] ?? '' ),
		'area_ha'             => isset( $payload['area_ha'] ) ? (float) $payload['area_ha'] : 0,
		'location'            => sanitize_text_field( $payload['location'] ?? '' ),
		'yield_goal_t_ha'     => isset( $payload['yield_goal_t_ha'] ) ? (float) $payload['yield_goal_t_ha'] : 0,
		'variety'             => sanitize_text_field( $payload['variety'] ?? '' ),
		'stage'               => sanitize_key( $payload['stage'] ?? '' ),
		'primary_goal'        => sanitize_key( $payload['primary_goal'] ?? '' ),
		'analysis_summary'    => sanitize_text_field( $payload['analysis_summary'] ?? '' ),
		'irrigation_system'   => sanitize_key( $payload['irrigation_system'] ?? '' ),
		'readiness_label'     => sanitize_text_field( $payload['readiness_label'] ?? '' ),
		'request_admin_url'   => esc_url_raw( $payload['request_admin_url'] ?? '' ),
		'received_at'         => current_time( 'mysql' ),
	);
	$requests   = array_slice( $requests, -50 );

	update_post_meta( $lead_id, '_cesarandev_wf_tailored_to_requests', $requests );
	update_post_meta( $lead_id, '_cesarandev_wf_last_external_request_id', $request );
	update_post_meta( $lead_id, '_cesarandev_wf_last_formula_summary', sanitize_text_field( $payload['formula_summary'] ?? '' ) );
	update_post_meta( $lead_id, '_cesarandev_wf_last_technical_status', sanitize_key( $payload['technical_status'] ?? 'simulation' ) );
	update_post_meta( $lead_id, '_cesarandev_wf_last_request_admin_url', esc_url_raw( $payload['request_admin_url'] ?? '' ) );

	return array(
		'lead_id' => (int) $lead_id,
		'status'  => 'synced',
	);
}
```

Si el plugin conserva compatibilidad con PHP 7.4, el operador `??` usado arriba es válido.

## 4. Contrato del payload v1

| Campo | Tipo | Requerido | Uso |
|---|---:|:---:|---|
| `schema_version` | entero | Sí | Debe ser `1`. |
| `source` | string | Sí | Debe ser `cadv-tailored-to-calculator`. |
| `external_request_id` | string | Sí | Idempotencia, formato `TT-AAAAMMDD-000000`. |
| `cta_type` | string | Sí | `tailored_to`. |
| `full_name`, `email`, `phone` | string | Sí | Contacto principal. |
| `company`, `position` | string | No | Segmentación comercial. |
| `product_interest` | string | Sí | `Tailored To`. |
| `crop_type`, `source_url` | string | Sí | Contexto y atribución. |
| `privacy_accepted_at` | fecha | Sí | Evidencia de aceptación; la calculadora conserva el expediente original. |
| `technical_status` | string | Sí | Actualmente `simulation`. |
| `formula_summary` | string | Sí | Perfil N–P₂O₅–K₂O no aprobado. |
| `area_ha`, `location`, `yield_goal_t_ha` | mixto | Sí | Contexto declarado, no medición de laboratorio. |
| `variety`, `stage`, `primary_goal` | string | No | Segmentación técnica y comercial del caso. |
| `analysis_summary` | string | No | Estado resumido de suelo, foliar y agua. |
| `irrigation_system` | string | No | Contexto del sistema de aplicación. |
| `readiness_label` | string | No | Preparación del expediente; no es nivel de confianza agronómica. |
| `request_admin_url` | URL | Sí | Enlace al expediente completo en la calculadora. |

Respuesta admitida por la calculadora:

```php
array(
	'lead_id' => 123,
	'status'  => 'synced', // o already_synced
)
```

Un `WP_Error` mantiene la solicitud en estado `pending`.

## 5. Presentación en CRM

En el detalle del lead:

- mostrar `Tailored To` en los tipos de interacción;
- mostrar el último perfil como **“Simulación no aprobada”**;
- enlazar `request_admin_url` con el texto **“Abrir expediente Tailored To”**;
- no presentar `formula_summary` como producto cotizable ni como dosis;
- usar el expediente de la calculadora como fuente completa.

En el CSV, agregue columnas opcionales:

```text
Último expediente Tailored To
Última fórmula simulada
Estado técnico Tailored To
```

No copie al CRM análisis completos de suelo, foliares o agua. El CRM debe conservar solo el resumen y el enlace; la calculadora es propietaria del expediente agronómico.

## 6. Pruebas de aceptación

1. Activar ambos plugins en staging.
2. Enviar una solicitud y comprobar que se crean:
   - un expediente `cadv_tt_request`;
   - un lead o actualización del lead existente;
   - una interacción `tailored_to`;
   - el enlace administrativo al expediente.
3. Reprocesar el mismo `external_request_id`: no debe duplicarse.
4. Enviar otra solicitud con el mismo correo: debe actualizar el mismo lead y añadir un nuevo expediente.
5. Desactivar CADV Woo y enviar: la calculadora debe guardar la solicitud con CRM `pending`.
6. Confirmar que el filtro de CRM, la vista y el CSV escapan todas las salidas.
7. Confirmar que ningún lugar rotula la simulación como fórmula aprobada, dosis o recomendación final.

## 7. Versión recomendada

Publicar este cambio como una versión nueva de CADV Woo Functionalities y declarar en las notas:

```text
Añade el contrato CRM v1 para solicitudes externas Tailored To.
```

No es necesario que ambos plugins compartan repositorio ni dependencias de código.
