<?php
/**
 * Date Condition Handler.
 */

namespace PremiumAddons\Includes\PA_Display_Conditions\Conditions;

// Elementor Classes.
use Elementor\Controls_Manager;

// PA Classes.
use PremiumAddons\Includes\Helper_Functions;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Date.
 */
class Date extends Condition {

	/**
	 * Get Controls Options.
	 *
	 * @access public
	 * @since 4.7.0
	 *
	 * @return array|void  controls options
	 */
	public function get_control_options() {

		return array(
			'label'          => __( 'Value', 'premium-addons-for-elementor' ),
			'type'           => Controls_Manager::DATE_TIME,
			'default'        => gmdate( 'Y/m/d' ),
			'label_block'    => true,
			'picker_options' => array(
				'format'     => 'd-m-Y',
				'enableTime' => false,
			),
			'condition'      => array(
				'pa_condition_key' => 'date',
			),
		);
	}

	/**
	 * Compare Condition Value.
	 *
	 * @access public
	 * @since 4.7.0
	 *
	 * @param array       $settings       element settings.
	 * @param string      $operator       condition operator.
	 * @param string      $value          condition value.
	 * @param string      $compare_val    comparison mode: default, after or before.
	 * @param string|bool $tz        time zone.
	 *
	 * @return bool|void
	 */
	public function compare_value( $settings, $operator, $value, $compare_val, $tz ) {

		$value = strtotime( $value );

		$today = 'local' === $tz ? strtotime( Helper_Functions::get_local_time( 'd-m-Y' ) ) : strtotime( Helper_Functions::get_site_server_time( 'd-m-Y' ) );

		// The control is hidden for the Is Not operator, and Elementor nulls hidden
		// controls while reading the settings, so the comparison falls back to the
		// exact match on its own. Anything unrecognised degrades the same way.
		$compare = isset( $compare_val ) && '' !== $compare_val ? $compare_val : 'default';

		if ( empty( $value ) ) {
			$condition_result = false;
		} elseif ( 'after' === $compare ) {
			$condition_result = $today > $value;
		} elseif ( 'before' === $compare ) {
			$condition_result = $today < $value;
		} else {
			$condition_result = $today === $value;
		}

		return Helper_Functions::get_final_result( $condition_result, $operator );
	}
}
