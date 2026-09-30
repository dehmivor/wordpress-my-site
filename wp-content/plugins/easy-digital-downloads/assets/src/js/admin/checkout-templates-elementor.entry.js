/**
 * Checkout Templates - Elementor Editor Button
 *
 * Injects a "Browse Checkout Templates" button into Elementor's editor
 * panel header when editing the EDD checkout page.
 *
 * @package EDD
 * @copyright Copyright (c) 2026, Sandhills Development, LLC
 * @license   https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since     3.7.0
 */

'use strict';

/**
 * Attempt to locate the correct Elementor UI container and insert the button.
 *
 * The function is idempotent: it exits immediately when the button is already
 * present, so it is safe to call multiple times (e.g. from multiple events or
 * timers).
 */
const addEDDTemplatesButton = () => {
	// Exit early if button is already present.
	if ( document.querySelector( '.edd-elementor-templates-button' ) ) {
		return;
	}

	// Resolve the localised string data injected by wp_add_inline_script.
	const i18n = globalThis.eddElementorEditorI18n || {};
	const labelBrowse  = i18n.browse  || 'Browse Checkout Templates';
	const labelButton  = i18n.button  || 'EDD Checkout Templates';

	// Try multiple locations in order of preference:
	// 1. Panel header menu area (next to title)
	// 2. Editor top bar tools
	// 3. Panel header itself
	let targetContainer = null;
	let insertMethod    = 'append';

	// 1. Try panel header menu area first.
	const panelHeaderMenu = document.querySelector( '#elementor-panel-header-menu-button' );
	if ( panelHeaderMenu?.parentElement ) {
		targetContainer = panelHeaderMenu.parentElement;
		insertMethod    = 'before-menu';
	}

	// 2. Try editor top bar tools.
	if ( ! targetContainer ) {
		const topBarTools = document.querySelector( '#elementor-editor-wrapper-v2 .MuiStack-root' );
		if ( topBarTools ) {
			targetContainer = topBarTools;
			insertMethod    = 'prepend';
		}
	}

	// 3. Fallback to panel header.
	if ( ! targetContainer ) {
		targetContainer = document.querySelector( '#elementor-panel-header' );
		insertMethod    = 'append';
	}

	if ( ! targetContainer ) {
		return;
	}

	// Build the button element.
	const button = document.createElement( 'button' );
	button.className = 'edd-elementor-templates-button';
	button.setAttribute( 'title',      labelBrowse );
	button.setAttribute( 'aria-label', labelBrowse );
	button.innerHTML =
		'<i class="eicon-single-page" aria-hidden="true"></i>' +
		'<span class="edd-elementor-templates-button__label">' + labelButton + '</span>';

	button.addEventListener( 'click', ( e ) => {
		e.preventDefault();
		e.stopPropagation();

		// Signal the React app to open the template browser modal.
		document.dispatchEvent( new CustomEvent( 'edd-open-checkout-templates-modal' ) );
	} );

	// Insert at the resolved position.
	if ( 'before-menu' === insertMethod && panelHeaderMenu ) {
		panelHeaderMenu.before( button );
	} else if ( 'prepend' === insertMethod ) {
		targetContainer.insertBefore( button, targetContainer.firstChild );
	} else {
		targetContainer.appendChild( button );
	}
};

/**
 * Register all init strategies and bind Elementor lifecycle events.
 */
const initEDDButton = () => {
	// Attempt an immediate insert (Elementor may already be ready).
	addEDDTemplatesButton();

	// Bind to Elementor's own lifecycle events when the object is available.
	if ( globalThis.elementor !== undefined ) {
		globalThis.elementor.on( 'document:loaded', addEDDTemplatesButton );
		globalThis.elementor.on( 'panel:init',      addEDDTemplatesButton );
	}
};

// Bootstrap: run now if Elementor is already defined, otherwise wait for its
// init event.  Two safety timers cover async panel loading on slow connections;
// addEDDTemplatesButton is idempotent so duplicate calls are harmless.
if ( globalThis.elementor !== undefined ) {
	initEDDButton();
} else {
	document.addEventListener( 'elementor:init', initEDDButton );
}

// 2 000 ms covers most standard load times on typical hosting.
// 5 000 ms provides an additional fallback for slow connections or heavy pages.
setTimeout( addEDDTemplatesButton, 2000 );
setTimeout( addEDDTemplatesButton, 5000 );
