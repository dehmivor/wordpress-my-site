/**
 * EDD Checkout box after-create seed hook for Elementor.
 *
 * @package     EDD\Elementor
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

import { EDD_TYPE, DEFAULT_PATTERN, findPattern } from './patterns';
import { isSeeding, withSeedingFlag, seedPattern } from './pattern-seeding';

/**
 * Register the after-create hook that auto-seeds the default layout pattern.
 *
 * The hook fires after `document/elements/create` for our elType. It seeds the
 * default `single-column` pattern (cart, personal-info, payment-info) into the
 * freshly added box via seedPattern (each element created as its own command so
 * every section becomes a real, selectable element). The author then switches to
 * any of the five patterns from the layout picker.
 *
 * It is run once per editor session (`__eddSeedHookDone`), recursion-guarded
 * (`eddSeeding`, via withSeedingFlag — set while it issues its own create commands
 * so the dedup guards and this hook do not re-enter), and skips any box that
 * already has children (paste/undo/reload). Seeded containers have elType
 * `container`, not our elType, so this hook never re-fires for them.
 *
 * @since 3.7.0
 * @return {boolean} True when the hook is registered (or already was).
 */
const registerSeedHook = () => {
	const commands = globalThis.$e;

	if ( ! commands?.modules?.hookUI?.After ) {
		return false;
	}

	if ( globalThis.__eddSeedHookDone ) {
		return true;
	}

	const AfterHook = commands.modules.hookUI.After;

	class SeedHook extends AfterHook {
		getCommand() {
			return 'document/elements/create';
		}

		getId() {
			return 'edd-checkout-box-seed';
		}

		getConditions( args ) {
			return ! isSeeding() && Boolean( args?.model?.elType === EDD_TYPE );
		}

		apply( args, result ) {
			const container = Array.isArray( result ) ? result[ 0 ] : result;
			if ( ! container?.model ) {
				return;
			}

			const children = container.model.get( 'elements' );
			if ( children?.length ) {
				return;
			}

			withSeedingFlag( () => {
				seedPattern( commands, container, findPattern( DEFAULT_PATTERN ) );
			} );
		}
	}

	commands.hooks.registerUIAfter( new SeedHook() );
	globalThis.__eddSeedHookDone = true;

	return true;
};

export { registerSeedHook };
