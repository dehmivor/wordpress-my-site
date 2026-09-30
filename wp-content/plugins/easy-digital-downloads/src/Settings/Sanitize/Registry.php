<?php
/**
 * Indexes the registered settings for the sanitizer and describes the shape each setting stores.
 *
 * @package     EDD\Settings\Sanitize
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Settings\Sanitize;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Utils\Convert;

/**
 * Indexes the registered settings for the sanitizer and describes the shape each setting stores.
 *
 * @since 3.7.1
 */
class Registry {

	/**
	 * Types stored exactly as given: stripping a character from a credential breaks the integration silently.
	 *
	 * @since 3.7.1
	 * @var string[]
	 */
	const VERBATIM_TYPES = array( 'password' );

	/**
	 * Types core renders no input for, so nothing legitimate is posted under them.
	 *
	 * Named rather than read from edd_get_non_setting_types(): a type an extension adds
	 * through that filter draws its own field and keeps the value it posts.
	 *
	 * @since 3.7.1
	 * @var string[]
	 */
	const NO_INPUT_TYPES = array( 'header', 'descriptive_text' );

	/**
	 * Types whose value is a list of keys rather than a single value.
	 *
	 * @since 3.7.1
	 * @var string[]
	 */
	const ARRAY_TYPES = array( 'multicheck', 'gateways', 'payment_icons' );

	/**
	 * Types stored either as a single value or as one entry per line.
	 *
	 * @since 3.7.1
	 * @var string[]
	 */
	const EITHER_SHAPE_TYPES = array( 'textarea' );

	/**
	 * Types core renders with a single input, so a save of that field carries one value.
	 *
	 * A type core does not render is left off this list: the shape belongs to whichever
	 * callback draws the field.
	 *
	 * @since 3.7.1
	 * @var string[]
	 */
	const SCALAR_TYPES = array(
		'amounttype',
		'checkbox',
		'checkbox_description',
		'checkbox_toggle',
		'color',
		'color_select',
		'email',
		'gateway_select',
		'number',
		'password',
		'radio',
		'rich_editor',
		'select',
		'shop_states',
		'sort_order',
		'text',
		'upload',
	);

	/**
	 * The index, built once per request.
	 *
	 * @since 3.7.1
	 * @var array|null
	 */
	private static $index = null;

	/**
	 * Gets the index of the registered settings.
	 *
	 * @since 3.7.1
	 *
	 * @return array The index described by build().
	 */
	public static function get_index() {
		if ( ! is_null( self::$index ) ) {
			return self::$index;
		}

		$registered = (array) edd_get_registered_settings();
		$index      = self::build( $registered );

		// Settings register on admin_init, so an earlier call must not fix an empty index in place.
		if ( ! empty( $registered ) ) {
			self::$index = $index;
		}

		return $index;
	}

	/**
	 * Builds the index from a set of registered settings.
	 *
	 * @since 3.7.1
	 *
	 * @param array $registered The registered settings, keyed by tab and then section.
	 * @return array {
	 *     @type array    $types       Setting id => declared type.
	 *     @type array    $names       Setting id => the label the field is rendered with.
	 *     @type array    $shapes      Setting id => 'array', 'scalar' or 'either'.
	 *     @type array    $sanitizers  Setting id => the Sanitize\Tabs section class that declares sanitize_{$id}().
	 *     @type array    $allow_html  Setting id => true for a setting registered as carrying markup.
	 *     @type string[] $sort_orders The `{$id}_order` keys a sortable setting posts alongside itself.
	 * }
	 */
	public static function build( array $registered ) {
		$index = array(
			'types'       => array(),
			'names'       => array(),
			'shapes'      => array(),
			'sanitizers'  => array(),
			'allow_html'  => array(),
			'sort_orders' => array( 'gateways_order', 'payment_icons_order' ),
		);

		foreach ( $index['sort_orders'] as $sort_order ) {
			$index['shapes'][ $sort_order ] = 'scalar';
		}

		foreach ( $registered as $tab_id => $sections ) {
			foreach ( (array) $sections as $section_id => $settings ) {
				// A setting registered at the tab level belongs to the main section.
				if ( is_array( $settings ) && array_key_exists( 'type', $settings ) ) {
					$settings   = array( $settings );
					$section_id = 'main';
				}

				$section_class     = self::get_section_class( $tab_id, $section_id );
				$section_sanitizes = class_exists( $section_class );

				foreach ( (array) $settings as $registered_as => $setting ) {
					if ( empty( $setting['id'] ) ) {
						continue;
					}

					$id = $setting['id'];

					if ( ! empty( $setting['type'] ) ) {
						$index['types'][ $id ] = $setting['type'];
					}

					$index['shapes'][ $id ] = self::resolve_shape( $setting );

					if ( ! empty( $setting['sortable'] ) ) {
						$sort_order                     = $id . '_order';
						$index['sort_orders'][]         = $sort_order;
						$index['shapes'][ $sort_order ] = 'scalar';
					}

					foreach ( self::get_index_keys( $registered_as, $id ) as $key ) {
						if ( ! empty( $setting['name'] ) ) {
							$index['names'][ $key ] = $setting['name'];
						}

						if ( ! empty( $setting['allow_html'] ) ) {
							$index['allow_html'][ $key ] = true;
						}

						if ( $section_sanitizes && method_exists( $section_class, 'sanitize_' . $key ) ) {
							$index['sanitizers'][ $key ] = $section_class;
						}
					}
				}
			}
		}

		return $index;
	}

	/**
	 * Gets the class that sanitizes a settings tab, or one of its sections.
	 *
	 * @since 3.7.1
	 *
	 * @param string $tab     The tab id.
	 * @param string $section The section id, or an empty string for the tab itself.
	 * @return string The class name, whether or not the class exists.
	 */
	public static function get_section_class( $tab, $section = '' ) {
		$class = 'EDD\\Admin\\Settings\\Sanitize\\Tabs\\' . Convert::snake_to_camel( $tab );

		return empty( $section ) ? $class : $class . '\\' . Convert::snake_to_camel( $section );
	}

	/**
	 * Gets the class that sanitizes a setting type.
	 *
	 * @since 3.7.1
	 *
	 * @param string $type The declared setting type.
	 * @return string The class name, whether or not the class exists.
	 */
	public static function get_type_class( $type ) {
		return 'EDD\\Settings\\Sanitize\\Types\\' . Convert::snake_to_camel( $type );
	}

	/**
	 * Gets the type a setting is declared with, wherever it is declared.
	 *
	 * @since 3.7.1
	 *
	 * @param string $key The setting id.
	 * @return string The declared type, 'sort_order' for the order a sortable setting posts, or an empty string for a key the registry does not declare.
	 */
	public static function get_type( $key ) {
		$index = self::get_index();

		// A sortable setting posts its order under its own key, which nothing registers.
		if ( in_array( $key, $index['sort_orders'], true ) ) {
			return 'sort_order';
		}

		return isset( $index['types'][ $key ] ) ? $index['types'][ $key ] : '';
	}

	/**
	 * Gets the label a setting's field is rendered with.
	 *
	 * @since 3.7.1
	 *
	 * @param string $key The setting id.
	 * @return string The label, or an empty string for a key the registry carries no label for.
	 */
	public static function get_name( $key ) {
		$index = self::get_index();

		return isset( $index['names'][ $key ] ) ? $index['names'][ $key ] : '';
	}

	/**
	 * Whether a setting is registered as carrying the markup a post may carry.
	 *
	 * @since 3.7.1
	 *
	 * @param string $key The setting id.
	 * @return bool
	 */
	public static function allows_html( $key ) {
		$index = self::get_index();

		return ! empty( $index['allow_html'][ $key ] );
	}

	/**
	 * Whether a value has the shape the setting stores.
	 *
	 * A setting whose shape core cannot judge accepts both, since the callback that
	 * draws the field is the only thing that knows what it posts.
	 *
	 * @since 3.7.1
	 *
	 * @param string $key   The setting id.
	 * @param mixed  $value The submitted value.
	 * @return bool
	 */
	public static function has_expected_shape( $key, $value ) {
		$shape = self::get_shape( $key );

		if ( 'either' === $shape ) {
			return true;
		}

		if ( 'array' === $shape ) {
			return is_array( $value );
		}

		return ! is_array( $value );
	}

	/**
	 * Whether a value is the marker the form sends to say a list has nothing checked.
	 *
	 * @since 3.7.1
	 *
	 * @param string $key   The setting id.
	 * @param mixed  $value The submitted value.
	 * @return bool
	 */
	public static function is_empty_list( $key, $value ) {
		if ( 'array' !== self::get_shape( $key ) ) {
			return false;
		}

		return '' === $value || '-1' === $value;
	}

	/**
	 * Gets the value a setting keeps when what was submitted for it is not usable.
	 *
	 * @since 3.7.1
	 *
	 * @global array $edd_options Array of all the EDD Options.
	 *
	 * @param string $key The setting id.
	 * @return mixed What the store holds for the setting, or the empty value of its shape.
	 */
	public static function kept_value( $key ) {
		global $edd_options;

		// A stored value of the wrong shape is no more usable than the submitted one.
		if ( is_array( $edd_options ) && array_key_exists( $key, $edd_options ) && self::has_expected_shape( $key, $edd_options[ $key ] ) ) {
			return $edd_options[ $key ];
		}

		return self::empty_value( $key );
	}

	/**
	 * Gets the shape a setting stores.
	 *
	 * Public because Refusal names the shape in the line it logs.
	 *
	 * @since 3.7.1
	 *
	 * @param string $key The setting id.
	 * @return string 'array', 'scalar', or 'either' for a key the index does not carry.
	 */
	public static function get_shape( $key ) {
		$index = self::get_index();

		return isset( $index['shapes'][ $key ] ) ? $index['shapes'][ $key ] : 'either';
	}

	/**
	 * Gets the empty value of a setting's shape.
	 *
	 * @since 3.7.1
	 *
	 * @param string $key The setting id.
	 * @return array|string
	 */
	private static function empty_value( $key ) {
		return 'array' === self::get_shape( $key ) ? array() : '';
	}

	/**
	 * Gets the keys a setting's per-key rules are indexed under.
	 *
	 * A field can be registered under an array key that is not the setting id, and it is that
	 * key a save posts under, so a rule naming a key has to be reachable under both.
	 *
	 * @since 3.7.1
	 *
	 * @param string|int $registered_as The array key the setting is registered under.
	 * @param string     $id            The setting id.
	 * @return string[]
	 */
	private static function get_index_keys( $registered_as, $id ) {
		if ( ! is_string( $registered_as ) || $registered_as === $id ) {
			return array( $id );
		}

		return array( $id, $registered_as );
	}

	/**
	 * Resolves the shape one registered setting stores.
	 *
	 * @since 3.7.1
	 *
	 * @param array $setting The registered setting.
	 * @return string 'array', 'scalar' or 'either'.
	 */
	private static function resolve_shape( $setting ) {
		// A field the settings API renders with `multiple` posts one entry per selection.
		if ( ! empty( $setting['multiple'] ) ) {
			return 'array';
		}

		$type = ! empty( $setting['type'] ) ? $setting['type'] : '';

		if ( in_array( $type, self::ARRAY_TYPES, true ) ) {
			return 'array';
		}

		if ( in_array( $type, self::EITHER_SHAPE_TYPES, true ) ) {
			return 'either';
		}

		return in_array( $type, self::SCALAR_TYPES, true ) ? 'scalar' : 'either';
	}
}
