<?php
/**
 * Base Elementor Widget for EDD
 *
 * @package     EDD\Elementor\Widgets
 * @copyright   Copyright (c) 2025, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.0
 */

namespace EDD\Elementor\Widgets;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use Elementor\Plugin;
use Elementor\Widget_Base;
use EDD\Elementor\MarkerBuilder;

/**
 * Abstract base class for EDD Elementor widgets.
 *
 * @since 3.6.0
 */
abstract class Base extends Widget_Base {

	/**
	 * Render widget plain content.
	 *
	 * Elementor's plain-text save pipeline (Db::save_plain_text) invokes this for
	 * every widget node and writes the echoed output to the post_content column.
	 * Emitting real block-comment markers here makes the composable Elementor
	 * checkout discoverable by has_block(), so the block-gated checkout behavior
	 * (Validator::has_block, Attributes::get, core purchase-field de-duplication)
	 * treats the Elementor checkout as a first-class checkout block.
	 *
	 * Two markers are emitted per inner widget:
	 *
	 *  - The outer `edd/checkout` marker, identical across all four inner widgets.
	 *    Attributes::parse_attributes() reads the first `edd/checkout` block in
	 *    document order, so keeping the marker identical makes the parsed attributes
	 *    deterministic regardless of the order Elementor serializes the widgets.
	 *    Its attributes are reconstructed from the whole document: `thumbnail_width`
	 *    is read from the Cart widget, while `show_discount_form` is always true —
	 *    that widget is control-less (its visibility is governed by the box's
	 *    discount section switcher, not a per-widget toggle, and the widget itself
	 *    never renders when absent), so the marker carries no per-widget setting to
	 *    read. `layout` is structural
	 *    in the checkout box's composable model (chosen by the pattern picker, not a
	 *    box control), so the marker carries the block default.
	 *  - The widget's own inner marker (`edd/checkout-cart`,
	 *    `edd/checkout-personal-info`, `edd/checkout-payment-info`,
	 *    `edd/checkout-discount-form`), which satisfies the inner-name has_block()
	 *    checks such as the cart's duplicate-discount guard.
	 *
	 * @since 3.7.0
	 * @return void
	 */
	public function render_plain_content() {
		// Marker-write gate, guarding the single #edd_purchase_form DOM contract
		// gateway JS relies on: a section widget saved OUTSIDE an
		// edd-checkout-box writes no marker, so has_block() stays false and the
		// loose widget is never treated as a live checkout. A widget inside a box
		// writes both markers exactly as before.
		if ( ! $this->is_within_checkout_box() ) {
			return;
		}

		// The marker literals and the thumbnail-width clamp live in one place
		// (MarkerBuilder), so this widget's saved output cannot drift from the
		// importer's reconstructed output.
		echo MarkerBuilder::build( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- MarkerBuilder self-escapes: constrained enum/int attrs via wp_json_encode, inner block name via esc_html.
			array(
				array(
					'block' => $this->get_inner_block_name(),
					'attrs' => self::get_checkout_block_attributes(),
				),
			)
		);
	}

	/**
	 * Whether this widget's own element node is inside an edd-checkout-box at save.
	 *
	 * The marker-write gate for render_plain_content(). render_plain_content()
	 * fires inside Elementor's plain-text save pipeline (Db::save_plain_text),
	 * where the document being saved is the current document — the same save-time
	 * context get_checkout_block_attributes() already relies on. This walks that
	 * document's element tree for this widget's own id (get_id()) and reports
	 * whether any ancestor of that node is an edd-checkout-box. A loose section
	 * placed outside a box (copy/paste, template/API import, a pre-existing page,
	 * or a drag out of the box) therefore writes no wp:edd/checkout marker, which
	 * is what keeps has_block() false and prevents the standalone-widget gateway
	 * crash: gateway JS (PayPal, etc.) does getElementById( 'edd_purchase_form' )
	 * then scopes into it, and a widget with no wrapping form has nothing to scope
	 * to. Resolving no current document (never the case in the real
	 * save pipeline) is treated as "not within a box" so the gate fails closed.
	 *
	 * @since 3.7.0
	 * @return bool True when this widget's node is inside an edd-checkout-box.
	 */
	protected function is_within_checkout_box(): bool {
		if ( ! Plugin::$instance || ! Plugin::$instance->documents ) {
			return false;
		}

		$document = Plugin::$instance->documents->get_current();
		if ( ! $document ) {
			return false;
		}

		$elements = $document->get_elements_data();
		if ( empty( $elements ) ) {
			return false;
		}

		return self::element_id_within_box( $elements, (string) $this->get_id(), false );
	}

	/**
	 * Recursively test whether a target element id sits inside an edd-checkout-box.
	 *
	 * Pure walk over the saved element-data tree: it descends the tree tracking
	 * whether the current branch is already inside an edd-checkout-box (an
	 * edd-checkout-box node turns the flag on for its whole subtree), and returns
	 * that flag's value for the node whose id matches the target. Kept side-effect
	 * free and static so the marker-write gate is unit-testable without Elementor.
	 *
	 * @since 3.7.0
	 *
	 * @param array  $elements  The element-data tree to search.
	 * @param string $target_id The widget element id to locate.
	 * @param bool   $inside    Whether the current branch is already inside a box.
	 * @return bool True when the target id is found inside an edd-checkout-box.
	 */
	private static function element_id_within_box( array $elements, string $target_id, bool $inside ): bool {
		if ( '' === $target_id ) {
			return false;
		}

		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			$within = $inside || ( 'edd-checkout-box' === ( $element['elType'] ?? '' ) );

			if ( ( $element['id'] ?? '' ) === $target_id ) {
				return $within;
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				if ( self::element_id_within_box( $element['elements'], $target_id, $within ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get the inner block name emitted by this widget's plain-content marker.
	 *
	 * The composable widget names mirror the block names, differing only in the
	 * namespace separator (`edd-checkout-cart` widget maps to the `edd/checkout-cart`
	 * block), so the block name is derived from the widget name.
	 *
	 * @since 3.7.0
	 * @return string The inner block name.
	 */
	protected function get_inner_block_name(): string {
		return (string) preg_replace( '/^edd-/', 'edd/', $this->get_name() );
	}

	/**
	 * Reconstruct the outer checkout block attributes from the saved document.
	 *
	 * The attribute values the block layer honors come from the saved document:
	 * `thumbnail_width` is read from the Cart widget. `show_discount_form` is always
	 * true — that widget is control-less, so its own settings never hold a value for
	 * it; the box's discount section switcher governs its presence and its render is
	 * presence-gated independently of this marker.
	 * This walks the document being saved (set as the current document by the editor
	 * save flow) so every inner widget serializes the same, complete attribute set.
	 * `layout` is structural in the checkout box's composable model (chosen by the
	 * pattern picker, which seeds the inner-widget arrangement), so it is emitted as
	 * the block default rather
	 * than read from the retired `edd_layout` control. Only the page-persistent
	 * attributes are emitted; request-time values (`logged_in`,
	 * `show_register_form`) are intentionally omitted so Attributes::get() overlays
	 * the fresh runtime defaults.
	 *
	 * @since 3.7.0
	 * @return array The reconstructed block attributes.
	 */
	protected static function get_checkout_block_attributes(): array {
		$defaults = array(
			'layout'             => '',
			'show_discount_form' => true,
			'thumbnail_width'    => 25,
		);

		if ( ! Plugin::$instance || ! Plugin::$instance->documents ) {
			return $defaults;
		}

		$document = Plugin::$instance->documents->get_current();
		if ( ! $document ) {
			return $defaults;
		}

		$elements = $document->get_elements_data();
		if ( empty( $elements ) ) {
			return $defaults;
		}

		$cart = self::find_element_settings( $elements, 'edd-checkout-cart' );

		return array(
			// Layout is structural in the checkout box's composable model (chosen by
			// the pattern picker, which seeds the inner-widget arrangement), so the
			// marker carries the block default rather than reading a retired box control.
			'layout'             => '',
			// The discount-form widget is control-less, so its own settings never hold
			// a `show_discount_form` value to read — the marker is always true.
			'show_discount_form' => true,
			// Raw cart thumbnail width; MarkerBuilder normalizes and clamps it.
			'thumbnail_width'    => $cart['thumbnail_width'] ?? null,
		);
	}

	/**
	 * Recursively find the settings array for the first element of a given type.
	 *
	 * Matches a widget by its `widgetType` (or a container element by its `elType`),
	 * mirroring how the element data is serialized.
	 *
	 * @since 3.7.0
	 *
	 * @param array  $elements The element data tree to search.
	 * @param string $type     The widgetType or elType to match.
	 * @return array|null The matching element's settings, or null if not found.
	 */
	private static function find_element_settings( array $elements, string $type ) {
		foreach ( $elements as $element ) {
			$element_type = $element['widgetType'] ?? $element['elType'] ?? '';
			if ( $type === $element_type ) {
				return isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
			}

			if ( ! empty( $element['elements'] ) ) {
				$found = self::find_element_settings( $element['elements'], $type );
				if ( null !== $found ) {
					return $found;
				}
			}
		}

		return null;
	}

	/**
	 * Get widget categories
	 *
	 * @since 3.6.0
	 * @return array Widget categories
	 */
	public function get_categories(): array {
		return array( 'edd' );
	}

	/**
	 * Get widget keywords
	 *
	 * @since 3.6.0
	 * @return array Widget keywords
	 */
	public function get_keywords(): array {
		return array( 'edd' );
	}

	/**
	 * Whether the widget has a widget inner wrapper.
	 *
	 * @since 3.6.0
	 * @return bool Whether the widget has a widget inner wrapper.
	 */
	public function has_widget_inner_wrapper(): bool {
		return false;
	}
}
