/**
 * EDD Checkout box missing-required-section preview overlay for Elementor.
 *
 * @package     EDD\Elementor
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

import { requiredLabels } from './patterns';
import { getMissingRequired, findBoxView, pickerBox } from './container-utils';
import { getActivePickerView, syncSectionToggles } from './layout-picker';

/**
 * The CSS class used for the missing-section overlay injected into a box preview.
 *
 * @since 3.7.0
 * @type {string}
 */
const OVERLAY_CLASS = 'edd-checkout-box-missing-overlay';

/**
 * Render or update the missing-section overlay inside a box preview.
 *
 * Injects (or refreshes) an overlay div into the box view's preview element
 * naming the specific missing required section(s). When both required sections
 * are present the overlay is removed entirely.
 *
 * @since 3.7.0
 * @param {Object} view The box element view (has `$el` in the preview frame).
 * @return {void}
 */
const renderMissingOverlay = ( view ) => {
	const $el = view?.$el;
	if ( ! $el || typeof $el.find !== 'function' ) {
		return;
	}

	const missing = getMissingRequired( view.getContainer?.() ?? view.container ?? { model: view.model } );
	const existing = $el.find( `.${ OVERLAY_CLASS }` );

	// All required sections present: remove the overlay entirely.
	if ( ! missing.length ) {
		existing.remove();
		return;
	}

	const labels = requiredLabels();
	const names = missing.map( ( widgetType ) => labels[ widgetType ] ?? widgetType ).join( ', ' );
	const i18n = globalThis.wp?.i18n;
	const message = i18n
		? i18n.sprintf(
			/* translators: %s: comma-separated list of missing checkout section names. */
			i18n.__( 'This checkout box is missing required sections: %s. They will be added automatically on the live page.', 'easy-digital-downloads' ),
			names
		)
		: `This checkout box is missing required sections: ${ names }.`;

	const doc = $el[ 0 ]?.ownerDocument ?? globalThis.document;
	let node = existing[ 0 ];
	if ( ! node ) {
		node = doc.createElement( 'div' );
		node.className = OVERLAY_CLASS;
		node.style.cssText = 'position:relative;z-index:1;margin:8px;padding:10px 14px;border-left:4px solid #b32d2e;background:#fcf0f1;color:#3c434a;font-size:13px;line-height:1.5;border-radius:2px;';
		$el[ 0 ].insertBefore( node, $el[ 0 ].firstChild );
	}
	node.textContent = message;
};

/**
 * Handle an Elementor element lifecycle event (render or destroy).
 *
 * Refreshes the missing-section overlay for the checkout box that owns the affected
 * element, and — when that box's options panel is open — re-syncs the box's native
 * section switchers so each slider stays in step with its section's presence as child
 * sections are added/removed. Runs on child add/remove and on the box's own render.
 *
 * @since 3.7.0
 * @param {CustomEvent} event The lifecycle event carrying `detail.elementView`.
 * @return {void}
 */
const onElementLifecycle = ( event ) => {
	const view = event?.detail?.elementView;
	if ( ! view ) {
		return;
	}

	const boxView = findBoxView( view );
	if ( boxView ) {
		renderMissingOverlay( boxView );
	}

	// Re-sync the open box's native section switchers so each slider reflects a section
	// just added/removed (e.g. a canvas delete of the cart, or a pattern switch) — the
	// presence-driven half of the switcher wiring. Driven off the ACTIVE picker's box,
	// not findBoxView( view ): a destroyed element's parent chain is already severed by
	// the time element-destroyed fires, so findBoxView would return null and the sync
	// would never run for a delete. The active picker's box is the one whose panel (and
	// therefore switchers) is open, so re-syncing it on any lifecycle event keeps the
	// sliders honest regardless of which element the event targeted.
	if ( getActivePickerView() ) {
		syncSectionToggles( pickerBox( getActivePickerView() ) );
	}
};

/**
 * Bind the missing-section overlay to element lifecycle events.
 *
 * Listens for the Elementor editor `element-rendered` / `element-destroyed`
 * events (dispatched on the top window) so the overlay recomputes whenever a
 * child section is added or removed from a checkout box.
 *
 * @since 3.7.0
 * @return {void}
 */
const registerMissingOverlay = () => {
	if ( globalThis.__eddOverlayBound ) {
		return;
	}

	globalThis.addEventListener( 'elementor/editor/element-rendered', onElementLifecycle );
	globalThis.addEventListener( 'elementor/editor/element-destroyed', onElementLifecycle );
	globalThis.__eddOverlayBound = true;
};

export { registerMissingOverlay };
