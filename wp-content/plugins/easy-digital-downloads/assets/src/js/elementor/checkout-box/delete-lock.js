/**
 * EDD Checkout box required-section delete-lock for Elementor.
 *
 * @package     EDD\Elementor
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

import { EDD_TYPE, REQUIRED_WIDGETS } from './patterns';
import { findEnclosingBox, boxContainsType } from './container-utils';
import { isSeeding } from './pattern-seeding';

/**
 * Whether the current command is running as a half of a decomposed move.
 *
 * In Elementor 4.x a `document/elements/move` (a drag-reorder) is NOT atomic: its
 * apply() decomposes into a `document/elements/delete` followed by a
 * `document/elements/create` for each moved element. Both halves run with the move
 * command on the command trace, so `isCurrentFirstTrace('document/elements/move')`
 * is true for the delete-half and the create-half, but false for a standalone
 * (user-initiated) delete or create whose trace is empty. The delete-lock and the
 * create dup-guard use this to let an intra-box reorder pass while still vetoing a
 * true delete or a true duplicate. Move-out of a required section is vetoed
 * separately, at the move command level (registerRequiredSectionMoveLock), before
 * these decomposed halves ever run.
 *
 * @since 3.7.0
 * @return {boolean} True when the current delete/create is a move-decomposition half.
 */
const isMoveDecomposition = () => Boolean(
	globalThis.$e?.commands?.isCurrentFirstTrace?.( 'document/elements/move' )
);

/**
 * Register the delete-lock that blocks removal of required section widgets.
 *
 * Implemented as a `document/elements/delete` DATA Dependency hook — NOT a UI
 * Before hook, and it VETOES BY RETURNING false (it must never throw itself).
 *
 * Two Elementor 4.x internals dictate this:
 *   1. UI Before hooks run OUTSIDE the command runner's try/catch
 *      (Commands.runInstance: onBeforeRun runs before the try), so a HookBreak
 *      thrown from a UI Before hook escapes UNCAUGHT ("Uncaught Error: HookBreak")
 *      with no notification. Data (dependency) hooks run INSIDE the try
 *      (onBeforeApply -> runDataDependency), so their veto is caught cleanly.
 *   2. The dependency runner is the ONLY safe place to signal a veto: on a
 *      falsy return it decrements its per-hook recursion-depth counter and THEN
 *      throws its own HookBreak (see core/hooks/data.js `runCallback`, case
 *      'dependency'). If this hook threw HookBreak itself, the throw would unwind
 *      through the runner BEFORE that `depth--`, leaving the counter stuck at 1 —
 *      the runner's `1 === depth` recursion guard then SKIPS the hook on every
 *      later delete, so a second delete (e.g. via the Structure/Navigator panel
 *      after a first blocked attempt) would silently succeed. Returning false lets
 *      the runner balance the depth and throw, so the veto holds on EVERY delete
 *      path (canvas keyboard/context-menu, remove button, Navigator) repeatedly.
 *
 * Scoped to the two required sections: the veto fires when the delete target IS a
 * required section widget OR is a container inside a checkout box whose subtree
 * still holds a required section (so deleting the LEFT column that seeds
 * personal-info + payment-info cannot bypass the lock by removing them in one
 * command). Deleting the box itself, a non-required section (cart / discount-form),
 * or an empty/non-required container stays allowed; a delete outside a box is
 * unaffected. The runner's HookBreak carries no user-facing notification, so the
 * message is shown via Elementor's toast (elementor.notifications.showToast)
 * before the veto returns.
 *
 * Reorder exemption: a drag-reorder fires `document/elements/move`, which
 * decomposes into a delete-half + a create-half (Elementor 4.x — the move is not
 * atomic). The delete-half of a reorder must NOT be vetoed, or a required section
 * could never be reordered. getConditions therefore skips the veto when the delete
 * is a move-decomposition half (isMoveDecomposition) — a standalone user delete has
 * an empty trace and is still vetoed. Moving a required section OUT of its box is
 * blocked ahead of the decomposition by registerRequiredSectionMoveLock, so this
 * exemption never lets a required section escape its box.
 *
 * Seeding/switch exemption: a layout-pattern switch deletes the box's current
 * children (including the required sections) before re-seeding the chosen pattern,
 * all under the programmatic-replace flag (`eddSeeding`, set by withSeedingFlag).
 * getConditions skips the veto while that flag is set so the switch's delete-half
 * passes, matching the dup-lock create guard which already short-circuits on the
 * same flag. The flag is cleared in a `finally`, so a plain user delete afterwards
 * is still vetoed.
 *
 * @since 3.7.0
 * @return {boolean} True when the hook is registered (or already was).
 */
const registerDeleteLock = () => {
	const commands = globalThis.$e;

	if ( ! commands?.modules?.hookData?.Dependency || typeof commands.hooks?.registerDataDependency !== 'function' ) {
		return false;
	}

	if ( globalThis.__eddDeleteLockDone ) {
		return true;
	}

	const DependencyHook = commands.modules.hookData.Dependency;

	// Read the target widgetType(s) for a delete command's container(s).
	const targetIsProtected = ( container ) => {
		if ( ! container ) {
			return false;
		}

		// The target IS a required section widget.
		const widgetType = container.model?.get?.( 'widgetType' ) ?? '';
		if ( REQUIRED_WIDGETS.includes( widgetType ) ) {
			return true;
		}

		// The box itself is deletable — only its inner content is protected.
		if ( EDD_TYPE === container.model?.get?.( 'elType' ) ) {
			return false;
		}

		// A CONTAINER (e.g. an inner column) inside a box whose subtree holds a
		// required section: deleting it would remove the required section(s) in ONE
		// un-vetoed command, bypassing the widget-level lock (a container has no
		// widgetType). boxContainsType walks the container's own descendants, so a
		// required section nested at any depth below the container is caught.
		if ( ! findEnclosingBox( container ) ) {
			return false;
		}

		return REQUIRED_WIDGETS.some( ( requiredType ) => boxContainsType( container, requiredType ) );
	};

	// The delete target(s) that must be vetoed — required section widgets and any
	// container inside a box whose subtree still holds a required section.
	const lockedTargets = ( args ) => {
		const containers = args?.containers ?? ( args?.container ? [ args.container ] : [] );

		return containers.filter( targetIsProtected );
	};

	class DeleteLockHook extends DependencyHook {
		getCommand() {
			return 'document/elements/delete';
		}

		getId() {
			return 'edd-checkout-box-delete-lock';
		}

		getConditions( args ) {
			// A programmatic pattern switch deletes the box's current children under
			// the seeding flag before re-seeding the new pattern; those deletes must
			// pass. The delete-half of a drag-reorder must pass too. A standalone
			// (true) user delete of a required section — no flag, empty trace — is
			// still vetoed.
			if ( isSeeding() || isMoveDecomposition() ) {
				return false;
			}

			return lockedTargets( args ).length > 0;
		}

		apply() {
			const i18n = globalThis.wp?.i18n;
			const message = i18n
				? i18n.__( 'This checkout section is required and cannot be deleted.', 'easy-digital-downloads' )
				: 'This checkout section is required and cannot be deleted.';

			// Show the reason, then veto by returning false. The dependency runner
			// turns this false into a depth-balanced HookBreak, so the veto is clean
			// (no uncaught error) AND repeatable. Do NOT throw here — see docblock.
			globalThis.elementor?.notifications?.showToast?.( { message } );

			return false;
		}
	}

	commands.hooks.registerDataDependency( new DeleteLockHook() );
	globalThis.__eddDeleteLockDone = true;

	return true;
};

export { registerDeleteLock, isMoveDecomposition };
