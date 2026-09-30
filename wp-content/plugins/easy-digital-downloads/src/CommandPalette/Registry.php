<?php
/**
 * Command Palette source registry.
 *
 * Registers command palette search sources on top of the WordPress Abilities
 * API (WP 6.9+). Each source is registered as a read-only ability, so it is
 * discoverable by the command palette, the REST API, and any other Abilities
 * API consumer (MCP, the AI client, etc.).
 *
 * @package     EDD\CommandPalette
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\CommandPalette;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Registry class.
 *
 * @since 3.7.1
 */
class Registry {

	/**
	 * The ability category EDD's sources are registered under.
	 *
	 * @since 3.7.1
	 * @var string
	 */
	const CATEGORY = 'edd-commerce';

	/**
	 * The ability meta key marking an ability as a command palette source.
	 *
	 * @since 3.7.1
	 * @var string
	 */
	const META_SOURCE = 'edd_command_palette';

	/**
	 * The ability meta key marking a source as a strong match for numeric searches.
	 *
	 * @since 3.7.1
	 * @var string
	 */
	const META_NUMERIC = 'edd_command_palette_numeric';

	/**
	 * Whether the Abilities API needed to register sources is available.
	 *
	 * @since 3.7.1
	 *
	 * @return bool
	 */
	public static function is_supported() {
		foreach ( array( 'wp_register_ability', 'wp_get_abilities', 'wp_has_ability', 'wp_get_ability' ) as $function ) {
			if ( ! function_exists( $function ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Register a command palette search source.
	 *
	 * Call this on the `edd/command_palette/register_sources` action, which only
	 * fires when the Abilities API is available.
	 *
	 * @since 3.7.1
	 *
	 * @param string $name The namespaced ability name (e.g. 'edd/search-orders').
	 * @param array  $args {
	 *     Source arguments.
	 *
	 *     @type string   $label           Required. Human-readable label for the source, shown as a
	 *                                     search hint on each of its results.
	 *     @type string   $capability      Required. Capability required to search this source.
	 *     @type callable $search_callback Required. Receives the search term and returns an array
	 *                                     of results, each an array with 'label' and 'url' keys.
	 *     @type string   $description     Optional. Description of the ability for the Abilities API.
	 *     @type string   $category        Optional. Ability category slug. Defaults to 'edd-commerce'.
	 *     @type bool     $numeric         Optional. Whether this source is a strong match for numeric
	 *                                     searches (e.g. orders). Numeric sources are surfaced first
	 *                                     for numeric queries and last otherwise. Default false.
	 * }
	 * @return \WP_Ability|null The registered ability, or null on failure / unsupported WordPress.
	 */
	public static function register( $name, $args = array() ) {
		if ( ! self::is_supported() ) {
			return null;
		}

		// Nothing stops `wp_abilities_api_init` firing more than once in a
		// request, and the WordPress registry treats a repeat name as an error.
		if ( wp_has_ability( $name ) ) {
			return wp_get_ability( $name );
		}

		$missing = array_values(
			array_filter(
				array( 'label', 'capability', 'search_callback' ),
				function ( $key ) use ( $args ) {
					return empty( $args[ $key ] );
				}
			)
		);

		if ( ! empty( $missing ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					'Command palette source "%1$s" is missing required arguments: %2$s.',
					$name,
					implode( ', ', $missing )
				),
				'EDD 3.7.1'
			);

			return null;
		}

		$capability      = $args['capability'];
		$search_callback = $args['search_callback'];

		return wp_register_ability(
			$name,
			array(
				'label'               => $args['label'],
				'description'         => ! empty( $args['description'] )
					? $args['description']
					/* translators: %s: the source label, e.g. Orders */
					: sprintf( __( 'Search EDD %s and jump to a matching record.', 'easy-digital-downloads' ), $args['label'] ),
				'category'            => ! empty( $args['category'] ) ? $args['category'] : self::CATEGORY,
				'input_schema'        => array(
					'type'        => 'string',
					'description' => __( 'The search term.', 'easy-digital-downloads' ),
					'required'    => true,
				),
				'output_schema'       => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'label' => array( 'type' => 'string' ),
							'url'   => array( 'type' => 'string' ),
						),
					),
				),
				'execute_callback'    => function ( $search = '' ) use ( $search_callback ) {
					$results = $search_callback( (string) $search );

					return is_array( $results ) ? array_values( $results ) : array();
				},
				'permission_callback' => function () use ( $capability ) {
					return current_user_can( $capability );
				},
				'meta'                => array(
					'annotations'      => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest'     => true,
					self::META_SOURCE  => true,
					self::META_NUMERIC => ! empty( $args['numeric'] ),
				),
			)
		);
	}

	/**
	 * Get every registered command palette search source.
	 *
	 * Accessing the abilities registry triggers the `wp_abilities_api_init`
	 * action, so all sources (core and add-on) are registered on first call.
	 *
	 * @since 3.7.1
	 *
	 * @return \WP_Ability[] Ability instances keyed by ability name.
	 */
	public static function get_sources() {
		if ( ! self::is_supported() ) {
			return array();
		}

		return array_filter(
			wp_get_abilities(),
			function ( $ability ) {
				return (bool) $ability->get_meta_item( self::META_SOURCE );
			}
		);
	}
}
