<?php
/**
 * Get Page Structure.
 *
 * Shows the structure of an Elementor page, post or template.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Discovery;

use PremiumAddons\Includes\Abilities\Helpers;

use PremiumAddons\Includes\Abilities\Contracts\Ability_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Ability handler.
 */
class Get_Page_Structure implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'get-page-structure';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Get Page Structure', 'premium-addons-for-elementor' ),
			'description'         => __( 'Shows the structure of an Elementor page, post or template.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-discovery',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'post_id' ),
				'properties'           => array(
					'post_id'          => array(
						'type'        => 'integer',
						'description' => __( 'The ID of the page, post or template whose structure to read.', 'premium-addons-for-elementor' ),
					),
					'include_settings' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Return the full settings of every element instead of the short summary. Defaults to false — the full tree can be very large.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'The Elementor document and its element tree.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'post_id'   => array(
						'type'        => 'integer',
						'description' => __( 'The ID of the document.', 'premium-addons-for-elementor' ),
					),
					'title'     => array(
						'type'        => 'string',
						'description' => __( 'The document title.', 'premium-addons-for-elementor' ),
					),
					'type'      => array(
						'type'        => 'string',
						'description' => __( 'The Elementor document type from the _elementor_template_type meta (e.g. wp-page, wp-post, page, section, container), falling back to the post type.', 'premium-addons-for-elementor' ),
					),
					'structure' => array(
						'type'        => 'array',
						'description' => __( 'The nested element tree. Each node is { id, elType, widgetType?, settings_summary | settings, elements? } where elements holds the node children recursively.', 'premium-addons-for-elementor' ),
						'items'       => array(
							'type'                 => 'object',
							'additionalProperties' => true,
						),
					),
				),
			),
			'permission_callback' => function ( $input = null ) {
					return Helpers::can_edit_input_post( $input );
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

		$error = Helpers::guard_elementor();

		if ( $error ) {
			return $error;
		}

		$post_id = ! empty( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;

		if ( ! $post_id ) {
			return new \WP_Error(
				'premium_addons_missing_post_id',
				__( 'A post_id is required to read a page structure.', 'premium-addons-for-elementor' )
			);
		}

		$resolved = Helpers::resolve_document( $post_id );

		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

			list( , $elements ) = $resolved;

		$include_settings = ! empty( $input['include_settings'] );

		$summarize_settings = function ( $settings ) {

			foreach ( $settings as $value ) {
				if ( is_string( $value ) ) {
					$value = trim( wp_strip_all_tags( $value ) );

					if ( strlen( $value ) > 3 ) {
						return mb_substr( $value, 0, 80 );
					}
				}
			}

			return '';
		};

		$build_tree = function ( $elements ) use ( &$build_tree, $include_settings, $summarize_settings ) {

			$tree = array();

			foreach ( $elements as $element ) {

				if ( ! is_array( $element ) ) {
					continue;
				}

				$node = array(
					'id'     => isset( $element['id'] ) ? $element['id'] : '',
					'elType' => isset( $element['elType'] ) ? $element['elType'] : '',
				);

				if ( ! empty( $element['widgetType'] ) ) {
					$node['widgetType'] = $element['widgetType'];
				}

				$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();

				if ( $include_settings ) {
					$node['settings'] = $settings;
				} else {
					$node['settings_summary'] = $summarize_settings( $settings );
				}

				if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
					$node['elements'] = $build_tree( $element['elements'] );
				}

				$tree[] = $node;
			}

			return $tree;
		};

		$type = get_post_meta( $post_id, '_elementor_template_type', true );

		return array(
			'post_id'   => $post_id,
			'title'     => get_the_title( $post_id ),
			'type'      => $type ? $type : get_post_type( $post_id ),
			'structure' => $build_tree( $elements ),
		);
	}
}
