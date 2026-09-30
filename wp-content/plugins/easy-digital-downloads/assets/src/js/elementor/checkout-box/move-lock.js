/**
 * EDD Checkout box required-section move-out lock and second-box notice for Elementor.
 *
 * @package     EDD\Elementor
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

import { EDD_TYPE, REQUIRED_WIDGETS } from './patterns';
import { boxContainsType, findEnclosingBox, argContainers, containerId, rootContainer, countCheckoutBoxes } from './container-utils';
import { isSeeding } from './pattern-seeding';

/**
 * The notice shown when an author adds a SECOND checkout box to a document.
 *
 * Exactly one purchase form renders per page (the front-end one-form guard), so a
 * second box adds no working checkout — this discourages it. Built with the same
 * i18n-literal-plus-fallback shape as the other toast messages so the string
 * extractor picks it up statically and it still reads when wp.i18n is unavailable.
 *
 * @since 3.7.0
 * @return {string} The translated (or fallback) notice message.
 */
const secondBoxMessage = () => {
	const i18n = globalThis.wp?.i18n;

	return i18n
		? i18n.__( 'This page already has an EDD Checkout box. Only one checkout renders per page, so a second box has no effect.', 'easy-digital-downloads' )
		: 'This page already has an EDD Checkout box. Only one checkout renders per page, so a second box has no effect.';
};

/**
 * Register the after-create hook that warns when a SECOND checkout box is added.
 *
 * Fires after `document/elements/create` for our elType (mirroring the seed
 * hook's command + condition). After the box is in the document tree it counts the
 * document's checkout boxes; when more than one exists the just-added box is a
 * second box, so it shows a discouraging toast via the same notification mechanism
 * the delete-lock and dup-lock use. Editor-only UX — it does NOT block the create
 * and does NOT touch the front-end one-form guard.
 *
 * @since 3.7.0
 * @return {boolean} True when the hook is registered (or already was).
 */
const registerSecondBoxNotice = () => {
	const commands = globalThis.$e;

	if ( ! commands?.modules?.hookUI?.After ) {
		return false;
	}

	if ( globalThis.__eddSecondBoxNoticeDone ) {
		return true;
	}

	const AfterHook = commands.modules.hookUI.After;

	class SecondBoxNoticeHook extends AfterHook {
		getCommand() {
			return 'document/elements/create';
		}

		getId() {
			return 'edd-checkout-box-second-notice';
		}

		getConditions( args ) {
			return ! isSeeding() && Boolean( args?.model?.elType === EDD_TYPE );
		}

		apply( args, result ) {
			const container = Array.isArray( result ) ? result[ 0 ] : result;
			if ( ! container?.model ) {
				return;
			}

			const root = rootContainer( container );
			if ( countCheckoutBoxes( root?.model?.get?.( 'elements' ) ) > 1 ) {
				globalThis.elementor?.notifications?.showToast?.( { message: secondBoxMessage() } );
			}
		}
	}

	commands.hooks.registerUIAfter( new SecondBoxNoticeHook() );
	globalThis.__eddSecondBoxNoticeDone = true;

	return true;
};

/**
 * Whether a container is, or contains, a required checkout section.
 *
 * True when the container itself is a required section widget, or when its subtree
 * holds one at any depth (boxContainsType recurses). Mirrors the delete-lock's
 * targetIsProtected core test, minus the box-membership checks, so the move-out
 * veto catches both a bare required widget and an inner column that seeds them.
 *
 * @since 3.7.0
 * @param {Object} container The moved container to test.
 * @return {boolean} True when the container is or holds a required section.
 */
const subtreeHasRequired = ( container ) => {
	const widgetType = container?.model?.get?.( 'widgetType' ) ?? '';
	if ( REQUIRED_WIDGETS.includes( widgetType ) ) {
		return true;
	}

	return REQUIRED_WIDGETS.some( ( requiredType ) => boxContainsType( container, requiredType ) );
};

/**
 * Whether a `document/elements/move` would take a required section out of its box.
 *
 * True only when a moved element is (or contains) a required section AND its
 * destination is outside the box it currently lives in — the destination resolves
 * to no box at all, or to a DIFFERENT box than the source. A plain intra-box
 * reorder keeps the same enclosing box before and after, so it is allowed. Moving
 * the whole checkout box is allowed (it carries its sections with it, not a
 * move-out), and a non-required section (cart / discount-form) is never covered.
 *
 * This runs at the move command level, BEFORE the move decomposes into its
 * delete/create halves, so a vetoed move-out never reaches (and never needs) the
 * reorder exemption on the delete-lock / create dup-guard.
 *
 * @since 3.7.0
 * @param {Object} args The move command args ({ container|containers, target }).
 * @return {boolean} True when the move must be vetoed.
 */
const moveIsRequiredMoveOut = ( args ) => {
	if ( isSeeding() ) {
		return false;
	}

	const destBox = findEnclosingBox( args?.target );

	return argContainers( args ).some( ( element ) => {
		// Moving the whole box carries its sections along — not a move-out.
		if ( element?.model?.get?.( 'elType' ) === EDD_TYPE ) {
			return false;
		}

		// Only required sections (or containers holding them) are protected.
		if ( ! subtreeHasRequired( element ) ) {
			return false;
		}

		// The box the element currently lives in (its source).
		const sourceBox = findEnclosingBox( element );
		if ( ! sourceBox ) {
			return false;
		}

		// Vetoed when the destination is outside the source box: no box, or a
		// different box than the one the required section started in.
		return ! destBox || containerId( destBox ) !== containerId( sourceBox );
	} );
};

/**
 * The veto message shown when a required section is dragged out of its box.
 *
 * Built with the same i18n-literal-plus-fallback shape as the delete-lock and
 * dup-lock messages so the string extractor picks it up statically and it still
 * reads when wp.i18n is unavailable.
 *
 * @since 3.7.0
 * @return {string} The translated (or fallback) veto message.
 */
const requiredMoveOutMessage = () => {
	const i18n = globalThis.wp?.i18n;

	return i18n
		? i18n.__( 'This checkout section is required and cannot be moved outside its checkout box.', 'easy-digital-downloads' )
		: 'This checkout section is required and cannot be moved outside its checkout box.';
};

/**
 * Register the required-section move-out lock.
 *
 * A `document/elements/move` DATA Dependency hook that vetoes moving a required
 * section (or a container holding one) out of its checkout box, keeping the box's
 * required-section integrity. Implemented exactly like the delete-lock and dup-lock
 * — it VETOES BY RETURNING false (never by throwing HookBreak), so the dependency
 * runner emits a depth-balanced HookBreak and the veto repeats cleanly on every
 * attempt (see the delete-lock docblock for the full reasoning).
 *
 * Kept as its own single-responsibility hook rather than folded into the delete-
 * lock or the dup-lock: it is neither a delete veto nor a duplicate-section veto,
 * but a move-out veto. A plain intra-box reorder passes (same enclosing box before
 * and after); the dup-lock's own move guard still handles moving a duplicate INTO a
 * box.
 *
 * @since 3.7.0
 * @return {boolean} True when the hook is registered (or already was).
 */
const registerRequiredSectionMoveLock = () => {
	const commands = globalThis.$e;

	if ( ! commands?.modules?.hookData?.Dependency || typeof commands.hooks?.registerDataDependency !== 'function' ) {
		return false;
	}

	if ( globalThis.__eddMoveOutLockDone ) {
		return true;
	}

	const DependencyHook = commands.modules.hookData.Dependency;

	class MoveOutLockHook extends DependencyHook {
		getCommand() {
			return 'document/elements/move';
		}

		getId() {
			return 'edd-checkout-box-move-out-lock';
		}

		getConditions( args ) {
			return moveIsRequiredMoveOut( args );
		}

		apply() {
			globalThis.elementor?.notifications?.showToast?.( { message: requiredMoveOutMessage() } );

			return false;
		}
	}

	commands.hooks.registerDataDependency( new MoveOutLockHook() );
	globalThis.__eddMoveOutLockDone = true;

	return true;
};

export { registerRequiredSectionMoveLock, registerSecondBoxNotice };
