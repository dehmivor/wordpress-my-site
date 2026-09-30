<?php
/**
 * Checkout block-marker builder for Elementor plain-content output.
 *
 * Single source of the checkout block-comment markers written to post_content
 * for an Elementor checkout, and of the thumbnail-width clamp those markers
 * carry. Every emit site (the live section widgets, the deprecated monolith
 * widget, and the template importer's fallback) routes through this class so
 * the import output and the normal Elementor-save output stay byte-identical and
 * cannot drift.
 *
 * Deliberately free of any \Elementor\* reference: the importer's fallback caller
 * runs when the Elementor runtime is absent, so the builder is pure data-to-string
 * and the reusable tree walk reconstructs the marker set from a saved element tree
 * (associative arrays), never from live widget objects. The tree walk reads
 * DOWNLOADED template JSON, so the reconstructed attribute set is constrained to
 * numeric/enum/const values only (an enum-validated layout, a boolean discount
 * toggle, and a clamped integer thumbnail width). No free-form string from the
 * tree is ever copied into a marker, which keeps the surrounding HTML-comment
 * delimiter unbreakable.
 *
 * @package     EDD\Elementor
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Elementor;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * MarkerBuilder class.
 *
 * @since 3.7.0
 */
final class MarkerBuilder {

	/**
	 * The outer checkout block name, shared by every section marker.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	private const OUTER_BLOCK = 'edd/checkout';

	/**
	 * Default cart thumbnail width, in pixels.
	 *
	 * @since 3.7.0
	 * @var int
	 */
	private const DEFAULT_THUMBNAIL_WIDTH = 25;

	/**
	 * Minimum cart thumbnail width the control allows, in pixels.
	 *
	 * @since 3.7.0
	 * @var int
	 */
	private const MIN_THUMBNAIL_WIDTH = 10;

	/**
	 * Maximum cart thumbnail width the control allows, in pixels.
	 *
	 * @since 3.7.0
	 * @var int
	 */
	private const MAX_THUMBNAIL_WIDTH = 100;

	/**
	 * The layout values the checkout block recognizes.
	 *
	 * Anything else (including a hostile string pulled from a downloaded template)
	 * normalizes to the empty single-column default, so a free-form value can never
	 * reach the marker and break its HTML-comment delimiter.
	 *
	 * @since 3.7.0
	 * @var string[]
	 */
	private const ALLOWED_LAYOUTS = array(
		'',
		'half',
		'two-thirds',
		'four-fifths',
		'half-bottom',
		'two-thirds-bottom',
	);

	/**
	 * Map of checkout widget type to the inner block name its marker carries.
	 *
	 * The four composable section widgets each map to their matching inner block.
	 * The deprecated monolith widget (edd-checkout) is an all-in-one section with
	 * no inner block, so it maps to an empty string (outer marker only).
	 *
	 * @since 3.7.0
	 * @var array<string,string>
	 */
	private const SECTION_WIDGETS = array(
		'edd-checkout'               => '',
		'edd-checkout-cart'          => 'edd/checkout-cart',
		'edd-checkout-personal-info' => 'edd/checkout-personal-info',
		'edd-checkout-payment-info'  => 'edd/checkout-payment-info',
		'edd-checkout-discount-form' => 'edd/checkout-discount-form',
	);

	/**
	 * Build the checkout marker set from an ordered list of sections.
	 *
	 * Each section emits the shared outer edd/checkout marker (its reconstructed
	 * page-persistent attributes) immediately followed by its own inner
	 * self-closing marker, with no separator between them or between sections,
	 * matching how Elementor concatenates each widget's render_plain_content()
	 * output. A composable checkout yields the outer marker plus an inner marker
	 * per section; the deprecated monolith yields a single outer marker; an empty
	 * list yields an empty string.
	 *
	 * @since 3.7.0
	 *
	 * @param array $sections Ordered list of sections, each an array with a
	 *                        'block' inner-block-name string and an 'attrs' array.
	 * @return string The concatenated marker set, or an empty string when no sections.
	 */
	public static function build( array $sections ): string {
		$markup = '';

		foreach ( $sections as $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}

			$block = ( isset( $section['block'] ) && is_string( $section['block'] ) ) ? $section['block'] : '';
			$attrs = ( isset( $section['attrs'] ) && is_array( $section['attrs'] ) ) ? $section['attrs'] : array();

			$markup .= self::section_markers( $block, $attrs );
		}

		return $markup;
	}

	/**
	 * Reconstruct the section list from a saved Elementor element tree.
	 *
	 * Walks the tree depth-first in document order (the same order Elementor's
	 * plain-text save pipeline visits widgets), collecting each checkout section
	 * widget it recognizes, then attaches the page-persistent outer attributes to
	 * every section so the emitted set matches the live-widget save path.
	 *
	 * @since 3.7.0
	 *
	 * @param array $elements The saved Elementor element tree.
	 * @return array The section list for build(), or an empty array when the tree
	 *               carries no checkout widget.
	 */
	public static function from_elements( array $elements ): array {
		$blocks = array();
		self::collect_section_blocks( $elements, $blocks );

		if ( empty( $blocks ) ) {
			return array();
		}

		$attrs = self::reconstruct_attributes( $elements );

		return array_map(
			static function ( $block ) use ( $attrs ) {
				return array(
					'block' => $block,
					'attrs' => $attrs,
				);
			},
			$blocks
		);
	}

	/**
	 * Emit the outer marker (and inner marker when present) for one section.
	 *
	 * @since 3.7.0
	 *
	 * @param string $inner_block The inner block name, or '' for an outer-only section.
	 * @param array  $attrs       The raw attributes to normalize onto the outer marker.
	 * @return string The section's marker(s).
	 */
	private static function section_markers( string $inner_block, array $attrs ): string {
		$markup = '<!-- wp:' . self::OUTER_BLOCK . ' ' . wp_json_encode( self::normalize_attributes( $attrs ) ) . ' /-->';

		if ( '' !== $inner_block && self::OUTER_BLOCK !== $inner_block ) {
			$markup .= '<!-- wp:' . esc_html( $inner_block ) . ' /-->';
		}

		return $markup;
	}

	/**
	 * Constrain the outer marker attributes to numeric/enum/const values only.
	 *
	 * The three page-persistent attributes the block layer honors: an
	 * enum-validated layout, a boolean discount-form toggle, and a clamped integer
	 * thumbnail width. Emitting only these guarantees no free-form string from a
	 * downloaded template can reach the marker and break its HTML-comment delimiter.
	 * Key order is fixed so the serialized output is deterministic and identical
	 * across every emit site.
	 *
	 * @since 3.7.0
	 *
	 * @param array $attrs The raw attributes.
	 * @return array The constrained attributes in a fixed key order.
	 */
	private static function normalize_attributes( array $attrs ): array {
		return array(
			'layout'             => self::normalize_layout( $attrs['layout'] ?? '' ),
			'show_discount_form' => (bool) ( $attrs['show_discount_form'] ?? true ),
			'thumbnail_width'    => self::clamp_thumbnail_width( $attrs['thumbnail_width'] ?? null ),
		);
	}

	/**
	 * Normalize a layout value to a recognized enum member.
	 *
	 * @since 3.7.0
	 *
	 * @param mixed $layout The raw layout value.
	 * @return string A recognized layout value, or '' when unrecognized.
	 */
	private static function normalize_layout( $layout ): string {
		if ( ! is_string( $layout ) ) {
			return '';
		}

		return in_array( $layout, self::ALLOWED_LAYOUTS, true ) ? $layout : '';
	}

	/**
	 * Normalize then clamp a raw thumbnail width to the control's allowed range.
	 *
	 * Accepts both the widget-setting array shape (`array( 'size' => 42 )`) and a
	 * bare scalar. An empty value falls back to the default; otherwise the value is
	 * clamped between the minimum and maximum the control allows.
	 *
	 * @since 3.7.0
	 *
	 * @param mixed $value The raw thumbnail width (array with a 'size' key or scalar).
	 * @return int The clamped thumbnail width.
	 */
	private static function clamp_thumbnail_width( $value ): int {
		if ( is_array( $value ) ) {
			$value = $value['size'] ?? null;
		}

		if ( '' === $value || null === $value ) {
			return self::DEFAULT_THUMBNAIL_WIDTH;
		}

		return (int) max( self::MIN_THUMBNAIL_WIDTH, min( self::MAX_THUMBNAIL_WIDTH, (int) $value ) );
	}

	/**
	 * Reconstruct the page-persistent outer attributes from the element tree.
	 *
	 * Layout is structural in the composable model, so the marker carries the block
	 * default; the discount-form widget is control-less, so the toggle is always
	 * true. Only the thumbnail width is read from the tree -- from the cart section
	 * for a composable checkout, or the monolith widget's own settings for the
	 * deprecated widget -- and it is clamped to an integer by the builder.
	 *
	 * @since 3.7.0
	 *
	 * @param array $elements The saved Elementor element tree.
	 * @return array The raw outer attributes.
	 */
	private static function reconstruct_attributes( array $elements ): array {
		$settings = self::find_settings( $elements, 'edd-checkout-cart' );
		if ( null === $settings ) {
			$settings = self::find_settings( $elements, 'edd-checkout' );
		}

		return array(
			'layout'             => '',
			'show_discount_form' => true,
			'thumbnail_width'    => $settings['thumbnail_width'] ?? null,
		);
	}

	/**
	 * Collect the inner block names of every checkout section widget, in order.
	 *
	 * Depth-first, pre-order walk mirroring Elementor's plain-text save traversal,
	 * so the collected order matches the live-widget emit order exactly.
	 *
	 * @since 3.7.0
	 *
	 * @param array $elements The element tree to walk.
	 * @param array $blocks   Accumulator of inner block names (by reference).
	 * @return void
	 */
	private static function collect_section_blocks( array $elements, array &$blocks ): void {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			$widget_type = $element['widgetType'] ?? '';
			if ( 'widget' === ( $element['elType'] ?? '' ) && isset( self::SECTION_WIDGETS[ $widget_type ] ) ) {
				$blocks[] = self::SECTION_WIDGETS[ $widget_type ];
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				self::collect_section_blocks( $element['elements'], $blocks );
			}
		}
	}

	/**
	 * Recursively find the settings array for the first element of a given type.
	 *
	 * Matches a widget by its widgetType (or a container element by its elType),
	 * mirroring how the element data is serialized.
	 *
	 * @since 3.7.0
	 *
	 * @param array  $elements The element tree to search.
	 * @param string $type     The widgetType or elType to match.
	 * @return array|null The matching element's settings, or null when not found.
	 */
	private static function find_settings( array $elements, string $type ) {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			$element_type = $element['widgetType'] ?? $element['elType'] ?? '';
			if ( $type === $element_type ) {
				return ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) ? $element['settings'] : array();
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$found = self::find_settings( $element['elements'], $type );
				if ( null !== $found ) {
					return $found;
				}
			}
		}

		return null;
	}
}
