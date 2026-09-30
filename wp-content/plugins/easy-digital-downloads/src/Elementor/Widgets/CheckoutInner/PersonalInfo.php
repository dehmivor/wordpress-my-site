<?php
/**
 * Checkout Personal Info Elementor Widget
 *
 * Thin child section widget that renders the EDD checkout personal info and
 * billing-address section by delegating to the shared UserDetails coordinator.
 *
 * @package     EDD\Elementor\Widgets\CheckoutInner
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Elementor\Widgets\CheckoutInner;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Elementor\Widgets\Base;
use EDD\Elementor\Widgets\Config\Checkout\Styles\Sections;
use EDD\Elementor\Widgets\Config\CheckoutInner\Controls;
use EDD\Elementor\Widgets\CheckoutInner\Concerns\EditorPreview;
use EDD\Elementor\Widgets\CheckoutInner\Concerns\SectionGuard;

/**
 * EDD Checkout Personal Info Widget for Elementor.
 *
 * @since 3.7.0
 */
class PersonalInfo extends Base {

	use \EDD\Elementor\Widgets\Traits\ConfigurableControls;
	use EditorPreview;
	use SectionGuard;

	/**
	 * Enqueue the checkout style.
	 *
	 * @since 3.7.0
	 * @return void
	 */
	public static function enqueue_style() {
		if ( ! wp_style_is( 'edd-checkout-style', 'enqueued' ) ) {
			wp_enqueue_style(
				'edd-checkout-style',
				EDD_BLOCKS_URL . 'build/checkout/style-index.css',
				array(),
				EDD_VERSION
			);
		}

		if ( ! wp_style_is( 'edd-checkout-personal-info-style', 'enqueued' ) ) {
			wp_enqueue_style(
				'edd-checkout-personal-info-style',
				EDD_BLOCKS_URL . 'build/checkout-personal-info/style-index.css',
				array(),
				EDD_VERSION
			);
		}
	}

	/**
	 * Get widget name.
	 *
	 * @since 3.7.0
	 * @return string Widget name.
	 */
	public function get_name(): string {
		return 'edd-checkout-personal-info';
	}

	/**
	 * Get widget title.
	 *
	 * @since 3.7.0
	 * @return string Widget title.
	 */
	public function get_title(): string {
		return __( 'EDD Checkout Personal Info', 'easy-digital-downloads' );
	}

	/**
	 * Get widget icon.
	 *
	 * @since 3.7.0
	 * @return string Widget icon.
	 */
	public function get_icon(): string {
		return 'dashicons dashicons-admin-users';
	}

	/**
	 * Get widget keywords.
	 *
	 * @since 3.7.0
	 * @return array Widget keywords.
	 */
	public function get_keywords(): array {
		return array( 'edd', 'checkout', 'personal', 'info', 'email' );
	}

	/**
	 * Get the style dependencies for the widget.
	 *
	 * @since 3.7.0
	 * @return array The style dependencies for the widget.
	 */
	public function get_style_depends() {
		return array( 'edd-checkout-style', 'edd-checkout-personal-info-style' );
	}

	/**
	 * Hide the checkout section widget from the Add-widget panel.
	 *
	 * The composable checkout sections are seeded into the edd-checkout-box, not
	 * placed individually; hiding them from the panel prevents a loose section
	 * being dropped onto a page where it cannot render safely: a loose section has
	 * no purchase form wrapping it, and gateway JS that does
	 * getElementById( 'edd_purchase_form' ) then scopes into it would find nothing
	 * to scope to. Existing
	 * saved instances continue to register, render, and edit; this only removes the
	 * widget from the panel so it is not chosen for new layouts.
	 *
	 * @since 3.7.0
	 * @return bool
	 */
	public function show_in_panel(): bool {
		return false;
	}

	/**
	 * Register widget controls.
	 *
	 * Owns the account alignment control, the personal-info and billing-details
	 * title toggles, and the personal-information and billing-details section
	 * styling.
	 *
	 * @since 3.7.0
	 * @return void
	 */
	protected function register_controls() {
		$this->register_controls_from_config( Controls::get_personal_info_controls() );
	}

	/**
	 * Get the selector prefix for this widget.
	 *
	 * This widget wraps the personal-info slot (.edd-checkout-block__personal-info,
	 * which holds whichever of the guest, log in and register forms is showing) and
	 * the billing-address fieldset (#edd_cc_address, which now renders with the
	 * personal info), so {{WRAPPER}} is the correct prefix. The Sections group's
	 * `form#edd_purchase_form ` ancestor is mapped away below.
	 *
	 * @since 3.7.0
	 * @return string
	 */
	protected function get_selector_prefix(): string {
		return '{{WRAPPER}}';
	}

	/**
	 * Get the selector mappings for this widget.
	 *
	 * Strips the `form#edd_purchase_form ` ancestor from every section selector. The form is an
	 * ANCESTOR of {{WRAPPER}} here, so an unmapped selector would compile to
	 * `{{WRAPPER}} form#edd_purchase_form …` and match nothing. Derived from the section configs.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	protected function get_selector_mappings(): array {
		return Sections::get_form_ancestor_mappings();
	}

	/**
	 * Render the widget output on the frontend.
	 *
	 * Delegates to the shared UserDetails coordinator, which renders the
	 * personal-info fieldset AND the billing-address fields inside the block's
	 * .edd-blocks__user-details wrapper — the same DOM the block checkout emits on
	 * edd_checkout_form_top. The buffered output is already escaped by the
	 * underlying templates.
	 *
	 * @since 3.7.0
	 */
	protected function render() {
		// A section rendered outside an engaged checkout box renders nothing
		// (editor/preview exempt) — the render-inert backstop. See SectionGuard.
		if ( $this->is_render_inert() ) {
			return;
		}

		// First instance of this section type wins; a duplicate inside the engaged
		// box renders nothing (editor/preview exempt). See SectionGuard.
		if ( $this->is_duplicate_section() ) {
			return;
		}

		// Applies the "Preview as Guest" toggle before the first Attributes::get() below, so
		// the guest flag is set for the whole render pass regardless of which section renders
		// first (see EditorPreview::maybe_setup_editor_preview()).
		$this->maybe_setup_editor_preview();

		self::enqueue_style();

		$attrs = \EDD\Blocks\Checkout\Attributes::get();

		// The paired-name geometry lives in the checkout block's stylesheet, so this passes the
		// intent down as an attribute and the shared element emits the class both editors use.
		$attrs['name_single_line'] = 'yes' === $this->get_settings_for_display( 'name_single_line' );

		ob_start();
		\EDD\Blocks\Checkout\Elements\UserDetails::render( $attrs );
		echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped by the shared template.
	}
}
