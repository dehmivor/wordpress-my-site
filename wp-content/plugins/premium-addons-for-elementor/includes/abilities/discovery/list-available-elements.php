<?php
/**
 * List Available Elements.
 *
 * Lists every Elementor widget and structural element registered on the site.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Discovery;

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
class List_Available_Elements implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'list-available-elements';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'List Available Elements', 'premium-addons-for-elementor' ),
			'description'         => __( 'Lists every Elementor widget and structural element registered on the site, with the type names other abilities expect.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-discovery',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'type'   => array(
						'type'        => 'string',
						'enum'        => array( 'widget', 'element', 'all' ),
						'default'     => 'all',
						'description' => __( 'widget for widgets, element for structural types (container, e-flexbox, section, column), all for both. Defaults to all.', 'premium-addons-for-elementor' ),
					),
					'source' => array(
						'type'        => 'string',
						'enum'        => array( 'all', 'premium-addons' ),
						'default'     => 'all',
						'description' => __( 'all for every registered type, premium-addons to return only Premium Addons widgets (structural elements are never Premium Addons, so the elements list is empty in that case). Defaults to all.', 'premium-addons-for-elementor' ),
					),
					'search' => array(
						'type'        => 'string',
						'description' => __( 'Optional case-insensitive substring; matches a type name or its title. Omit to return everything.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'The registered widgets and structural elements matching the filters.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'widgets'  => array(
						'type'        => 'array',
						'description' => __( 'Registered widgets. Empty when type is element.', 'premium-addons-for-elementor' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'name'              => array(
									'type'        => 'string',
									'description' => __( 'The Elementor type name to pass to get-widget-schema and insert-widget.', 'premium-addons-for-elementor' ),
								),
								'title'             => array(
									'type'        => 'string',
									'description' => __( 'The human-readable title shown in the editor panel; map a user phrase to this to find the name.', 'premium-addons-for-elementor' ),
								),
								'is_atomic'         => array(
									'type'        => 'boolean',
									'description' => __( 'True for Elementor v4 atomic widgets.', 'premium-addons-for-elementor' ),
								),
								'is_premium_addons' => array(
									'type'        => 'boolean',
									'description' => __( 'True when the widget is owned by Premium Addons.', 'premium-addons-for-elementor' ),
								),
								'insertable'        => array(
									'type'        => 'boolean',
									'description' => __( 'True when insert-widget accepts this type. False for atomic (v4) widgets, and for locked third-party widgets while Premium Addons Pro is inactive.', 'premium-addons-for-elementor' ),
								),
								'available'         => array(
									'type'        => 'boolean',
									'description' => __( 'True when this install may use the widget through these abilities. False for a third-party widget while Premium Addons Pro is inactive.', 'premium-addons-for-elementor' ),
								),
								'upgrade_link'      => array(
									'type'        => array( 'string', 'null' ),
									'description' => __( 'The Premium Addons Pro upgrade URL when the widget is locked, or null when available.', 'premium-addons-for-elementor' ),
								),
							),
						),
					),
					'elements' => array(
						'type'        => 'array',
						'description' => __( 'Registered structural elements. Empty when type is widget or source is premium-addons.', 'premium-addons-for-elementor' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'name'  => array(
									'type'        => 'string',
									'description' => __( 'The structural element type name (container, e-flexbox, section, column).', 'premium-addons-for-elementor' ),
								),
								'title' => array(
									'type'        => 'string',
									'description' => __( 'The human-readable title of the structural element.', 'premium-addons-for-elementor' ),
								),
							),
						),
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

		$error = Helpers::guard_elementor();

		if ( $error ) {
			return $error;
		}

		$type   = isset( $input['type'] ) && in_array( $input['type'], array( 'widget', 'element', 'all' ), true ) ? $input['type'] : 'all';
		$source = isset( $input['source'] ) && 'premium-addons' === $input['source'] ? 'premium-addons' : 'all';
		$search = isset( $input['search'] ) ? trim( (string) $input['search'] ) : '';

		// Substring match on a type's name or title. '' matches everything.
		$matches = function ( $name, $title ) use ( $search ) {
			if ( '' === $search ) {
				return true;
			}
			return false !== stripos( $name, $search ) || false !== stripos( $title, $search );
		};

		$widgets  = array();
		$elements = array();

		if ( 'element' !== $type ) {

			// No-arg call returns the whole widget registry and primes it — on a
			// REST/MCP request elementor/widgets/register may not have fired yet.
			$widget_types = \Elementor\Plugin::$instance->widgets_manager->get_widget_types();

			// Premium Addons ownership is keyed by the registered type name, the same
			// value elements.php stores in each element's `name`.
			$pa_names = array_flip( Admin_Helper::get_pa_elements_names() );

			$allow_third_party = Helper_Functions::check_papro_version();

			foreach ( $widget_types as $name => $type_object ) {

				$title = $type_object->get_title();

				if ( ! $matches( $name, $title ) ) {
					continue;
				}

				$is_premium_addons = isset( $pa_names[ $name ] );

				if ( 'premium-addons' === $source && ! $is_premium_addons ) {
					continue;
				}

				// Third-party widgets are locked (not insertable) unless PAPRO is active.
				$locked = ! $allow_third_party && ! Helpers::is_core_or_pa_widget( $type_object );

				$is_atomic = class_exists( '\Elementor\Modules\AtomicWidgets\Utils\Utils' )
					&& \Elementor\Modules\AtomicWidgets\Utils\Utils::is_atomic( $type_object );

				$widgets[] = array(
					'name'              => $name,
					'title'             => $title,
					'is_atomic'         => $is_atomic,
					'is_premium_addons' => $is_premium_addons,
					'insertable'        => ! $is_atomic && ! $locked,
					'available'         => ! $locked,
					'upgrade_link'      => $locked ? Helper_Functions::get_campaign_link( 'https://premiumaddons.com/pro/#get-pa-pro', 'ai-abilities', 'mcp', 'get-pro' ) : null,
				);
			}
		}

		// Structural elements are never Premium Addons owned, so source=premium-addons
		// yields an empty elements list.
		if ( 'widget' !== $type && 'premium-addons' !== $source ) {

			$element_types = \Elementor\Plugin::$instance->elements_manager->get_element_types();

			foreach ( $element_types as $name => $type_object ) {

				$title = $type_object->get_title();

				if ( ! $matches( $name, $title ) ) {
					continue;
				}

				$elements[] = array(
					'name'  => $name,
					'title' => $title,
				);
			}
		}

		return array(
			'widgets'  => $widgets,
			'elements' => $elements,
		);
	}
}
