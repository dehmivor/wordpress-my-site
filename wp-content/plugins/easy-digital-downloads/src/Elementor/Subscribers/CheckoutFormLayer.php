<?php
/**
 * EDD Elementor Checkout Form-Layer Subscriber.
 *
 * Opens the single page-level EDD purchase <form> at the checkout region
 * boundary, wrapped in the same #edd_checkout_form_wrap div the block-editor
 * checkout emits. The region boundary rides the `edd-checkout-box` element render
 * hooks: Elementor fires `elementor/frontend/{get_type()}/before_render`, and the
 * edd-checkout-box element reports its own type, so those hooks fire for the box.
 * The box is no longer coerced into the <form> — it renders as a plain container,
 * and its children render INSIDE the form the subscriber opens around it.
 *
 * The form and its outer wrapper are emitted via the echoed approach:
 * FormLayer::wrap_open() then FormLayer::form_open() are echoed directly at the
 * region's before_render(), and FormLayer::form_close() then FormLayer::wrap_close()
 * at its after_render(), with NO output buffering and NO string splice. The section
 * hooks that belong inside the form fire there too: edd_checkout_form_top (via
 * FormLayer::render_form_top()) after the form opens, and edd_checkout_form_bottom
 * (via FormLayer::render_form_bottom(), which also renders the captcha) before it
 * closes — mirroring the block checkout topology (checkout.php).
 *
 * The purchase-form hooks bracket the wrapper the same way: edd_before_purchase_form
 * fires inside the wrapper before the form opens, and edd_after_purchase_form fires
 * between form_close() and wrap_close(). Before firing edd_before_purchase_form the
 * subscriber hands off to EDD\Integrations\SoftwareLicensing::remove_renewal_form(). For a
 * logged-in customer the "Account Information" line is emitted at the top of the form,
 * matching the inner-blocks checkout (EDD\Blocks\Checkout\AccountLine).
 *
 * The form-layer emits NO nonce: the single edd-process-checkout-nonce arrives
 * via the gateway-AJAX path (edd_load_gateway -> edd_purchase_form ->
 * edd_checkout_hidden_fields).
 *
 * Security note: FormLayer::wrap_open()/form_open() self-escape the emitted
 * attributes (esc_attr for id/class, esc_url for action), so the echoes carry a
 * scoped phpcs escape-output ignore.
 *
 * Exactly one purchase form opens per render pass, since EDD core's gateway JS
 * targets a single #edd_purchase_form on the page: before_render()
 * resets the engaged/empty-cart state for each new document render, the FIRST
 * checkout content to reach the boundary opens the form and sets the page-level
 * FormLayer::open_page_form() guard, and any later box (or nested container) is
 * refused. If the cart is empty, no form opens and the first box is instead marked
 * as the empty-cart notice owner so it can emit the notice in print_content(). The
 * subscriber also registers and unregisters, per element id, box-scoped fallback
 * callbacks for the personal-info and payment-info sections so those required
 * sections still render when a box's children don't include them directly.
 *
 * @package     EDD\Elementor\Subscribers
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Elementor\Subscribers;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\EventManagement\SubscriberInterface;
use EDD\Blocks\Checkout\AccountLine;
use EDD\Elementor\Checkout\FormLayer;
use EDD\Elementor\Utils\Page;

/**
 * Class CheckoutFormLayer
 *
 * @since 3.7.0
 */
class CheckoutFormLayer implements SubscriberInterface {

	/**
	 * The per-element engaged-render depth, keyed by Elementor element id.
	 *
	 * Each engaged checkout box gets its own entry so multiple boxes on one page
	 * engage independently (a single scalar/bool would collide across boxes). The
	 * value is the re-entrancy depth for that element id: 0 at the engaging call,
	 * incremented on a nested re-entrant call on the same id, and decremented on
	 * each matching after_render. When it returns to 0 the wrapper is closed for
	 * that box.
	 *
	 * @since 3.7.0
	 *
	 * @var array<string, int>
	 */
	private $engaged_depth_map = array();

	/**
	 * The registered personal-info fallback callbacks, keyed by Elementor element id.
	 *
	 * Each engaged box stores the exact closure it registered on edd_checkout_form_top
	 * so after_render() can pass the SAME instance to remove_action(). Passing a fresh
	 * inline closure to remove_action() would not match (PHP builds a new object each
	 * time), leaking the subscriber to later hooks.
	 *
	 * @since 3.7.0
	 *
	 * @var array<string, callable>
	 */
	private $pi_fallback_cb = array();

	/**
	 * The registered payment-info fallback callbacks, keyed by Elementor element id.
	 *
	 * Stored per box so the exact closure registered on edd_checkout_form_bottom can be
	 * passed back to remove_action() in after_render().
	 *
	 * @since 3.7.0
	 *
	 * @var array<string, callable>
	 */
	private $pay_fallback_cb = array();

	/**
	 * Whether the shutdown failsafe has been registered for this request.
	 *
	 * Ensures the shutdown action is added at most once, even when multiple
	 * containers are processed in the same request.
	 *
	 * @since 3.7.0
	 *
	 * @var bool
	 */
	private $failsafe_registered = false;

	/**
	 * The Elementor document-id render stack for the current request.
	 *
	 * A document id is pushed when a document render begins
	 * (before_get_builder_content) and popped when it ends (get_builder_content).
	 * Shared form-layer state is torn down only when the stack returns to empty
	 * (the outermost pass is done), so a nested document rendered mid-outer-render
	 * cannot wipe the outer box's still-open wrapper. The matching-id pop also
	 * discards an unbalanced entry left by Elementor's empty-data early return (a
	 * nested document that fired the push but returned before its pop).
	 *
	 * @since 3.7.0
	 *
	 * @var string[]
	 */
	private $document_stack = array();

	/**
	 * Get the subscribed events.
	 *
	 * Hooks the edd-checkout-box element render hooks: Elementor fires
	 * `elementor/frontend/{get_type()}/before_render`, and the edd-checkout-box
	 * element reports its own type, so these hooks fire for the box. A plain
	 * native container does not engage the form layer, so the container render
	 * hooks are not subscribed. The paired document hooks
	 * `elementor/frontend/before_get_builder_content` (push) and
	 * `elementor/frontend/get_builder_content` (pop) bracket every document render
	 * pass; the subscriber tracks that nesting on a stack so shared state is reset
	 * only when the outermost pass ends, never mid-nested-render.
	 *
	 * @since 3.7.0
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		return array(
			'elementor/frontend/before_get_builder_content' => 'reset_for_new_render_pass',
			'elementor/frontend/get_builder_content' => 'end_document_pass',
			'elementor/frontend/edd-checkout-box/before_render' => 'before_render',
			'elementor/frontend/edd-checkout-box/after_render' => 'after_render',
			'edd_elementor_checkout_box_top'         => 'render_box_top',
			'edd_elementor_checkout_box_bottom'      => 'render_box_bottom',
		);
	}

	/**
	 * Begin a document render pass (the push half of the paired document hooks).
	 *
	 * Fires once at the start of every document render, including a nested render
	 * started mid-outer-render. Only the OUTERMOST pass (an empty stack) tears down
	 * the shared form-layer state, so each independent top-level pass opens its own
	 * purchase form while a nested render never wipes the outer box's open wrapper or
	 * its page-form-open guard. The document id is pushed so its paired
	 * end_document_pass() can detect when the outermost pass finishes.
	 *
	 * @since 3.7.0
	 *
	 * @param mixed $document The Elementor document beginning its render (nullable, BC).
	 * @return void
	 */
	public function reset_for_new_render_pass( $document = null ): void {
		if ( empty( $this->document_stack ) ) {
			$this->tear_down_render_state();
		}

		$this->document_stack[] = $this->get_document_id( $document );
	}

	/**
	 * End a document render pass (the pop half of the paired document hooks).
	 *
	 * Pops this document's entry off the stack. When the id is resolvable it pops
	 * back through any entries stacked above it, discarding an unbalanced push left
	 * by Elementor's empty-data early return (a nested document that fired the push
	 * but returned before this pop). Once the stack empties the outermost pass is
	 * done, so the shared form-layer state is torn down here.
	 *
	 * @since 3.7.0
	 *
	 * @param mixed $document The Elementor document ending its render (nullable, BC).
	 * @return void
	 */
	public function end_document_pass( $document = null ): void {
		if ( empty( $this->document_stack ) ) {
			return;
		}

		$document_id = $this->get_document_id( $document );
		if ( '' === $document_id ) {
			array_pop( $this->document_stack );
		} else {
			while ( ! empty( $this->document_stack ) ) {
				if ( array_pop( $this->document_stack ) === $document_id ) {
					break;
				}
			}
		}

		if ( empty( $this->document_stack ) ) {
			$this->tear_down_render_state();
		}
	}

	/**
	 * Tear down the shared and per-box render-pass state.
	 *
	 * Removes each leaked box's fallback closures and clears its shared engaged
	 * state, drops the per-box depth-map entries, then resets the shared FormLayer
	 * state via FormLayer::reset_all(). Called when the outermost document pass
	 * begins or ends.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	private function tear_down_render_state(): void {
		foreach ( array_keys( $this->engaged_depth_map ) as $element_id ) {
			$this->unregister_section_fallbacks( $element_id );
			FormLayer::clear_engaged( $element_id );
			unset( $this->engaged_depth_map[ $element_id ] );
		}

		FormLayer::reset_all();
	}

	/**
	 * Resolve a stable string id for a document on the render stack.
	 *
	 * Prefers the document's get_id() (its post id), falling back to the object
	 * hash for an id-less object and the scalar value for a plain identifier (the
	 * form the tests pass). Returns an empty string when unresolvable.
	 *
	 * @since 3.7.0
	 *
	 * @param mixed $document The Elementor document (or a test stand-in).
	 * @return string The document id, or an empty string when unresolved.
	 */
	private function get_document_id( $document ): string {
		if ( is_object( $document ) ) {
			if ( method_exists( $document, 'get_id' ) ) {
				return (string) $document->get_id();
			}

			return (string) spl_object_id( $document );
		}

		if ( is_scalar( $document ) ) {
			return (string) $document;
		}

		return '';
	}

	/**
	 * Open the single page-level purchase form at the checkout region boundary.
	 *
	 * If the element qualifies (is an edd-checkout-box and edd checkout context is
	 * available) AND no purchase form has opened yet this pass (exactly one purchase
	 * form per Elementor document render pass, because EDD core's gateway JS targets
	 * a single #edd_purchase_form on the page), this: sets the page-level
	 * FormLayer::open_page_form() guard, marks this box as THE engaged box
	 * (box-engagement bookkeeping for the dedupe path), registers the box-scoped
	 * section fallbacks, and echoes the outer #edd_checkout_form_wrap div,
	 * fires edd_before_purchase_form, then echoes the <form id="edd_purchase_form"> open
	 * directly at this hook point (no output buffer), emits the logged-in account block
	 * at the top of the form (mirroring the block checkout), then fires
	 * edd_checkout_form_top INSIDE the form via FormLayer::render_form_top(). The box then
	 * renders its children as a plain container between here and after_render(), so they
	 * land inside the form; after_render() fires edd_checkout_form_bottom, closes the form,
	 * fires edd_after_purchase_form, then closes the wrapper.
	 * Re-entrant calls on the same element id only increment that id's depth so the
	 * form opens exactly once per box; a subsequent box or a nested checkout box is
	 * refused so exactly one form exists per render pass.
	 *
	 * @since 3.7.0
	 *
	 * @param \Elementor\Element_Base $element The element being rendered.
	 * @return void
	 */
	public function before_render( $element ): void {
		$element_id = $this->get_element_id( $element );

		// An element with no resolvable id cannot be tracked through after_render
		// (teardown keys on the id), so engaging it would leak the wrapper to
		// shutdown. Leave such a render untouched.
		if ( '' === $element_id ) {
			return;
		}

		// A re-entrant call on an already-engaged id: only track depth, no re-open.
		if ( array_key_exists( $element_id, $this->engaged_depth_map ) ) {
			++$this->engaged_depth_map[ $element_id ];
			return;
		}

		$elements = array( $this->get_raw_element_data( $element ) );

		if ( ! FormLayer::should_engage( $elements ) ) {
			return;
		}

		// Exactly one purchase form is rendered per Elementor document render pass.
		// The first checkout content to reach the boundary opens the form; a second
		// box or a nested checkout box must not open another.
		if ( FormLayer::is_page_form_open() ) {
			return;
		}
		FormLayer::open_page_form();

		// Empty cart on a real front-end render: mirror the block checkout, which renders
		// the empty-cart message and NO purchase form. Claim the page's single checkout
		// slot as the empty-cart notice owner (so no other box opens a form or repeats the
		// notice) and bail before forcing a <form>, opening the wrapper, or registering the
		// section fallbacks — the notice-owning box's print_content() emits the notice.
		// The editor/preview is exempt so authors always see the box and its sections.
		if ( $this->cart_is_empty() && ! Page::is_edit_mode() ) {
			FormLayer::mark_empty_cart( $element_id );
			return;
		}

		// Record this element id as engaged at depth 0 (its own before_render) and mark
		// it as THE engaged box so its print_content() renders its children inside the
		// <form> the subscriber opens here (box-engagement bookkeeping for the dedupe path).
		$this->engaged_depth_map[ $element_id ] = 0;
		FormLayer::mark_engaged( $element_id );

		// Checkout context is resolved from the wp:edd/checkout marker via
		// Validator::has_checkout(), so no per-render filter is needed here.
		$this->register_shutdown_failsafe();

		$payment_mode = edd_get_chosen_gateway();
		$form_action  = edd_get_checkout_uri( 'payment-mode=' . $payment_mode );

		// Open the outer #edd_checkout_form_wrap div directly here (no output buffer),
		// mirroring the block checkout topology (checkout.php).
		echo FormLayer::wrap_open(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wrap_open() self-escapes id/class via esc_attr.

		\EDD\Integrations\SoftwareLicensing::remove_renewal_form();

		// Fire the block purchase form's pre-form hook once per pass, inside the
		// wrapper but before the <form> opens, matching the block's relative position
		// (checkout.php). Stripe recurring rate-limiting hooks
		// listen_for_recurring_card_errors here.
		do_action( 'edd_before_purchase_form' );

		// Open the single <form id="edd_purchase_form"> the box's children render
		// inside. This containment boundary is genuinely new for Elementor (the block
		// gets it free via its InnerBlocks $content string; Elementor renders flat),
		// so the subscriber emits the form tag itself rather than coercing the box.
		echo FormLayer::form_open( $form_action ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- form_open() self-escapes id/class via esc_attr and action via esc_url.

		// Box-scoped subtree: the ENGAGED box's own children, used by the fallback gate
		// so a complete box on the page does not suppress another box's fallback.
		// Reuse the raw data already fetched above rather than re-reading it.
		$box_elements = $elements[0]['elements'] ?? array();

		// Register the box-scoped fallbacks on edd_checkout_form_top/_bottom BEFORE
		// firing the top hook, so a missing required section is filled inside the form.
		$this->register_section_fallbacks( $element_id, $box_elements );

		// The top-of-form hooks fire from edd_elementor_checkout_box_top, inside the box, so
		// the notice and any fallback sections sit inside it rather than beside it.
	}

	/**
	 * Close the page-level purchase form: fire the bottom hook, close form + wrapper.
	 *
	 * Once the engaging element id's depth returns to 0 this fires
	 * edd_checkout_form_bottom (+ captcha) INSIDE the form via
	 * FormLayer::render_form_bottom(), echoes FormLayer::form_close(), fires
	 * edd_after_purchase_form BETWEEN the form close and the wrapper close (matching the
	 * block checkout.php), then echoes FormLayer::wrap_close().
	 *
	 * @since 3.7.0
	 *
	 * @param \Elementor\Element_Base $element The element being rendered.
	 * @return void
	 */
	public function after_render( $element ): void {
		$element_id = $this->get_element_id( $element );

		if ( '' === $element_id || ! array_key_exists( $element_id, $this->engaged_depth_map ) ) {
			return;
		}

		// A re-entrant close on a deeper level: only unwind the depth, keep engaged.
		if ( $this->engaged_depth_map[ $element_id ] > 0 ) {
			--$this->engaged_depth_map[ $element_id ];
			return;
		}

		try {
			// Fire the bottom-of-form section hook (+ captcha) INSIDE the form, after
			// the box rendered its children between the two hooks, then close the form
			// and the outer wrapper. The bottom hooks already fired inside the box, on
			// edd_elementor_checkout_box_bottom.
			echo FormLayer::form_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup, no dynamic input.

			do_action( 'edd_after_purchase_form' );

			echo FormLayer::wrap_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup, no dynamic input.
		} finally {
			// Always tear down the scoped engagement, even on a child exception.
			$this->unregister_section_fallbacks( $element_id );
			unset( $this->engaged_depth_map[ $element_id ] );
			FormLayer::clear_engaged( $element_id );
		}
	}

	/**
	 * Render everything that belongs at the top of the checkout, inside the box.
	 *
	 * The account line, the required-fields notice and any missing-section fallbacks render inside
	 * the box rather than beside it, which is what aligns them with it. The account line comes first
	 * (matching the inner-blocks checkout), so a template must keep page chrome — a heading, checkout
	 * steps — in a container BEFORE the box, not inside it.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public function render_box_top(): void {
		AccountLine::render( \EDD\Blocks\Checkout\Attributes::get() );

		// Fires edd_elementor_checkout_sections and edd_checkout_form_top.
		FormLayer::render_form_top();
	}

	/**
	 * Render everything that belongs at the bottom of the checkout, inside the box.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public function render_box_bottom(): void {
		// Fires edd_checkout_form_bottom and the captcha.
		FormLayer::render_form_bottom();
	}

	/**
	 * Register the box-scoped per-section Elementor fallback subscribers.
	 *
	 * Each fallback fills a required section that this box omits, so a partial box
	 * still renders a complete purchase form. The gate is box-scoped: it consults
	 * Page::has_widget( $section, $box_elements ) against THIS box's own subtree, so a
	 * complete box on the page never suppresses another box's fallback. The closures
	 * are stored per element id so the exact instance can be removed in after_render()
	 * (a fresh inline closure would not match remove_action()).
	 *
	 * The block section renderers echo their markup directly into the active output
	 * stream, so each is called directly at its hook point (the echoed-wrapper
	 * approach — no output buffer, matching the wrapper echoes). They are called with
	 * Attributes::get() (non-empty defaults) on purpose: the block's own
	 * UserDetails/PaymentDetails hook subscribers render nothing on the Elementor path
	 * not because of a has_block() gate but because UserDetails::render early-returns on
	 * empty $block_attributes (UserDetails.php), the arguments those subscribers receive there.
	 *
	 * @since 3.7.0
	 *
	 * @param string $element_id   The engaged box's Elementor element id.
	 * @param array  $box_elements The engaged box's own child element-data subtree.
	 * @return void
	 */
	private function register_section_fallbacks( string $element_id, array $box_elements ): void {
		if ( '' === $element_id ) {
			return;
		}

		$this->pi_fallback_cb[ $element_id ] = function () use ( $box_elements ): void {
			$section = 'edd-checkout-personal-info';
			if ( ! did_action( 'edd_elementor_checkout_sections' ) || Page::has_widget( $section, $box_elements ) ) {
				return;
			}

			// The UserDetails coordinator echoes directly, rendering the personal-info
			// fieldset AND the billing address (matching the personal-info widget's own
			// render); on an Elementor page Attributes::get() returns defaults.
			\EDD\Blocks\Checkout\Elements\UserDetails::render( \EDD\Blocks\Checkout\Attributes::get() );
		};

		$this->pay_fallback_cb[ $element_id ] = function () use ( $box_elements ): void {
			$section = 'edd-checkout-payment-info';
			if ( ! did_action( 'edd_elementor_checkout_sections' ) || Page::has_widget( $section, $box_elements ) ) {
				return;
			}

			// The renderer echoes directly; on an Elementor page Attributes::get() returns defaults.
			\EDD\Blocks\Checkout\Elements\PaymentDetails::render( \EDD\Blocks\Checkout\Attributes::get(), null );
		};

		add_action( 'edd_checkout_form_top', $this->pi_fallback_cb[ $element_id ], 10 );
		add_action( 'edd_checkout_form_bottom', $this->pay_fallback_cb[ $element_id ], 10 );
	}

	/**
	 * Remove the box-scoped fallback subscribers registered for an element id.
	 *
	 * Passes the SAME stored closure instance to remove_action() so the removal
	 * matches, then clears the stored references. Idempotent when nothing was stored.
	 *
	 * @since 3.7.0
	 *
	 * @param string $element_id The engaged box's Elementor element id.
	 * @return void
	 */
	private function unregister_section_fallbacks( string $element_id ): void {
		if ( isset( $this->pi_fallback_cb[ $element_id ] ) ) {
			remove_action( 'edd_checkout_form_top', $this->pi_fallback_cb[ $element_id ], 10 );
			unset( $this->pi_fallback_cb[ $element_id ] );
		}

		if ( isset( $this->pay_fallback_cb[ $element_id ] ) ) {
			remove_action( 'edd_checkout_form_bottom', $this->pay_fallback_cb[ $element_id ], 10 );
			unset( $this->pay_fallback_cb[ $element_id ] );
		}
	}

	/**
	 * Register a one-time shutdown action that clears any leaked engagement state.
	 *
	 * Guards against the case where a child element render throws an uncaught
	 * exception after before_render has engaged but before after_render's finally
	 * block can run. The shutdown callback iterates the per-element engaged-depth map
	 * so a leak from ANY box (not just the first) is cleared: for each leaked id it
	 * removes that id's stored personal-info and payment-info fallback closures from
	 * edd_checkout_form_top/_bottom (so a leaked fallback cannot echo stale
	 * $box_elements on a persistent worker) and clears that id from the shared
	 * FormLayer engaged-element state, then resets the page-level form-open guard
	 * (persistent-worker hardening so the next request can open a purchase form
	 * again). The callback is idempotent: if after_render already cleared everything,
	 * the maps are empty and the guard reset is a no-op. Registered at most once per
	 * request via $failsafe_registered.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	private function register_shutdown_failsafe(): void {
		if ( $this->failsafe_registered ) {
			return;
		}

		$this->failsafe_registered = true;

		$map     = &$this->engaged_depth_map;
		$pi_map  = &$this->pi_fallback_cb;
		$pay_map = &$this->pay_fallback_cb;

		add_action(
			'shutdown',
			static function () use ( &$map, &$pi_map, &$pay_map ): void {
				// Clear any box still marked engaged at shutdown (leaked render).
				foreach ( array_keys( $map ) as $element_id ) {
					// Remove that id's leaked fallback closures so they cannot echo stale box data.
					if ( isset( $pi_map[ $element_id ] ) ) {
						remove_action( 'edd_checkout_form_top', $pi_map[ $element_id ], 10 );
						unset( $pi_map[ $element_id ] );
					}

					if ( isset( $pay_map[ $element_id ] ) ) {
						remove_action( 'edd_checkout_form_bottom', $pay_map[ $element_id ], 10 );
						unset( $pay_map[ $element_id ] );
					}

					// Clear the shared engaged-element id so a persistent worker's next
					// request does not see this leaked box as still engaged.
					FormLayer::clear_engaged( $element_id );
					unset( $map[ $element_id ] );
				}

				// Clear any empty-cart notice owner so a persistent worker does not carry
				// it into the next request.
				FormLayer::clear_empty_cart();

				// Clear the per-render-pass rendered-section registry so a leaked pass
				// cannot suppress the next request's first section of each type.
				FormLayer::clear_rendered_sections();

				// Reset the page-level form-open guard so a persistent worker starts the
				// next request able to open a purchase form again.
				FormLayer::reset_page_form_open();
			}
		);
	}

	/**
	 * Whether the EDD cart is empty (no items and no fees).
	 *
	 * Mirrors the block checkout's empty-cart gate (checkout.php): an empty cart
	 * shows the empty-cart notice instead of the purchase form.
	 *
	 * @since 3.7.0
	 *
	 * @return bool True when the cart holds no items and no fees.
	 */
	private function cart_is_empty(): bool {
		return ! edd_get_cart_contents() && ! edd_cart_has_fees();
	}

	/**
	 * Resolve the Elementor element id for the map key.
	 *
	 * Prefers the raw element-data id (present for both the real Elementor node
	 * and the test fake), falling back to get_id() when available. Returns an
	 * empty string when neither is resolvable so the caller can skip engagement.
	 *
	 * @since 3.7.0
	 *
	 * @param \Elementor\Element_Base $element The element being rendered.
	 * @return string The element id, or an empty string when unresolved.
	 */
	private function get_element_id( $element ): string {
		$raw = $this->get_raw_element_data( $element );
		if ( ! empty( $raw['id'] ) ) {
			return (string) $raw['id'];
		}

		if ( method_exists( $element, 'get_id' ) ) {
			return (string) $element->get_id();
		}

		return '';
	}

	/**
	 * Build the raw element-data node for the detection helper.
	 *
	 * Elementor's Element_Base::get_raw_data() yields the node shape
	 * (elType/widgetType/elements) the Elementor-free helper expects.
	 *
	 * @since 3.7.0
	 *
	 * @param \Elementor\Element_Base $element The element being rendered.
	 * @return array The raw element-data node.
	 */
	private function get_raw_element_data( $element ): array {
		if ( ! method_exists( $element, 'get_raw_data' ) ) {
			return array();
		}

		return (array) $element->get_raw_data();
	}
}
