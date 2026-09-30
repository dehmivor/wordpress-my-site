<?php
/**
 * Section-Render Guard Concern for EDD Checkout Inner Widgets
 *
 * Shared trait providing the two front-end render gates each checkout section
 * widget consults, in this order, before it renders:
 *
 *   1. is_render_inert() — the render-inert backstop. A section widget rendered
 *      OUTSIDE an engaged checkout box (no box is engaged for the current render
 *      pass) renders NOTHING. This closes the standalone/loose-widget vectors the
 *      panel-hide cannot (copy/paste, template/API import, a pre-existing page, a
 *      drag out of the box): a loose section must never emit a gateway selector or
 *      #edd_purchase_form_wrap, since gateway JS (PayPal, etc.) does
 *      getElementById( 'edd_purchase_form' ) then scopes into it, and a widget with
 *      no wrapping form has nothing to scope to.
 *   2. is_duplicate_section() — the duplicate-section dedupe. Applies ONLY when a
 *      box IS engaged: the native Elementor Container imposes no cardinality, so an
 *      author (or an imported layout) can place two payment-info sections in one
 *      box; the second would emit a duplicate gateway selector, a second
 *      #edd_purchase_form_wrap, and duplicated field ids that break the gateway
 *      switch. The FIRST instance of a type claims its per-render-pass slot and
 *      renders; any later same-type instance renders nothing.
 *
 * The two gates branch on FormLayer::is_box_engaged() in OPPOSITE senses (inert
 * bails when NO box is engaged; dedupe only acts when a box IS engaged), so the
 * inert check MUST run first — a loose widget short-circuits before it can ever
 * consult or claim a slot in the dedupe registry. The registry is per-render-pass
 * and lives on FormLayer, cleared between passes and requests alongside the other
 * render-pass markers. The Elementor editor/preview is exempt from BOTH gates so
 * authors always see and can edit (or delete) every instance they placed.
 *
 * @package     EDD\Elementor\Widgets\CheckoutInner\Concerns
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Elementor\Widgets\CheckoutInner\Concerns;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Elementor\Checkout\FormLayer;
use EDD\Elementor\Utils\Page;

/**
 * SectionGuard trait.
 *
 * Two front-end render gates for a checkout section widget: the render-inert
 * backstop (a section outside an engaged box renders nothing) and the
 * duplicate-section dedupe (a second same-type section inside an engaged box
 * renders nothing). Editor/preview renders are always allowed so every instance
 * stays visible and editable.
 *
 * @since 3.7.0
 */
trait SectionGuard {

	/**
	 * Whether this section render must be inert (render nothing) — the backstop.
	 *
	 * Returns false in the Elementor editor/preview so every instance renders and
	 * stays editable. On the front end it returns true when NO checkout box is
	 * engaged for the current render pass: a section widget rendered outside an
	 * engaged box (placed standalone via copy/paste, template/API import, a
	 * pre-existing page, or dragged out of the box) has no purchase form to live in,
	 * so it must render nothing rather than emit a gateway selector or
	 * #edd_purchase_form_wrap with no form around it: gateway JS (PayPal, etc.)
	 * does getElementById( 'edd_purchase_form' ) then scopes into it, and a widget
	 * with no wrapping form has nothing to scope to. Each widget's render() calls
	 * this FIRST, ahead of
	 * is_duplicate_section(): the two gates read FormLayer::is_box_engaged() in
	 * opposite senses, so a loose widget must bail here before it could consult or
	 * claim a dedupe slot it should never touch.
	 *
	 * @since 3.7.0
	 *
	 * @return bool True when this render must emit nothing.
	 */
	protected function is_render_inert(): bool {
		if ( Page::is_edit_mode() ) {
			return false;
		}

		return ! FormLayer::is_box_engaged();
	}

	/**
	 * Whether this section widget render is a duplicate that must be skipped.
	 *
	 * Returns false in the Elementor editor/preview so every instance renders
	 * (authors must see and be able to delete a duplicate). The dedupe applies ONLY
	 * while a checkout box is actively engaged (rendering its own children): a
	 * section widget rendered standalone — before, after, or outside any engaged box
	 * (the section widget carries the generic `edd` category and can be placed
	 * anywhere) — renders normally and never claims a type slot, so it cannot consume
	 * the box's real same-type section's slot and silently blank the checkout. Inside
	 * the engaged box it consults the per-render-pass FormLayer registry: the first
	 * instance of this widget's type claims the slot and renders; any later same-type
	 * instance returns true here so its render() short-circuits, emitting no duplicate
	 * markup or field ids.
	 *
	 * @since 3.7.0
	 *
	 * @return bool True when this render is a duplicate and should be skipped.
	 */
	protected function is_duplicate_section(): bool {
		if ( Page::is_edit_mode() ) {
			return false;
		}

		// Dedupe only inside an actively-engaged checkout box. A section widget
		// rendered when no box is engaged is never a tracked duplicate and must not
		// claim a slot.
		if ( ! FormLayer::is_box_engaged() ) {
			return false;
		}

		return ! FormLayer::claim_section_render( $this->get_name() );
	}
}
