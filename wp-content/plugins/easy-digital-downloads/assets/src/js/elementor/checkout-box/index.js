/**
 * EDD Checkout box editor integration entry (wiring) for Elementor.
 *
 * @package     EDD\Elementor
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

import { hasRequiredInternals } from './container-utils';
import { registerCheckoutBoxType } from './element-type';
import { registerSeedHook } from './seed-hook';
import { registerMissingOverlay } from './missing-overlay';
import { registerBlockClass } from './block-class';
import { registerCustomCss } from './custom-css';
import { registerLayoutPickerControl } from './layout-picker';
import { registerDeleteLock } from './delete-lock';
import { registerDuplicateLock } from './duplicate-lock';
import { registerRequiredSectionMoveLock, registerSecondBoxNotice } from './move-lock';

/**
 * Initialize the EDD Checkout box editor integration.
 *
 * Registers the JS element type on `elementor/init-components` (before the
 * document model is built) and re-binds on `elementor/init` as a load-order
 * fallback. If the elements manager already exists when this module loads
 * (script ordering / cache), registration runs immediately. The seed hook is
 * registered on `elementor/init-components` (the SLASH form) and re-bound on
 * `elementor/init` as a load-order fallback; if `$e` is already available when
 * this module loads (late load / cache), the seed hook is registered
 * immediately. The __eddSeedHookDone guard prevents double-registration.
 *
 * @since 3.7.0
 * @return {void}
 */
const init = () => {
	globalThis.addEventListener( 'elementor/init-components', registerCheckoutBoxType );
	globalThis.addEventListener( 'elementor/init', registerCheckoutBoxType );
	globalThis.addEventListener( 'elementor/init-components', registerSeedHook );
	globalThis.addEventListener( 'elementor/init', registerSeedHook );
	globalThis.addEventListener( 'elementor/init-components', registerSecondBoxNotice );
	globalThis.addEventListener( 'elementor/init', registerSecondBoxNotice );
	globalThis.addEventListener( 'elementor/init-components', registerDeleteLock );
	globalThis.addEventListener( 'elementor/init', registerDeleteLock );
	globalThis.addEventListener( 'elementor/init-components', registerDuplicateLock );
	globalThis.addEventListener( 'elementor/init', registerDuplicateLock );
	globalThis.addEventListener( 'elementor/init-components', registerRequiredSectionMoveLock );
	globalThis.addEventListener( 'elementor/init', registerRequiredSectionMoveLock );
	globalThis.addEventListener( 'elementor/init-components', registerLayoutPickerControl );
	globalThis.addEventListener( 'elementor/init', registerLayoutPickerControl );

	// The overlay listens on plain window lifecycle events; bind it immediately.
	registerMissingOverlay();

	// The block-class bridge also listens on plain window lifecycle events; bind it immediately.
	registerBlockClass();

	// The custom-css live-preview bridge also listens on plain window lifecycle events; bind it immediately.
	registerCustomCss();

	// Load-order fallback: the manager may already exist if this script loaded late.
	if ( hasRequiredInternals() ) {
		registerCheckoutBoxType();
	}

	// Seed-hook / delete-lock load-order fallback: $e may already be available if
	// this script loaded after elementor/init fired (late load / cache). The
	// __eddSeedHookDone / __eddDeleteLockDone guards prevent double-registration.
	if ( globalThis.$e ) {
		registerSeedHook();
		registerSecondBoxNotice();
		registerDeleteLock();
		registerDuplicateLock();
		registerRequiredSectionMoveLock();
	}

	// The layout-picker control view only needs elementor.addControlView; register it
	// immediately if elementor is already available (late load / cache).
	if ( typeof globalThis.elementor?.addControlView === 'function' ) {
		registerLayoutPickerControl();
	}
};

export default init;
