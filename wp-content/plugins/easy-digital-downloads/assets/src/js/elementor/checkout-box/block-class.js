/**
 * EDD Checkout box block-style class bridge for the Elementor editor.
 *
 * The checkout block's gateway/form CSS is scoped under `.wp-block-edd-checkout`, and
 * the core input/field styling under `.wp-block-edd-checkout #edd_purchase_form`. On
 * the frontend the render-time wrapper (FormLayer::wrap_open()/form_open()) supplies
 * that outer `#edd_checkout_form_wrap` wrapper and the inner `<form id="edd_purchase_form">`,
 * so the scoped styles reach the box's inner section widgets. In the editor the box is a
 * Container subclass rendered client-side, so neither is emitted: the inner widgets fall
 * outside `.wp-block-edd-checkout` (gateways render as raw radios) and outside
 * `#edd_purchase_form` (the whole forms partial never matches, so inputs are unstyled).
 * This module re-creates just those anchors on the box's own preview DOM — the wrapper
 * anchors on the outer `.e-con`, the form id on the inner `.e-con-inner` that holds the
 * widgets — so the two-level selectors resolve exactly as on the frontend. It touches only
 * the live editor DOM (never the element model/settings), so the saved document and the
 * frontend DOM are unchanged.
 *
 * @package     EDD\Elementor
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

import { findBoxView } from './container-utils';

/**
 * The wrapper classes the frontend #edd_checkout_form_wrap carries (FormLayer::wrap_open()).
 *
 * Only the two the block form/gateway CSS is actually keyed to are mirrored:
 * `wp-block-edd-checkout` (the block root the scoped styles hang off) and
 * `edd-checkout--elementor` (the Elementor-only variant selectors).
 *
 * @since 3.7.0
 * @type {string[]}
 */
const WRAP_CLASSES = [ 'wp-block-edd-checkout', 'edd-checkout--elementor' ];

/**
 * The wrapper and form ids the frontend render supplies (FormLayer::WRAP_ID / FORM_ID).
 *
 * The forms partial is scoped under `.wp-block-edd-checkout #edd_purchase_form`, so the
 * outer element takes the wrapper id and the inner (a descendant of it) the form id.
 *
 * @since 3.7.0
 * @type {string}
 */
const WRAP_ID = 'edd_checkout_form_wrap';
const FORM_ID = 'edd_purchase_form';

/**
 * Re-create the frontend form-wrapper anchors on the box's editor preview element.
 *
 * Runs on any element render, resolving the enclosing checkout box via findBoxView
 * (which only matches views whose elType is the checkout box), so the anchors land on
 * the box and never on a plain container. The outer `.e-con` takes the wrapper classes
 * and id; its inner `.e-con-inner` (the descendant that actually holds the section
 * widgets) takes the form id, so `.wp-block-edd-checkout #edd_purchase_form` resolves as
 * a descendant selector just like the frontend. Each write is idempotent — `classList.add`
 * no-ops when present, and the ids are only set when the element has none, so a user's own
 * CSS id is never clobbered and repeated renders never conflict.
 *
 * @since 3.7.0
 * @param {CustomEvent} event The lifecycle event carrying `detail.elementView`.
 * @return {void}
 */
const onElementRendered = ( event ) => {
	const view = event?.detail?.elementView;
	if ( ! view ) {
		return;
	}

	const el = findBoxView( view )?.$el?.[ 0 ];
	if ( ! el ) {
		return;
	}

	el.classList.add( ...WRAP_CLASSES );
	if ( ! el.id ) {
		el.id = WRAP_ID;
	}

	const inner = el.querySelector( ':scope > .e-con-inner' );
	if ( inner && ! inner.id ) {
		inner.id = FORM_ID;
	}
};

/**
 * Bind the block-class bridge to element render events.
 *
 * Listens for the Elementor editor `element-rendered` event (dispatched on the top
 * window) so the box picks up the block wrapper class whenever it — or one of its
 * children — renders in the preview. The __eddBlockClassBound guard prevents
 * double-binding.
 *
 * @since 3.7.0
 * @return {void}
 */
const registerBlockClass = () => {
	if ( globalThis.__eddBlockClassBound ) {
		return;
	}

	globalThis.addEventListener( 'elementor/editor/element-rendered', onElementRendered );
	globalThis.__eddBlockClassBound = true;
};

export { registerBlockClass };
