<?php
/**
 * Scan Usage.
 *
 * Reports which Premium Addons widgets are used on the site and how often.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Dashboard;

use PremiumAddons\Admin\Includes\Admin_Helper;
use PremiumAddons\Includes\Abilities\Contracts\Ability_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Scan Usage ability handler.
 */
class Scan_Usage implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'scan-usage';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Scan Premium Addons Widget Usage', 'premium-addons-for-elementor' ),
			'description'         => __( 'Shows which Premium Addons widgets are used on the site and how often.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-dashboard',
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => (object) array(),
				'additionalProperties' => false,
				'properties'           => array(
					'rescan' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'When true, walks every Elementor page to rebuild the usage data before reading it. Slow on large sites. A rescan happens automatically when no usage data exists yet.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'Premium Addons widget usage across the site.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'used'    => array(
						'type'                 => 'object',
						'description'          => __( 'Map of Premium Addons widget key to the number of times it is used site-wide. Empty when no Premium Addons widgets are in use or the Usage module is unavailable.', 'premium-addons-for-elementor' ),
						'additionalProperties' => array(
							'type' => 'integer',
						),
					),
					'scanned' => array(
						'type'        => 'boolean',
						'description' => __( 'True when this call rebuilt the usage data instead of reading what was already stored.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'permission_callback' => function () {
				return Admin_Helper::check_user_can( 'manage_options' );
			},
			'meta'                => array(
				// Don't want Angie to see this.
				'show_in_rest' => false,
				'mcp'          => array( 'public' => true ),
				'annotations'  => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		);
	}

	/**
	 * Execute the ability.
	 *
	 * @param array|null $input Ability input.
	 * @return array|\WP_Error
	 */
	public function execute( $input = null ) {

		$rescan = ( is_array( $input ) && ! empty( $input['rescan'] ) ) || ! Admin_Helper::has_usage_data();

		if ( $rescan ) {
			$this->rebuild_usage_data();
		}

		return array(
			'used'    => Admin_Helper::get_used_widgets(),
			'scanned' => $rescan,
		);
	}

	/**
	 * Walk every Elementor page in batches until the usage data is rebuilt.
	 *
	 * The dashboard spreads these across requests; an ability has no client to drive
	 * that loop, so they run back to back.
	 *
	 * @return void
	 */
	private function rebuild_usage_data() {

		$offset = 0;

		do {
			$progress = Admin_Helper::scan_widgets_usage( $offset );

			if ( ! $progress || $progress['processed'] === $offset ) {
				return;
			}

			$offset = $progress['processed'];

		} while ( ! $progress['done'] );
	}
}
