<?php
/**
 * EDD Checkout container element for Elementor.
 *
 * A distinct element type that extends the native Elementor Container so it
 * inherits ALL real container behavior (drop zones, child-type acceptance,
 * flex/grid controls, render). Only identity, the seeded `edd-checkout` css
 * class, and the panel-config flags are overridden.
 *
 * The class extends a class that only loads when the native container
 * experiment is active, so it is defined inside a class_exists guard and is
 * loaded by the registration subscriber AFTER the native Container is
 * autoloaded.
 *
 * Serialized representation: the valid representation of this element is its
 * `_elementor_data` (elType `edd-checkout-box` + seeded children). A
 * non-Elementor post_content block-comment fallback is NOT provided; doing so
 * would require a document-save filter which is out of scope.
 *
 * It also overrides `is_dynamic_content()` to opt the box out of Elementor's
 * element cache, and `print_content()`, which branches on whether the
 * form-layer engaged this box (rendering its children as the purchase form's
 * content — the subscriber opens the <form> and fires the section hooks around
 * it), the box is the empty-cart notice owner, or the box is a second/duplicate
 * checkout box that must render nothing.
 *
 * @package     EDD\Elementor\Elements
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Elementor\Elements;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Elementor\Checkout\FormLayer;
use EDD\Elementor\Utils\Page;
use EDD\Elementor\Widgets\Traits\ConfigurableControls;
use EDD\Elementor\Widgets\Config\Checkout\Styles\FormElements;

if ( class_exists( '\Elementor\Includes\Elements\Container' ) && ! class_exists( '\EDD\Elementor\Elements\CheckoutBox' ) ) {

	/**
	 * EDD Checkout container element — extends the native Elementor Container.
	 *
	 * @since 3.7.0
	 */
	class CheckoutBox extends \Elementor\Includes\Elements\Container {

		// FormElements controls register on the box so their CSS scopes to {{WRAPPER}}, which wraps
		// the whole #edd_purchase_form and cascades to every inner section's inputs.
		use ConfigurableControls;

		/**
		 * Selector prefix for the box's config-driven (FormElements) controls.
		 *
		 * The form renders outside this box's wrapper, so `{{WRAPPER}}` cannot out-specify EDD's
		 * defaults. Id and both classes on one element (FormLayer::wrap_open()) buy the specificity
		 * that clears the blocks stylesheet's input rules.
		 *
		 * @since 3.7.0
		 * @return string
		 */
		protected function get_selector_prefix(): string {
			return '#edd_checkout_form_wrap.wp-block-edd-checkout.edd-checkout--elementor';
		}

		/**
		 * Element type.
		 *
		 * The elType used by the JS model factory and the serialized element data.
		 * Must equal get_name() so the panel elType, the registered JS element
		 * type, and the serialized elType all resolve to the same key.
		 *
		 * @since 3.7.0
		 *
		 * @return string
		 */
		public static function get_type() {
			return 'edd-checkout-box';
		}

		/**
		 * Element name.
		 *
		 * The config.elements key and the panel item name. Must equal get_type()
		 * so the panel elType resolves to the registered JS element type.
		 *
		 * @since 3.7.0
		 *
		 * @return string
		 */
		public function get_name() {
			return self::get_type();
		}

		/**
		 * Panel title.
		 *
		 * @since 3.7.0
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'EDD Checkout', 'easy-digital-downloads' );
		}

		/**
		 * Panel icon.
		 *
		 * @since 3.7.0
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-cart-medium';
		}

		/**
		 * Panel search keywords.
		 *
		 * @since 3.7.0
		 *
		 * @return array
		 */
		public function get_keywords() {
			return array( 'edd', 'checkout', 'container', 'edd-checkout' );
		}

		/**
		 * Replace the inherited native-container panel preset so no "Grid" tile appears.
		 *
		 * The native Container's get_panel_presets() returns a `container_grid` preset
		 * that renders as a "Grid" tile. Because this element extends the native
		 * Container and is forced into the EDD category, the INHERITED preset surfaces
		 * as a stray "Grid" tile under Easy Digital Downloads (the recurring
		 * regression). Overriding the method REPLACES that inherited list; returning the
		 * base-class empty list (element-base.php default) means this element
		 * contributes NO panel preset of its own.
		 *
		 * The box therefore shows exactly ONE panel tile: its own base "EDD Checkout"
		 * tile (the element is in the widgets config with show_in_panel + the `edd`
		 * category). Adding a custom single preset here would NOT collapse into that
		 * base tile — Elementor's panel builds a base tile from the widgets config AND a
		 * separate tile per preset, so a self-referential preset renders a SECOND,
		 * duplicate "EDD Checkout" tile (proven in the editor e2e). Layout is chosen by
		 * the editor-JS pattern picker, not a preset, so no structure preset is needed:
		 * the base tile adds the box as a flex container (native default) with the
		 * `edd-checkout` css class (the control default), after which the JS seed hook
		 * auto-seeds the default single-column pattern.
		 *
		 * @since 3.7.0
		 *
		 * @return array<string, array<string, mixed>> Always empty — no scoped preset,
		 *         so the inherited native "Grid" preset never surfaces.
		 */
		public function get_panel_presets() {
			return array();
		}

		/**
		 * Provide a server-side fallback default for the `css_classes` setting.
		 *
		 * This is a shallow-merged fallback only; it does not authoritatively seed
		 * the class at create time. The authoritative create-time seed is the
		 * `css_classes` control default (set in register_controls()) combined with
		 * the JS after-create seed hook in the editor. This server default acts as
		 * a backstop when the data array has no settings key at all.
		 *
		 * @since 3.7.0
		 *
		 * @return array
		 */
		protected function get_default_data() {
			$data = parent::get_default_data();

			if ( ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
				$data['settings'] = array();
			}

			$data['settings']['css_classes'] = 'edd-checkout';

			return $data;
		}

		/**
		 * Add the panel-hosted layout picker and re-default `css_classes`.
		 *
		 * The box keeps ALL of the native Elementor Container controls at their
		 * defaults (Content Layout, Direction, Content Width, Width, Min Height,
		 * Justify Content, Align Items, Gaps, Wrap) — nothing is hidden. On top of
		 * those, the box's layout can also be chosen by a five-pattern picker that
		 * lives IN the box's Elementor options panel (the settings side-panel) as its
		 * OWN dedicated "Checkout Layout" controls section, registered here via the
		 * custom `edd-layout-picker` control. The control is a data-less mount point:
		 * its editor JS view (see checkout-box.js) renders the five block-parity
		 * thumbnails (single-column plus the four two-column / cart-top variants) and the
		 * passive "Switching layouts replaces all inner blocks." text, and wires each
		 * thumbnail to the layout switch (delete the current inner widgets and re-seed the
		 * chosen pattern). Alongside the picker, this section also registers a NATIVE
		 * Elementor `switcher` control per non-required section (Cart, Discount Form) — the
		 * same slider toggle the monolith legacy checkout widget uses for section show/hide;
		 * the editor JS listens on the box's settings change for these control ids and
		 * re-seeds (ON) or removes (OFF) the section's child widget, keeping each switcher in
		 * sync with the section's actual presence. Every pattern drives the box's own
		 * `flex_direction` to `column` through the now-visible native Direction control;
		 * the two-column patterns get their side-by-side layout from an inner `row`
		 * container instead. A fresh box auto-seeds the
		 * `single-column` pattern; per-pattern column widths are seeded onto the inner
		 * containers by the picker.
		 *
		 * The `css_classes` re-default is a create-time default (the control's default
		 * value), so newly added instances carry the `edd-checkout` class through every
		 * creation path without overwriting a user's later edits.
		 *
		 * @since 3.7.0
		 *
		 * @return void
		 */
		protected function register_controls() {
			// Add the layout picker as its OWN dedicated section, separate from the
			// native Container "Layout" section, which keeps all of its controls shown
			// at their defaults. Registered before parent::register_controls() so it is
			// the first section in the Layout tab (and the one that auto-expands on open).
			$this->start_controls_section(
				'edd_checkout_layout',
				array(
					'label' => __( 'Checkout Layout', 'easy-digital-downloads' ),
					'tab'   => \Elementor\Controls_Manager::TAB_LAYOUT,
				)
			);

			$this->add_control(
				'edd_layout_picker',
				array(
					'type' => 'edd-layout-picker',
				)
			);

			// A native Elementor `switcher` (the slider toggle) per non-required section
			// (Cart, Discount Form) — the SAME control the monolith legacy checkout widget
			// uses for its section show/hide (see Widgets\Config\Checkout\Cart). Flipping a
			// switcher re-seeds (ON) or removes (OFF) that section's child widget in the box;
			// the editor JS (checkout-box.js) listens on the box's settings change for these
			// control ids and keeps their state in sync with the section's actual presence.
			// Only the two non-required sections get a switcher — the required personal-info
			// and payment-info sections are delete-locked and always present, so they get
			// none. Cart and the discount form are both seeded into every fresh box (the
			// discount right after the cart). Each switcher default is presence-driven: the
			// editor JS (checkout-box.js) rewrites the switcher from the box's actual contents
			// on panel open, so the stored default is only the initial value, not the source
			// of truth. Cart defaults `yes`; the discount form keeps its `''` default.
			$this->add_control(
				'edd_section_cart',
				array(
					'label'        => __( 'Cart', 'easy-digital-downloads' ),
					'type'         => 'switcher',
					'label_on'     => __( 'Show', 'easy-digital-downloads' ),
					'label_off'    => __( 'Hide', 'easy-digital-downloads' ),
					'return_value' => 'yes',
					'default'      => 'yes',
				)
			);

			$this->add_control(
				'edd_section_discount_form',
				array(
					'label'        => __( 'Discount Form', 'easy-digital-downloads' ),
					'type'         => 'switcher',
					'label_on'     => __( 'Show', 'easy-digital-downloads' ),
					'label_off'    => __( 'Hide', 'easy-digital-downloads' ),
					'return_value' => 'yes',
					'default'      => '',
				)
			);

			// Custom CSS for the checkout. Lives in the EDD Checkout Layout section (not
			// the Advanced tab) so it stays clear of Elementor Pro's own Custom CSS
			// control. Uses Elementor core's `code` control, so it works on free
			// Elementor. The value is output verbatim inside a single <style> block by
			// print_custom_css() on each render branch, matching how checkout templates
			// ship their CSS today.
			$this->add_control(
				'edd_custom_css',
				array(
					'label'       => __( 'Custom CSS', 'easy-digital-downloads' ),
					'type'        => \Elementor\Controls_Manager::CODE,
					'language'    => 'css',
					'separator'   => 'before',
					'description' => __( 'CSS entered here is output in a style tag when the checkout renders. Selectors are global (not scoped to this element); a surrounding style tag is optional.', 'easy-digital-downloads' ),
				)
			);

			$this->end_controls_section();

			parent::register_controls();

			if ( null !== $this->get_controls( 'css_classes' ) ) {
				$this->update_control(
					'css_classes',
					array(
						'default' => 'edd-checkout',
					)
				);
			}

			// Hosted on the box rather than per-section: its wrapper covers the whole
			// #edd_purchase_form, so one panel reaches every inner section's fields.
			$this->register_controls_from_config( FormElements::get_controls() );
		}

		/**
		 * Keep the panel-visibility and widgets-config flags.
		 *
		 * `include_in_widgets_config => true` is the flag that merges this element
		 * into the widgets cache the Add Element panel reads, so the element shows
		 * up in the panel under the EDD category.
		 *
		 * @since 3.7.0
		 *
		 * @return array
		 */
		protected function get_initial_config() {
			$config = parent::get_initial_config();

			$config['show_in_panel']             = true;
			$config['categories']                = array( 'edd' );
			$config['include_in_widgets_config'] = true;

			return $config;
		}

		/**
		 * Mark the checkout box as dynamic so Elementor never element-caches its output.
		 *
		 * The native Container returns false here, so Elementor's element cache
		 * (elementor_element_cache_ttl, active by default) stores the box's rendered
		 * HTML as a static string in the document cache and replays it for the TTL
		 * (default 24h) WITHOUT re-running print_element() — its before_render()/
		 * print_content() never fire. The checkout box's output is inherently
		 * request-dependent (cart contents, the process-checkout nonce, chosen gateway,
		 * logged-in state, and the empty-cart notice), so a cached copy goes stale: a box
		 * first rendered with an empty cart would keep showing "Your cart is empty." even
		 * after items are added. Returning true makes Elementor emit the box as an
		 * [elementor-element] shortcode in the document cache, which re-renders fresh on
		 * every request, so the purchase form / empty-cart notice always reflects the
		 * current cart. Mirrors how dynamic widgets opt out of the element cache.
		 *
		 * @since 3.7.0
		 *
		 * @return bool Always true — the checkout box must never be statically cached.
		 */
		protected function is_dynamic_content(): bool {
			return true;
		}

		/**
		 * Fire one of the box's slot hooks and wrap whatever it printed.
		 *
		 * Anything printed here is a sibling of the box's columns, so in a row layout it becomes a
		 * flex item and competes with them for width. The wrapper makes it span the row instead, and
		 * is emitted only when the hook printed something.
		 *
		 * @since 3.7.0
		 *
		 * @param string $hook       The action to fire.
		 * @param string $element_id The engaged box's Elementor element id.
		 * @param string $position   Slot position, `top` or `bottom`.
		 * @return void
		 */
		private function print_slot( string $hook, string $element_id, string $position ) {
			ob_start();
			try {
				do_action( $hook, $element_id );
			} finally {
				// Always close the buffer, so a throwing callback cannot leave it open.
				$output = ob_get_clean();
			}

			if ( '' === trim( (string) $output ) ) {
				return;
			}

			printf(
				'<div class="edd-checkout-box__slot edd-checkout-box__slot--%s">',
				esc_attr( $position )
			);
			// Hook output, already escaped by whatever rendered it.
			echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</div>';
		}

		/**
		 * Print the box content: render the child widgets when this box is engaged.
		 *
		 * The form-layer subscriber opens the #edd_checkout_form_wrap div and the
		 * <form id="edd_purchase_form"> around this box, and fires the section hooks
		 * (edd_checkout_form_top/_bottom) INSIDE that form, at the box's
		 * before_render/after_render ACTIONS. Elementor buffers print_content() output
		 * and echoes it BETWEEN those actions, so the engaged box simply renders its
		 * child widgets here (parent::print_content()) and they land as descendants of
		 * <form id="edd_purchase_form">, mirroring the block purchase form topology
		 * (EDD\Blocks\Checkout\Elements\PurchaseForm) where the sections are the form's
		 * $content. The box no longer becomes the form or fires the section hooks
		 * itself. Children render only when the form-layer engaged THIS box (exactly
		 * one purchase form is rendered per Elementor document render pass).
		 *
		 * A box the form-layer did not engage falls into one of three cases on the
		 * front end: (1) the Elementor editor/preview, where every box renders in full
		 * so authors can see and edit it; (2) the empty-cart notice owner, which emits
		 * the empty-cart message in place of a form (mirroring the block checkout); or
		 * (3) a second/duplicate or nested checkout-flagged box, which renders nothing
		 * so the page holds a single checkout form with no duplicated section
		 * widgets or field ids.
		 *
		 * @since 3.7.0
		 *
		 * @return void
		 */
		protected function print_content() {
			$element_id = (string) $this->get_id();

			// The engaged box renders its child widgets as the checkout form's content.
			// The form-layer subscriber opened the <form> around this box and fires the
			// section hooks (edd_checkout_form_top/_bottom) at the box's
			// before_render/after_render actions, so this box only renders its children
			// as a plain container — it no longer fires those hooks or becomes the form.
			if ( FormLayer::is_engaged_element( $element_id ) ) {
				$this->print_custom_css();

				/**
				 * Fires inside the engaged checkout box, before its sections render.
				 *
				 * Carries the account line, the required-fields notice and the fallbacks for any
				 * required section this box omits. The box cannot render them itself: they need the
				 * blocks runtime, which this element stays free of.
				 *
				 * @since 3.7.0
				 *
				 * @param string $element_id The engaged box's Elementor element id.
				 */
				$this->print_slot( 'edd_elementor_checkout_box_top', $element_id, 'top' );

				parent::print_content();

				/**
				 * Fires inside the engaged checkout box, after its sections render.
				 *
				 * @since 3.7.0
				 *
				 * @param string $element_id The engaged box's Elementor element id.
				 */
				$this->print_slot( 'edd_elementor_checkout_box_bottom', $element_id, 'bottom' );

				return;
			}

			// In the Elementor editor/preview every box renders so authors can see and
			// edit it (including a duplicate box they may want to delete).
			if ( Page::is_edit_mode() ) {
				$this->print_custom_css();
				parent::print_content();
				return;
			}

			// Front-end, not the engaged box. On an empty cart the form-layer opened no
			// form and named this box the empty-cart notice owner; mirror the block
			// checkout by emitting the empty-cart notice in its place.
			if ( FormLayer::is_empty_cart_element( $element_id ) ) {
				$this->print_custom_css();
				do_action( 'edd_cart_empty' );
			}

			// Any remaining non-engaged box is a second/duplicate checkout box (or a
			// nested checkout-flagged box). Render nothing so the page holds a single
			// checkout form and no section widget — and therefore no field id — is
			// duplicated.
		}

		/**
		 * Emit the box's Custom CSS control value inside a single style block.
		 *
		 * The value is authored via the Checkout Layout section's Custom CSS
		 * control and is output verbatim so template CSS keeps using global
		 * selectors (it is not scoped to the element wrapper). A surrounding
		 * <style> wrapper is stripped so the value works whether it was
		 * authored with or without one; both the start- and end-tag sequences
		 * are stripped repeatedly until stable so the value cannot reconstruct
		 * either tag and break out of the emitted style element. Raw markup in
		 * the stored value still requires the unfiltered_html capability at
		 * save time; non-privileged saves are run through wp_kses_post by
		 * Elementor's document save, which also HTML-entity-encodes CSS
		 * combinators such as `>`, so the value is entity-decoded before the
		 * stripping runs below.
		 *
		 * @since 3.7.0
		 *
		 * @return void
		 */
		protected function print_custom_css() {
			$css = $this->get_settings_for_display( 'edd_custom_css' );

			if ( ! is_string( $css ) || '' === trim( $css ) ) {
				return;
			}

			// Elementor's Document::save() runs the element tree through wp_kses_post() for
			// any user lacking the unfiltered_html capability — every user on a site defining
			// DISALLOW_UNFILTERED_HTML, administrators included — which rewrites CSS combinators
			// such as `>` to `&gt;` and breaks the stored rule. Decode entities before the strip
			// loop below so combinators survive; a decoded </style> is still neutralized because
			// the strip loop runs after this decode.
			$css = html_entity_decode( $css, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

			// Neutralize any <style> start/end tags in the value, looping until stable.
			// Stripping the full end tag (including its optional trailing >) prevents
			// the value from breaking out of the emitted style element (a single pass
			// could splice fragments into a fresh terminator); stripping the start tag
			// lets an author paste CSS with or without a surrounding <style> wrapper.
			// Valid CSS never contains a complete <style ...> or </style> tag, so
			// legitimate input is unaffected; a bare <style without a > is left as-is
			// (inert inside the emitted style element). A preg_replace failure
			// (unreachable in practice) blanks the value rather than restoring the
			// un-stripped input, so a would-be </style> can never echo.
			do {
				$before = $css;
				$css    = preg_replace( '#</\s*style\s*>?#i', '', $css ) ?? '';
				$css    = preg_replace( '#<\s*style\b[^>]*>#i', '', $css ) ?? '';
			} while ( $css !== $before );

			if ( '' === trim( $css ) ) {
				return;
			}

			// CSS is emitted verbatim; escaping would corrupt selectors and any <style>
			// tags are stripped above.
			echo '<style class="edd-checkout-custom-css">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}
