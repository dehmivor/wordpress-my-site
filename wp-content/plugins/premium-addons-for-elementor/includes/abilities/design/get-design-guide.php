<?php
/**
 * Get Design Guide.
 *
 * Returns the bundled design guide as a tool result, for clients that cannot
 * reach the MCP prompt.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Design;

use PremiumAddons\Admin\Includes\Admin_Helper;

use PremiumAddons\Includes\Abilities\Contracts\Ability_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Ability handler.
 */
class Get_Design_Guide implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'get-design-guide';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Get Design Guide', 'premium-addons-for-elementor' ),
			'description'         => __( 'Returns the Premium Addons design skill and its design references, in Markdown. Defaults to the build workflow; pass part to request any of workflow, design-guide, premium-templates, widget-selection, global-addons, page-patterns, troubleshooting.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-discovery',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'part' => array(
						'type'        => 'array',
						'uniqueItems' => true,
						'items'       => array(
							'type' => 'string',
							'enum' => array_keys( Design_Guide::PARTS ),
						),
						'description' => __( 'Which parts to return, in the order you want them. Omit for the build workflow alone. Ask for several at once — design-guide rules every design decision, premium-templates covers picking and inserting ready-made catalog sections, widget-selection maps an intent to a widget, global-addons covers effects, page-patterns covers section and page composition, troubleshooting covers connection and rendering failures.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'The requested parts of the design guide.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'parts'     => array(
						'type'                 => 'object',
						'description'          => __( 'The Markdown body of each requested part, keyed by part name, in the order requested. Read a part fully before acting on it, and follow it for the whole build.', 'premium-addons-for-elementor' ),
						'additionalProperties' => array( 'type' => 'string' ),
					),
					'available' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => __( 'Every part value this ability accepts.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'permission_callback' => function () {
				return Admin_Helper::check_user_can( 'edit_posts' );
			},
			'meta'                => array(
				'show_in_rest' => true,
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

		$available = array_keys( Design_Guide::PARTS );
		$requested = ! empty( $input['part'] ) && is_array( $input['part'] ) ? $input['part'] : array( Design_Guide::DEFAULT_PART );
		$parts     = array();

		foreach ( $requested as $part ) {

			if ( ! in_array( $part, $available, true ) ) {
				return new \WP_Error(
					'premium_addons_invalid_part',
					sprintf(
						/* translators: 1: the requested part, 2: comma-separated list of the accepted parts. */
						__( 'There is no %1$s part of the design guide. Valid parts are: %2$s.', 'premium-addons-for-elementor' ),
						$part,
						implode( ', ', $available )
					)
				);
			}

			$body = Design_Guide::get_body( $part );

			if ( false === $body ) {
				return new \WP_Error(
					'premium_addons_design_guide_unreadable',
					sprintf(
						/* translators: %s: the requested part. */
						__( 'The %s part of the design guide could not be read.', 'premium-addons-for-elementor' ),
						$part
					)
				);
			}

			$parts[ $part ] = $body;
		}

		return array(
			'parts'     => $parts,
			'available' => $available,
		);
	}
}
