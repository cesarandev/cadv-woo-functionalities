<?php
/**
 * Deterministic demonstration engine.
 *
 * @package CADVTailoredTo
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'CADV_TT_TESTING' ) ) {
	exit;
}

/**
 * Build a non-prescriptive N-P2O5-K2O simulation.
 */
final class CADV_TT_Formula_Engine {

	const VERSION     = 'simulation-2026-07-23-v2';
	const GRADE_TOTAL = 45;

	/**
	 * Supported crops.
	 *
	 * Values are relative UX weights, not agronomic recommendations.
	 *
	 * @return array
	 */
	public static function get_crop_profiles() {
		return array(
			'palma'     => array( 'label' => 'Palma de aceite', 'weights' => array( 0.90, 0.75, 1.35 ) ),
			'banano'    => array( 'label' => 'Banano', 'weights' => array( 1.00, 0.70, 1.40 ) ),
			'arroz'     => array( 'label' => 'Arroz', 'weights' => array( 1.25, 1.00, 0.80 ) ),
			'maiz'      => array( 'label' => 'Maíz', 'weights' => array( 1.20, 1.00, 0.90 ) ),
			'cafe'      => array( 'label' => 'Café', 'weights' => array( 1.05, 0.80, 1.15 ) ),
			'aguacate'  => array( 'label' => 'Aguacate Hass', 'weights' => array( 0.90, 0.80, 1.30 ) ),
			'citricos'  => array( 'label' => 'Cítricos', 'weights' => array( 1.00, 0.80, 1.20 ) ),
			'otro'      => array( 'label' => 'Otro cultivo', 'weights' => array( 1.00, 1.00, 1.00 ) ),
		);
	}

	/**
	 * Supported stages.
	 *
	 * @return array
	 */
	public static function get_stage_profiles() {
		return array(
			'establishment' => array( 'label' => 'Establecimiento', 'weights' => array( 0.85, 1.35, 0.85 ) ),
			'vegetative'    => array( 'label' => 'Crecimiento vegetativo', 'weights' => array( 1.30, 0.85, 0.90 ) ),
			'flowering'     => array( 'label' => 'Floración', 'weights' => array( 0.95, 1.10, 1.20 ) ),
			'production'    => array( 'label' => 'Producción o llenado', 'weights' => array( 1.00, 0.80, 1.35 ) ),
			'maintenance'   => array( 'label' => 'Mantenimiento', 'weights' => array( 1.00, 1.00, 1.00 ) ),
		);
	}

	/**
	 * Build a simulated formula.
	 *
	 * This method never returns a dose.
	 *
	 * @param array $data Sanitized calculator context.
	 * @return array
	 */
	public static function calculate( array $data ) {
		$data = array_merge(
			array(
				'application'            => 'soil',
				'irrigation_system'      => 'rainfed',
				'management_risks'       => array(),
				'yield_goal'             => 0,
				'boron_status'           => 'unknown',
				'zinc_status'            => 'unknown',
				'copper_status'          => 'unknown',
				'exclude_boron'          => false,
				'exclude_copper'         => false,
				'soil_analysis_status'   => 'none',
				'soil_analysis_date'     => '',
				'soil_laboratory'        => '',
				'foliar_analysis_status' => 'none',
				'foliar_analysis_date'   => '',
				'foliar_laboratory'      => '',
				'water_analysis_status'  => 'none',
				'water_analysis_date'    => '',
				'water_ph'               => 0,
				'water_ec'               => 0,
			),
			$data
		);
		$crops  = self::get_crop_profiles();
		$stages = self::get_stage_profiles();
		$crop   = isset( $crops[ $data['crop'] ] ) ? $crops[ $data['crop'] ] : $crops['otro'];
		$stage  = isset( $stages[ $data['stage'] ] ) ? $stages[ $data['stage'] ] : $stages['maintenance'];

		$level_multipliers = array(
			'low'    => 1.25,
			'medium' => 1.00,
			'high'   => 0.65,
			'unknown' => 1.00,
		);

		$levels = array(
			isset( $data['n_level'] ) ? $data['n_level'] : 'medium',
			isset( $data['p_level'] ) ? $data['p_level'] : 'medium',
			isset( $data['k_level'] ) ? $data['k_level'] : 'medium',
		);
		$raw     = array();

		for ( $index = 0; $index < 3; $index++ ) {
			$level       = isset( $level_multipliers[ $levels[ $index ] ] ) ? $levels[ $index ] : 'medium';
			$raw[ $index ] = $crop['weights'][ $index ] * $stage['weights'][ $index ] * $level_multipliers[ $level ];
		}

		$grade   = self::allocate_grade( $raw, self::GRADE_TOTAL );
		$formula = implode( '-', $grade );
		$notes   = array(
			'Esta fórmula es una simulación de experiencia de usuario; no corresponde a una composición comercial aprobada.',
			'La relación se construye con ponderaciones demostrativas de cultivo, etapa y lectura declarada de N-P-K.',
		);

		$nutrient_names = array( 'Nitrógeno', 'Fósforo', 'Potasio' );
		foreach ( $levels as $index => $level ) {
			if ( 'high' === $level ) {
				$notes[] = sprintf( 'Se redujo la ponderación de %s porque fue marcado como alto.', $nutrient_names[ $index ] );
			} elseif ( 'unknown' === $level ) {
				$notes[] = sprintf( 'No se declaró una lectura de %s; su ponderación se mantuvo neutral.', $nutrient_names[ $index ] );
			}
		}

		if ( 'low' === $data['boron_status'] ) {
			$notes[] = 'El usuario reporta boro bajo; debe confirmarse con unidades, método y referencia por el estrecho margen entre deficiencia y toxicidad.';
		} elseif ( 'high' === $data['boron_status'] || ! empty( $data['exclude_boron'] ) ) {
			$notes[] = 'La solicitud indica excluir boro hasta validar su necesidad y el riesgo de toxicidad.';
		}

		if ( 'low' === $data['zinc_status'] ) {
			$notes[] = 'El usuario reporta zinc bajo; su inclusión y relación con otros micronutrientes requieren confirmación analítica.';
		} elseif ( 'high' === $data['zinc_status'] ) {
			$notes[] = 'La solicitud indica evitar aportes adicionales de zinc hasta validar el resultado reportado.';
		}

		if ( 'high' === $data['copper_status'] || ! empty( $data['exclude_copper'] ) ) {
			$notes[] = 'La solicitud indica excluir cobre para evitar agravar un posible exceso.';
		}

		$risk_notes = array(
			'volatilization' => 'El riesgo declarado de volatilización orienta a revisar fuente, incorporación, humedad y momento de aplicación.',
			'leaching'       => 'El riesgo declarado de lixiviación orienta a sincronizar y fraccionar las aplicaciones.',
			'runoff'         => 'La escorrentía declarada requiere revisar lugar, momento y protección de la superficie antes de ajustar fertilizantes.',
			'salinity'       => 'La salinidad no debe abordarse solo con fertilizante; requiere diagnóstico de sales, agua y drenaje.',
			'acidity'        => 'La acidez o el aluminio requieren evaluar una intervención correctiva independiente de la fórmula fertilizante.',
			'drainage'       => 'El drenaje o la compactación pueden limitar la respuesta nutricional y requieren manejo físico del suelo.',
			'erosion'        => 'El riesgo de erosión exige priorizar cobertura y conservación del suelo junto con la estrategia nutricional.',
		);
		foreach ( (array) $data['management_risks'] as $risk ) {
			if ( isset( $risk_notes[ $risk ] ) ) {
				$notes[] = $risk_notes[ $risk ];
			}
		}

		$soil_identified   = 'current' === $data['soil_analysis_status'] && ! empty( $data['soil_analysis_date'] ) && ! empty( $data['soil_laboratory'] );
		$foliar_identified = 'current' === $data['foliar_analysis_status'] && ! empty( $data['foliar_analysis_date'] ) && ! empty( $data['foliar_laboratory'] );
		$water_needed      = in_array( $data['application'], array( 'fertigation', 'mixed' ), true ) || 'rainfed' !== $data['irrigation_system'];
		$water_identified  = $water_needed && (
			'current' === $data['water_analysis_status']
			&& ! empty( $data['water_analysis_date'] )
			&& $data['water_ph'] > 0
			&& $data['water_ec'] > 0
		);
		$missing           = array(
			'Informe completo del análisis de suelo, con unidades, métodos y profundidad de muestreo.',
			'Informe completo del análisis foliar, con tejido, unidades y protocolo de muestreo.',
		);
		$missing[] = 'Validación agronómica de la meta de rendimiento.';
		$missing[] = 'Composición y restricciones oficiales de fabricación de Tailored To.';

		if ( $water_needed ) {
			$missing[] = 'Informe completo del análisis de agua y datos técnicos del sistema de riego.';
		}
		if ( in_array( $data['application'], array( 'fertigation', 'mixed' ), true ) ) {
			$notes[]   = 'Para fertirriego deben verificarse CE, bicarbonatos, calcio, precipitados y compatibilidad de tanque.';
		}

		$evidence_status = array(
			array(
				'label'  => 'Meta de rendimiento',
				'status' => ! empty( $data['yield_goal'] ) ? 'declared' : 'pending',
				'text'   => ! empty( $data['yield_goal'] ) ? 'Declarada; pendiente de validación' : 'Pendiente',
			),
			array(
				'label'  => 'Análisis de suelo',
				'status' => $soil_identified ? 'partial' : ( 'none' === $data['soil_analysis_status'] ? 'pending' : 'partial' ),
				'text'   => $soil_identified ? 'Identificado; falta revisar el informe' : ( 'none' === $data['soil_analysis_status'] ? 'No disponible' : 'Incompleto o no vigente' ),
			),
			array(
				'label'  => 'Análisis foliar',
				'status' => $foliar_identified ? 'partial' : ( 'none' === $data['foliar_analysis_status'] ? 'pending' : 'partial' ),
				'text'   => $foliar_identified ? 'Identificado; falta revisar el informe' : ( 'none' === $data['foliar_analysis_status'] ? 'No disponible' : 'Incompleto o no vigente' ),
			),
			array(
				'label'  => 'Agua y riego',
				'status' => ! $water_needed ? 'not_required' : ( $water_identified ? 'partial' : 'pending' ),
				'text'   => ! $water_needed ? 'No aplica en esta etapa' : ( $water_identified ? 'Datos básicos declarados; falta el informe' : 'Información pendiente' ),
			),
			array(
				'label'  => 'Validación Tailored To',
				'status' => 'pending',
				'text'   => 'Requiere revisión técnica y de fabricación',
			),
		);

		$ready_count = 0;
		foreach ( $evidence_status as $evidence ) {
			if ( in_array( $evidence['status'], array( 'ready', 'declared', 'partial', 'not_required' ), true ) ) {
				$ready_count++;
			}
		}

		return array(
			'engine_version'       => self::VERSION,
			'status'               => 'simulation',
			'status_label'         => 'Simulación pendiente de validación técnica',
			'formula'              => $formula,
			'n'                    => $grade[0],
			'p2o5'                 => $grade[1],
			'k2o'                  => $grade[2],
			'grade_total'          => array_sum( $grade ),
			'crop_label'           => $crop['label'],
			'stage_label'          => $stage['label'],
			'dose_per_hectare'     => null,
			'total_quantity'       => null,
			'notes'                => array_values( array_unique( $notes ) ),
			'missing_requirements' => $missing,
			'evidence_status'      => $evidence_status,
			'readiness_label'      => sprintf( '%d de %d bloques contextualizados', $ready_count, count( $evidence_status ) ),
			'confidence'           => 'low',
		);
	}

	/**
	 * Allocate integer grade points while preserving the requested sum.
	 *
	 * @param array $weights Positive weights.
	 * @param int   $total   Grade total.
	 * @return array
	 */
	private static function allocate_grade( array $weights, $total ) {
		$sum = array_sum( $weights );
		if ( $sum <= 0 ) {
			return array( 15, 15, 15 );
		}

		$base       = array();
		$fractions  = array();
		$distributed = 0;

		foreach ( $weights as $index => $weight ) {
			$exact              = ( $weight / $sum ) * $total;
			$base[ $index ]     = (int) floor( $exact );
			$fractions[ $index ] = $exact - $base[ $index ];
			$distributed       += $base[ $index ];
		}

		$remaining = $total - $distributed;
		arsort( $fractions, SORT_NUMERIC );
		$order = array_keys( $fractions );

		for ( $index = 0; $index < $remaining; $index++ ) {
			$base[ $order[ $index % count( $order ) ] ]++;
		}

		ksort( $base );
		return array_values( $base );
	}
}
