<?php
/**
 * Check Import Compatibility.
 *
 * Read-only pre-flight over a packaged copy: reports whether this site can
 * render everything in it, without writing anything and without consuming the
 * single-use transfer token.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Transfer;

use PremiumAddons\Admin\Includes\Admin_Helper;
use PremiumAddons\Includes\Abilities\Helpers;
use PremiumAddons\Includes\Helper_Functions;

use PremiumAddons\Includes\Abilities\Contracts\Ability_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Ability handler.
 */
class Check_Import_Compatibility implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'check-import-compatibility';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Check Copy Compatibility', 'premium-addons-for-elementor' ),
			'description'         => __( 'Checks whether this site can render elements copied from another site, before importing them.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-copy-paste',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'transfer_url' ),
				'properties'           => array(
					'transfer_url' => array(
						'type'        => 'string',
						'description' => __( 'The transfer URL returned by premium-addons/export-elements on the source site. Checking does not use the URL up — the same URL still works for premium-addons/import-elements afterwards.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'What this site can and cannot render from the copied content.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'compatible'      => array(
						'type'        => 'boolean',
						'description' => __( 'True when every widget in the copied content exists and is licensed here.', 'premium-addons-for-elementor' ),
					),
					'missing_widgets' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => __( 'Widget types not installed or deactivated here. They still import, but will not render until the widget is available.', 'premium-addons-for-elementor' ),
					),
					'pro_gated'       => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => __( 'Widget types that import but stay inert until a Premium Addons Pro license is present.', 'premium-addons-for-elementor' ),
					),
					'templates'       => array(
						'type'        => 'array',
						'description' => __( 'Elementor templates the copied content renders, packaged with it. The ones missing here are created on import; the ones already here are reused untouched.', 'premium-addons-for-elementor' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'title'         => array(
									'type'        => 'string',
									'description' => __( 'The template title.', 'premium-addons-for-elementor' ),
								),
								'template_type' => array(
									'type'        => 'string',
									'description' => __( 'The Elementor template type (container, section, page, …).', 'premium-addons-for-elementor' ),
								),
								'exists_here'   => array(
									'type'        => 'boolean',
									'description' => __( 'True when a template with this title already exists on this site and will be reused instead of created.', 'premium-addons-for-elementor' ),
								),
							),
						),
					),
					'summary'         => array(
						'type'        => 'object',
						'description' => __( 'What the source site packaged.', 'premium-addons-for-elementor' ),
						'properties'  => array(
							'element_count' => array(
								'type'        => 'integer',
								'description' => __( 'Number of top-level elements in the copy.', 'premium-addons-for-elementor' ),
							),
							'widget_keys'   => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => __( 'Distinct widget types in the copy, including the ones that only appear inside a bundled template.', 'premium-addons-for-elementor' ),
							),
							'templates'     => array(
								'type'        => 'array',
								'description' => __( 'The templates the source site packaged: title, template_type and element_count for each.', 'premium-addons-for-elementor' ),
								'items'       => array( 'type' => 'object' ),
							),
						),
					),
				),
			),
			'permission_callback' => function () {
				return Admin_Helper::check_user_can( 'edit_posts' ) && Admin_Helper::check_user_can( 'upload_files' );
			},
			'meta'                => array(
				// Don't want Angie to see this.
				'show_in_rest' => false,
				'mcp'          => array( 'public' => true ),
				'annotations'  => array(
					'readonly'    => true,
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

		$error = Helpers::guard_elementor();

		if ( $error ) {
			return $error;
		}

		$transfer_url = isset( $input['transfer_url'] ) ? trim( $input['transfer_url'] ) : '';

		if ( '' === $transfer_url ) {
			return new \WP_Error(
				'premium_addons_missing_transfer_url',
				__( 'A transfer URL from premium-addons/export-elements is required.', 'premium-addons-for-elementor' )
			);
		}

		// Non-consuming fetch — the token has to survive for the import that follows.
		$payload = Helpers::fetch_transfer_payload( $transfer_url );

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$bundle      = ! empty( $payload['templates'] ) && is_array( $payload['templates'] ) ? $payload['templates'] : array();
		$widget_keys = Helpers::collect_widget_types( $payload['content'] );
		$templates   = array();

		// A widget that only appears inside a bundled template still has to render
		// here, so it counts towards compatible.
		foreach ( $bundle as $template ) {

			$widget_keys = array_merge( $widget_keys, Helpers::collect_widget_types( $template['content'] ) );

			$templates[] = array(
				'title'         => $template['title'],
				'template_type' => $template['template_type'],
				'exists_here'   => 0 < (int) Helper_Functions::get_elementor_template_id( $template['title'] ),
			);
		}

		$widget_keys  = array_values( array_unique( $widget_keys ) );
		$availability = Helpers::scan_widget_availability( $widget_keys );

		$summary = ! empty( $payload['manifest'] ) && is_array( $payload['manifest'] )
			? $payload['manifest']
			: array(
				'element_count' => count( $payload['content'] ),
				'widget_keys'   => $widget_keys,
				'templates'     => $templates,
			);

		return array(
			'compatible'      => empty( $availability['missing'] ) && empty( $availability['pro_gated'] ),
			'missing_widgets' => $availability['missing'],
			'pro_gated'       => $availability['pro_gated'],
			'templates'       => $templates,
			'summary'         => $summary,
		);
	}
}
