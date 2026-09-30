<?php
/**
 * Elementor Checkout Form Layer.
 *
 * The testable, emitted-string logic and per-render-pass state for the single
 * page-level purchase form the Elementor checkout emits — the same
 * #edd_checkout_form_wrap div and <form id="edd_purchase_form"> the block-editor
 * checkout renders. The purchase form opens once per render pass at the checkout
 * region boundary (the subscriber echoes wrap_open()/form_open() there) and wraps
 * the checkout content the region holds; the box itself is a plain container that
 * renders its children inside this form. Kept as pure string output plus static
 * state so the PHPUnit suite can exercise it without Elementor base classes.
 *
 * The form-layer emits NO nonce and NO edd_action hidden field: the single
 * edd-process-checkout-nonce arrives via the gateway-AJAX path
 * (edd_load_gateway -> edd_purchase_form -> edd_checkout_hidden_fields). Emitting
 * it here would double-emit the nonce.
 *
 * @package     EDD\Elementor\Checkout
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Elementor\Checkout;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Checkout form-layer: per-render-pass engagement tracking and hook contract.
 *
 * @since 3.7.0
 */
class FormLayer {

	/**
	 * The purchase form id, matching the block checkout (checkout.php).
	 *
	 * @since 3.7.0
	 *
	 * @var string
	 */
	const FORM_ID = 'edd_purchase_form';

	/**
	 * The outer wrapper id the block checkout emits (checkout.php).
	 *
	 * @since 3.7.0
	 *
	 * @var string
	 */
	const WRAP_ID = 'edd_checkout_form_wrap';

	/**
	 * The Elementor element id of the single checkout box that engaged this page render.
	 *
	 * Exactly one purchase form is rendered per Elementor document render pass.
	 * The subscriber records the first engaging box here (mark_engaged) and clears
	 * it when that box closes (clear_engaged). The engaged box's print_content()
	 * consults is_engaged_element() so only that box fires the checkout section
	 * hooks — a second box or a nested checkout-flagged container never does.
	 *
	 * @since 3.7.0
	 *
	 * @var string
	 */
	private static $engaged_element_id = '';

	/**
	 * Record the element id of the checkout box that engaged this page render.
	 *
	 * @since 3.7.0
	 *
	 * @param string $element_id The engaged box's Elementor element id.
	 * @return void
	 */
	public static function mark_engaged( string $element_id ): void {
		self::$engaged_element_id = $element_id;
	}

	/**
	 * Clear the engaged element id when the given box closes.
	 *
	 * Only clears when the id matches the currently-engaged box, so a stray close
	 * for a non-engaging element cannot drop a still-open engagement.
	 *
	 * @since 3.7.0
	 *
	 * @param string $element_id The Elementor element id whose render is closing.
	 * @return void
	 */
	public static function clear_engaged( string $element_id ): void {
		if ( '' !== $element_id && self::$engaged_element_id === $element_id ) {
			self::$engaged_element_id = '';
		}
	}

	/**
	 * Unconditionally clear the engaged element id.
	 *
	 * Unlike clear_engaged(), which only clears when the given id matches the
	 * currently-engaged box, this resets the engaged-element state regardless of
	 * which box (if any) is recorded. It is called at the start of each new
	 * document render pass (reset_for_new_render_pass) so a pass that leaked its
	 * teardown — a child threw before after_render's finally ran, leaving a stale
	 * engaged id — cannot make a later pass see is_engaged_element() as still true.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public static function reset_engaged(): void {
		self::$engaged_element_id = '';
	}

	/**
	 * Whether the given element id is the single engaged checkout box.
	 *
	 * @since 3.7.0
	 *
	 * @param string $element_id The Elementor element id to test.
	 * @return bool True when this element is the page's engaged checkout box.
	 */
	public static function is_engaged_element( string $element_id ): bool {
		return '' !== $element_id && self::$engaged_element_id === $element_id;
	}

	/**
	 * Whether a checkout box is currently engaged (actively rendering its children).
	 *
	 * True only while an edd-checkout-box is engaged for this render pass — i.e.
	 * between the box's before_render (mark_engaged) and its after_render
	 * (clear_engaged), which is exactly when the engaged box's print_content()
	 * renders its own child section widgets. The section dedupe (SectionGuard) gates
	 * on this so a section widget rendered standalone — before, after, or outside any
	 * engaged box — renders normally and never claims a type slot; only a section
	 * rendering inside the engaged box's own render can consume (and thereby
	 * de-duplicate) a slot.
	 *
	 * @since 3.7.0
	 *
	 * @return bool True when a checkout box is engaged for the current render.
	 */
	public static function is_box_engaged(): bool {
		return '' !== self::$engaged_element_id;
	}

	/**
	 * Whether the single page-level purchase form has opened for this render pass.
	 *
	 * Exactly one #edd_purchase_form is emitted per Elementor document render pass,
	 * since EDD core's gateway JS targets a single form of that id on the page. The
	 * subscriber sets this the first time checkout content in the
	 * region opens the form and consults it so no later box (or nested container)
	 * opens a second form. Unlike the box-engagement state ($engaged_element_id,
	 * which is per-box-close), this flag is per-PASS: it is set when the page form
	 * opens, is NOT cleared when a box closes, and is reset once per render pass by
	 * reset_for_new_render_pass() (and by the subscriber's shutdown failsafe). It is
	 * static so the Subscriber, the SectionGuard trait, and widget classes can all
	 * read it via FormLayer:: with no shared instance.
	 *
	 * @since 3.7.0
	 *
	 * @var bool
	 */
	private static $page_form_open = false;

	/**
	 * Mark the single page-level purchase form as opened for this render pass.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public static function open_page_form(): void {
		self::$page_form_open = true;
	}

	/**
	 * Whether the single page-level purchase form has already opened this pass.
	 *
	 * @since 3.7.0
	 *
	 * @return bool True once the page's purchase form has opened for this pass.
	 */
	public static function is_page_form_open(): bool {
		return self::$page_form_open;
	}

	/**
	 * Reset the page-form-open flag at the start of each render pass.
	 *
	 * Called by reset_for_new_render_pass() and the subscriber's shutdown failsafe
	 * so the per-pass "one form opened" guard never leaks across passes or, on a
	 * persistent worker, across requests.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public static function reset_page_form_open(): void {
		self::$page_form_open = false;
	}

	/**
	 * The Elementor element id of the checkout box that owns the empty-cart notice.
	 *
	 * On an empty cart no box opens a purchase form (mirroring the block checkout,
	 * which renders the empty-cart message and no form). The first qualifying box is
	 * recorded here so its print_content() renders the empty-cart notice exactly
	 * once, while any other checkout box on the page renders nothing. Reset per
	 * document render pass alongside the engaged-element state.
	 *
	 * @since 3.7.0
	 *
	 * @var string
	 */
	private static $empty_cart_element_id = '';

	/**
	 * Record the checkout box that owns the empty-cart notice for this render.
	 *
	 * @since 3.7.0
	 *
	 * @param string $element_id The Elementor element id of the notice-owning box.
	 * @return void
	 */
	public static function mark_empty_cart( string $element_id ): void {
		self::$empty_cart_element_id = $element_id;
	}

	/**
	 * Clear the empty-cart notice owner.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public static function clear_empty_cart(): void {
		self::$empty_cart_element_id = '';
	}

	/**
	 * Whether the given element id owns the page's empty-cart notice.
	 *
	 * @since 3.7.0
	 *
	 * @param string $element_id The Elementor element id to test.
	 * @return bool True when this element should render the empty-cart notice.
	 */
	public static function is_empty_cart_element( string $element_id ): bool {
		return '' !== $element_id && self::$empty_cart_element_id === $element_id;
	}

	/**
	 * The checkout section widget types already rendered in this render pass.
	 *
	 * A per-render-pass registry keyed by section widget type (e.g.
	 * edd-checkout-payment-info). The FIRST instance of a given type to render
	 * claims its slot here; any later same-type instance inside the engaged box
	 * renders nothing, so the page never emits a duplicate payment-info gateway
	 * selector, a duplicate #edd_purchase_form_wrap, or a duplicated field id — the
	 * duplicate-section defect that breaks the gateway switch. Lives for one render
	 * pass alongside the engaged/empty-cart markers: cleared by
	 * reset_for_new_render_pass() at the start of each pass and by the subscriber's
	 * shutdown failsafe, so a persistent worker never carries a claim into the next
	 * request.
	 *
	 * @since 3.7.0
	 *
	 * @var array<string, bool>
	 */
	private static $rendered_section_types = array();

	/**
	 * Claim the render slot for a checkout section type, first-instance-wins.
	 *
	 * Records the type as rendered and returns true when this is the FIRST
	 * instance of that type in the current render pass; returns false when the
	 * type has already rendered, signalling a duplicate the caller must skip. An
	 * empty type is never tracked and always allowed (it cannot be a tracked
	 * duplicate). Editor/preview exemption is the CALLER's responsibility — this
	 * registry is only consulted on the front end.
	 *
	 * @since 3.7.0
	 *
	 * @param string $type The section widget type (get_name()).
	 * @return bool True when this is the first render of the type (slot claimed).
	 */
	public static function claim_section_render( string $type ): bool {
		if ( '' === $type ) {
			return true;
		}

		if ( isset( self::$rendered_section_types[ $type ] ) ) {
			return false;
		}

		self::$rendered_section_types[ $type ] = true;

		return true;
	}

	/**
	 * Whether a checkout section type has already rendered this render pass.
	 *
	 * @since 3.7.0
	 *
	 * @param string $type The section widget type to test.
	 * @return bool True when the type has claimed its slot this render pass.
	 */
	public static function has_section_rendered( string $type ): bool {
		return '' !== $type && isset( self::$rendered_section_types[ $type ] );
	}

	/**
	 * Clear the per-render-pass rendered-section registry.
	 *
	 * Called at the start of each document render pass (reset_for_new_render_pass)
	 * and by the shutdown failsafe so section claims never leak across passes or
	 * requests.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public static function clear_rendered_sections(): void {
		self::$rendered_section_types = array();
	}

	/**
	 * Reset all shared per-render-pass form-layer state in one call.
	 *
	 * Clears the engaged-element id, the empty-cart notice owner, the rendered-section
	 * registry, and the page-level form-open guard. It does NOT touch the subscriber's
	 * per-box depth map or its fallback closures — those are instance state the
	 * subscriber tears down itself. Called when the outermost document render pass ends
	 * and by the subscriber's shutdown failsafe.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public static function reset_all(): void {
		self::reset_engaged();
		self::clear_empty_cart();
		self::clear_rendered_sections();
		self::reset_page_form_open();
	}

	/**
	 * Fire the checkout section hooks that belong at the TOP of the purchase form.
	 *
	 * Called from the engaged box's print_content() so the output lands INSIDE the
	 * <form> tag (the before_render/after_render actions fire outside it — see
	 * CheckoutBox::print_content()). edd_elementor_checkout_sections signals the
	 * Elementor checkout context the box-scoped fallback subscribers gate on;
	 * edd_checkout_form_top mirrors the block purchase form's top hook
	 * (PurchaseForm.php) so co-hooked renderers emit the same markup they do on
	 * block checkout.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public static function render_form_top(): void {
		/**
		 * Fires before EDD checkout sections render in an Elementor checkout context.
		 *
		 * Elementor-specific fallback subscribers gate on
		 * did_action( 'edd_elementor_checkout_sections' ).
		 *
		 * @since 3.7.0
		 */
		do_action( 'edd_elementor_checkout_sections' );

		// Payment icons are rendered via the gateway selector instead of the form-top
		// hook. edd_show_payment_icons is co-hooked on edd_payment_mode_top AND
		// edd_checkout_form_top (template.php), so without this removal the icons would
		// render twice. Mirrors the block purchase form (PurchaseForm.php).
		remove_action( 'edd_checkout_form_top', 'edd_show_payment_icons' );

		// Intentionally mirrors the CLASSIC (no-arg) signature: the co-hooked block
		// UserDetails::render() early-returns on empty attributes so OUR personal-info
		// fallback (with proper attrs) supplies the markup.
		do_action( 'edd_checkout_form_top' );
	}

	/**
	 * Fire the checkout section hook that belongs at the BOTTOM of the purchase form.
	 *
	 * Called from the engaged box's print_content() after its child widgets so the
	 * output lands INSIDE the <form> tag, mirroring the block purchase form order
	 * (PurchaseForm.php). Intentionally mirrors the CLASSIC (no-arg) signature.
	 *
	 * The captcha renders here, after the bottom hook, mirroring the block purchase
	 * form: the classic/gateway-AJAX purchase form (edd_show_purchase_form) never
	 * emits it, so the Elementor path would otherwise omit the checkout captcha.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public static function render_form_bottom(): void {
		do_action( 'edd_checkout_form_bottom' );

		if ( \EDD\Captcha\Utility::can_do_captcha() && defined( 'EDD_BLOCKS_DIR' ) ) {
			require_once EDD_BLOCKS_DIR . 'includes/forms/recaptcha.php';
			if ( function_exists( '\\EDD\\Blocks\\Recaptcha\\initialize' ) ) {
				\EDD\Blocks\Recaptcha\initialize();
			}
		}
	}

	/**
	 * Whether the given elements tree contains an edd-checkout-box (box detection).
	 *
	 * A box-DETECTION helper: it answers "is this element an edd-checkout-box?", not
	 * "should the purchase form open" — the page-level form now opens at the checkout
	 * region boundary, independently of this check. Detection and completeness are
	 * separate concerns: a box missing a required section (personal-info or
	 * payment-info) is still detected as a box so the fallback renderer can fill the
	 * gap inside the purchase form. A plain native container that merely holds EDD
	 * section widgets is NOT an edd-checkout-box, so this returns false for it (the
	 * box's before_render/after_render bookkeeping only tracks real boxes).
	 *
	 * @since 3.7.0
	 *
	 * @param array $elements Raw element-data arrays (elType/widgetType/elements).
	 * @return bool True when the tree contains an edd-checkout-box element.
	 */
	public static function should_engage( array $elements ): bool {
		// Only an edd-checkout-box elType is detected as a box, regardless of which sections are present.
		foreach ( $elements as $element ) {
			if ( is_array( $element ) && isset( $element['elType'] ) && 'edd-checkout-box' === $element['elType'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build the opening outer-wrapper div (#edd_checkout_form_wrap).
	 *
	 * Mirrors the block checkout wrapper (checkout.php): the wrapper carries
	 * the required wp-block-edd-checkout class so the compound CSS selector
	 * (style.scss) and the Stripe payment-request target (edd-prb--is-active)
	 * match. Echoed directly at the engaged element's before_render() by the
	 * subscriber (the echoed-wrapper approach), wrapping the native <form> it renders next.
	 *
	 * The block checkout's layout modifier and logged-in classes (e.g. is-two-column,
	 * edd-blocks__checkout--is-logged-in) are intentionally omitted: Elementor supplies
	 * its own layout for the box, so only the classes the shared CSS/JS selectors require
	 * are emitted here.
	 *
	 * @since 3.7.0
	 *
	 * @return string The opening wrapper div markup.
	 */
	public static function wrap_open(): string {
		$classes = array(
			'wp-block-edd-checkout',
			'edd-blocks__checkout',
			'edd-checkout__inner-blocks',
			'edd-checkout--elementor',
		);

		return sprintf(
			'<div id="%1$s" class="%2$s">',
			esc_attr( self::WRAP_ID ),
			esc_attr( implode( ' ', array_filter( $classes ) ) )
		);
	}

	/**
	 * Build the closing outer-wrapper div.
	 *
	 * @since 3.7.0
	 *
	 * @return string The closing wrapper div markup.
	 */
	public static function wrap_close(): string {
		return '</div>';
	}


	/**
	 * Build the opening purchase-form tag (<form id="edd_purchase_form">).
	 *
	 * Mirrors the block checkout's purchase form (checkout.php): the FORM_ID and the
	 * edd_form / edd-blocks-form classes match so the shared checkout JS (form
	 * validation, gateway switch) and CSS resolve the same on the Elementor front
	 * end as on the block checkout. Echoed directly at the region boundary's
	 * before_render() by the subscriber (no output buffer), wrapping the checkout
	 * content the region renders next; the matching form_close() is echoed at the
	 * region after_render().
	 *
	 * The form carries no inline style at all: the layout lives in the box's seeded
	 * inner-container tree, and the account line now renders inside the box, so it needs
	 * no width hint to align with it.
	 *
	 * @since 3.7.0
	 *
	 * @param string $action The form action URL (edd_get_checkout_uri()).
	 * @return string The opening <form> tag markup.
	 */
	public static function form_open( string $action ): string {
		$classes = array(
			'edd_form',
			'edd-blocks-form',
			'edd-blocks-form__purchase',
		);

		return sprintf(
			'<form id="%1$s" class="%2$s" action="%3$s" method="post">',
			esc_attr( self::FORM_ID ),
			esc_attr( implode( ' ', array_filter( $classes ) ) ),
			esc_url( $action )
		);
	}

	/**
	 * Build the closing purchase-form tag.
	 *
	 * @since 3.7.0
	 *
	 * @return string The closing </form> tag markup.
	 */
	public static function form_close(): string {
		return '</form>';
	}
}
