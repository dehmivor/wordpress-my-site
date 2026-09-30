<?php
/**
 * Get Addon Schema.
 *
 * The complement of premium-addons/get-widget-schema: same control stack, same
 * per-control mapper, inverted filter — it returns ONLY the Premium Addons
 * global-addon sections (Floating Effects, Tooltips, Display Conditions, …) that
 * get-widget-schema strips out. Lists the addons available on a host element, or
 * returns the settings schema of one, so agents learn the addon control keys
 * before writing them.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Discovery;

use PremiumAddons\Admin\Includes\Admin_Helper;
use PremiumAddons\Includes\Abilities\Helpers;

use PremiumAddons\Includes\Abilities\Contracts\Ability_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Ability handler.
 */
class Get_Addon_Schema implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'get-addon-schema';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Get Addon Schema', 'premium-addons-for-elementor' ),
			'description'         => __( 'Lists the Premium Addons global addons available on an element, or returns the settings schema of one.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-discovery',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'addon'   => array(
						'type'        => 'string',
						'description' => __( 'The addon section id to schema (e.g. section_premium_fe, section_pa_display_conditions). Omit to list every addon available on the host instead.', 'premium-addons-for-elementor' ),
					),
					'element' => array(
						'type'        => 'string',
						'default'     => 'container',
						'description' => __( 'The host element or widget type the addon is attached to (e.g. container, heading, premium-carousel). Addons hook different hosts, so the same addon can be present on one host and absent on another. Defaults to container.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'description'          => __( 'In list mode (no addon): the host element and its available addons. In schema mode (addon given): the addon and its settings schema.', 'premium-addons-for-elementor' ),
				'properties'           => array(
					'element' => array(
						'type'        => 'string',
						'description' => __( 'The host element or widget type the schema was read from.', 'premium-addons-for-elementor' ),
					),
					'addons'  => array(
						'type'        => 'array',
						'description' => __( 'List mode only: the addons available on the host, each { id, title }. The id is what to pass back as the addon input to fetch its schema.', 'premium-addons-for-elementor' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'id'    => array( 'type' => 'string' ),
								'title' => array( 'type' => 'string' ),
							),
						),
					),
					'addon'   => array(
						'type'        => 'string',
						'description' => __( 'Schema mode only: the addon section id that was introspected.', 'premium-addons-for-elementor' ),
					),
					'title'   => array(
						'type'        => 'string',
						'description' => __( 'Schema mode only: the human-readable title of the addon.', 'premium-addons-for-elementor' ),
					),
					'schema'  => array(
						'type'                 => 'object',
						'additionalProperties' => true,
						'description'          => __( 'Schema mode only: a JSON Schema object whose properties map each addon control key to its type, and — where relevant — enum, default and depends_on (the control\'s visibility prerequisite).', 'premium-addons-for-elementor' ),
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

		$name = isset( $input['element'] ) ? trim( (string) $input['element'] ) : '';

		if ( '' === $name ) {
			$name = 'container';
		}

		// Force widget registration — on a REST/MCP request elementor/widgets/register
		// may not have fired yet, so an un-primed widget lookup would miss.
		\Elementor\Plugin::$instance->widgets_manager->get_widget_types();

		// Resolve structural elements first, then widgets — same order as
		// premium-addons/check-elementor-element and get-widget-schema.
		$type_object    = \Elementor\Plugin::$instance->elements_manager->get_element_types( $name );
		$is_widget_host = false;

		if ( ! $type_object ) {
			$type_object    = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $name );
			$is_widget_host = (bool) $type_object;
		}

		if ( ! $type_object ) {
			return new \WP_Error(
				'premium_addons_invalid_element',
				/* translators: %s: element or widget type name. */
				sprintf( __( 'No element or widget type %s is registered on this site. A Premium Addons widget disabled in the dashboard is not registered.', 'premium-addons-for-elementor' ), $name )
			);
		}

		// A third-party widget host is unavailable while Premium Addons Pro is inactive.
		if ( $is_widget_host ) {

			$source_error = Helpers::guard_widget_source( $type_object, $name );

			if ( $source_error ) {
				return $source_error;
			}
		}

		// Atomic (v4) hosts carry no classic control stack, and the global addons
		// are not atomic-compatible — reject rather than return an empty list.
		$is_atomic = class_exists( '\Elementor\Modules\AtomicWidgets\Utils\Utils' )
			&& \Elementor\Modules\AtomicWidgets\Utils\Utils::is_atomic( $type_object );

		if ( $is_atomic ) {
			return new \WP_Error(
				'premium_addons_atomic_host_unsupported',
				/* translators: %s: element or widget type name. */
				sprintf( __( 'The type %s is an Elementor v4 atomic type; the Premium Addons global addons are not available on atomic hosts.', 'premium-addons-for-elementor' ), $name )
			);
		}

		$controls = $type_object->get_controls();

		// The addon sections carry the shared pa-extension-icon marker — the exact
		// set get-widget-schema strips out. This ability keeps only those.
		$pa_sections = Helpers::global_addon_section_ids( $controls );

		// Group the value-carrying controls under their addon section.
		$section_props = array();

		foreach ( $controls as $key => $control ) {

			// Internal controls (_-prefixed row/section keys).
			if ( 0 === strpos( $key, '_' ) ) {
				continue;
			}

			$control_section = isset( $control['section'] ) ? $control['section'] : '';

			if ( ! isset( $pa_sections[ $control_section ] ) ) {
				continue;
			}

			$mapped = Helpers::control_to_schema( $control );

			if ( null === $mapped ) {
				continue;
			}

			if ( ! isset( $section_props[ $control_section ] ) ) {
				$section_props[ $control_section ] = array();
			}

			$section_props[ $control_section ][ $key ] = $mapped;
		}

		// The addon title lives on its section-opener control label, wrapped in the
		// pa-extension-icon markup — strip the tags to the plain title.
		$title_of = function ( $section_id ) use ( $controls ) {
			$label = isset( $controls[ $section_id ]['label'] ) ? $controls[ $section_id ]['label'] : '';
			return trim( wp_strip_all_tags( $label ) );
		};

		$addon = isset( $input['addon'] ) ? trim( (string) $input['addon'] ) : '';

		// List mode — every addon actually available (with settable controls) on this host.
		if ( '' === $addon ) {

			$addons = array();

			foreach ( array_keys( $section_props ) as $section_id ) {
				$addons[] = array(
					'id'    => $section_id,
					'title' => $title_of( $section_id ),
				);
			}

			return array(
				'element' => $name,
				'addons'  => $addons,
			);
		}

		// Schema mode — a section absent from $section_props is either not injected
		// on this host or a marker-only upsell section; both are truthfully "not
		// available on this element".
		if ( ! isset( $section_props[ $addon ] ) ) {
			return new \WP_Error(
				'premium_addons_invalid_addon',
				/* translators: 1: addon section id, 2: host element type name. */
				sprintf( __( 'The addon %1$s is not available on the %2$s element. Call this ability without an addon to list the addons available on the host.', 'premium-addons-for-elementor' ), $addon, $name )
			);
		}

		return array(
			'addon'   => $addon,
			'title'   => $title_of( $addon ),
			'element' => $name,
			'schema'  => array(
				'type'       => 'object',
				'properties' => (object) $section_props[ $addon ],
			),
		);
	}
}
