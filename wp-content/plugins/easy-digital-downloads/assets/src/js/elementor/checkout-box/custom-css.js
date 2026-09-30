/**
 * EDD Checkout box Custom CSS live-preview bridge for the Elementor editor.
 *
 * The checkout box's Custom CSS control (`edd_custom_css`) is emitted verbatim by
 * PHP inside a single <style> block on each front-end / preview render branch (see
 * CheckoutBox::print_custom_css()). That server emission only refreshes when the
 * preview re-renders, so typing in the control does not update the preview live.
 * This module mirrors the PHP transform in JS and injects/updates a single
 * <style id="edd-checkout-custom-css"> node in the preview iframe <head> whenever
 * the box renders, so authored CSS reflects in the editor preview immediately. It
 * touches only the live preview document (never the element model/settings), so the
 * saved document and the frontend DOM are unchanged — the frontend keeps using the
 * PHP-emitted <style>.
 *
 * @package     EDD\Elementor
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

import { findBoxView } from './container-utils';

/**
 * The id of the single <style> node injected into the preview iframe <head>.
 *
 * The front-end PHP emits its style block with a `class` of edd-checkout-custom-css;
 * the editor preview uses an `id` so this module can find and update exactly one node
 * (no duplicates) without colliding with the PHP-rendered element.
 *
 * @since 3.7.0
 * @type {string}
 */
const STYLE_ID = 'edd-checkout-custom-css';

/**
 * Decode HTML entities in the stored Custom CSS value.
 *
 * Elementor's Document::save() runs the element tree through wp_kses_post() for any
 * user lacking the unfiltered_html capability, which rewrites CSS combinators such as
 * `>` to `&gt;`; the PHP entity-decodes before stripping <style> tags so combinators
 * survive. This mirrors that decode using a detached <textarea>: assigning markup to
 * `.innerHTML` and reading `.value` decodes entities WITHOUT executing anything (a
 * textarea's content is parsed as text, not live markup), so it is breakout-safe.
 *
 * @since 3.7.0
 * @param {string} value The raw control value.
 * @param {Document} doc The document used to create the detached decoder element.
 * @return {string} The entity-decoded value.
 */
const decodeEntities = ( value, doc ) => {
	const textarea = ( doc ?? globalThis.document ).createElement( 'textarea' );
	textarea.innerHTML = value;
	return textarea.value;
};

/**
 * Neutralize any <style> start/end tags in a decoded CSS value.
 *
 * Mirrors CheckoutBox::print_custom_css() IN ORDER: entities are decoded first (by
 * the caller), THEN <style> tags are stripped, looping until stable. Stripping the
 * full end tag (including its optional trailing >) prevents the value from breaking
 * out of the injected style element, and stripping the start tag lets an author paste
 * CSS with or without a surrounding <style> wrapper. A decoded </style> is still
 * neutralized because this strip runs after the decode — the same invariant as the
 * PHP. Valid CSS never contains a complete <style ...> or </style> tag, so legitimate
 * input is unaffected.
 *
 * @since 3.7.0
 * @param {string} value The entity-decoded control value.
 * @return {string} The value with all <style> tags removed.
 */
const stripStyleTags = ( value ) => {
	let css = value;
	let before;

	do {
		before = css;
		css = css.replace( /<\/\s*style\s*>?/gi, '' );
		css = css.replace( /<\s*style\b[^>]*>/gi, '' );
	} while ( css !== before );

	return css;
};

/**
 * Inject or update the single Custom CSS <style> node in the preview <head>.
 *
 * Reads the box's `edd_custom_css` edit-model setting, mirrors the PHP transform
 * (decode entities, then strip <style> tags) and assigns the result to a single
 * <style id="edd-checkout-custom-css"> node in the preview iframe document via
 * `.textContent` (breakout-safe — never innerHTML). The node is created once and
 * updated in place on later renders (no duplicates); an empty value removes it.
 *
 * @since 3.7.0
 * @param {Object} boxView The checkout box element view (has `$el` in the preview frame).
 * @return {void}
 */
const renderCustomCss = ( boxView ) => {
	const el = boxView?.$el?.[ 0 ];
	if ( ! el ) {
		return;
	}

	const doc = el.ownerDocument ?? globalThis.document;
	const head = doc.head;
	if ( ! head ) {
		return;
	}

	const model = boxView.getEditModel?.() ?? boxView.model;
	const raw = model?.get?.( 'settings' )?.get?.( 'edd_custom_css' ) ?? '';

	let css = '';
	if ( typeof raw === 'string' && '' !== raw.trim() ) {
		css = stripStyleTags( decodeEntities( raw, doc ) );
	}

	const existing = doc.getElementById( STYLE_ID );

	// An empty (or fully-stripped) value emits nothing — mirror PHP and remove the node.
	if ( '' === css.trim() ) {
		existing?.remove();
		return;
	}

	let node = existing;
	if ( ! node ) {
		node = doc.createElement( 'style' );
		node.id = STYLE_ID;
		head.appendChild( node );
	}

	node.textContent = css;
};

/**
 * Handle an Elementor element render event for the enclosing checkout box.
 *
 * Runs on any element render, resolving the enclosing checkout box via findBoxView
 * (which only matches the checkout-box elType), so the preview CSS refreshes whenever
 * the box — or one of its children — renders.
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

	const boxView = findBoxView( view );
	if ( boxView ) {
		renderCustomCss( boxView );
	}
};

/**
 * Bind the Custom CSS live-preview bridge to element render events.
 *
 * Listens for the Elementor editor `element-rendered` event (dispatched on the top
 * window) so the injected preview <style> refreshes whenever the box or a child
 * renders. The __eddCustomCssBound guard prevents double-binding.
 *
 * @since 3.7.0
 * @return {void}
 */
const registerCustomCss = () => {
	if ( globalThis.__eddCustomCssBound ) {
		return;
	}

	globalThis.addEventListener( 'elementor/editor/element-rendered', onElementRendered );
	globalThis.__eddCustomCssBound = true;
};

export { registerCustomCss };
