/**
 * EDD Checkout box duplicate-section lock for Elementor.
 *
 * @package     EDD\Elementor
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

import { isSectionWidget, findEnclosingBox, boxContainsType, argContainers, containerId } from './container-utils';
import { isSeeding } from './pattern-seeding';
import { isMoveDecomposition } from './delete-lock';

/**
 * The veto message shown when an author tries to add a second section of a type
 * a checkout box already contains.
 *
 * Built with the same i18n-literal-plus-fallback shape as the delete-lock message
 * so the string extractor picks it up statically and it still reads when wp.i18n
 * is unavailable.
 *
 * @since 3.7.0
 * @return {string} The translated (or fallback) veto message.
 */
const duplicateSectionMessage = () => {
	const i18n = globalThis.wp?.i18n;

	return i18n
		? i18n.__( 'This checkout box already contains this section.', 'easy-digital-downloads' )
		: 'This checkout box already contains this section.';
};

/**
 * Whether a `document/elements/create` would add a duplicate section to a box.
 *
 * True only when: not mid-seed (the seed hook's own creates must pass), not the
 * create-half of a decomposed drag-reorder (the moved element is re-created into
 * the same box its delete-half just removed it from), the model is a checkout
 * section widget, its target parent is inside a checkout box, and that box already
 * contains the same section type. Creating into a box that lacks the type, or
 * anywhere outside a box, returns false (allowed).
 *
 * @since 3.7.0
 * @param {Object} args The create command args ({ container|containers, model }).
 * @return {boolean} True when the create must be vetoed.
 */
const createIsDuplicate = ( args ) => {
	if ( isSeeding() || isMoveDecomposition() ) {
		return false;
	}

	const model = args?.model;
	if ( model?.elType !== 'widget' || ! isSectionWidget( model?.widgetType ) ) {
		return false;
	}

	return argContainers( args ).some( ( parent ) => {
		const box = findEnclosingBox( parent );
		return Boolean( box ) && boxContainsType( box, model.widgetType );
	} );
};

/**
 * Whether a `document/elements/duplicate` would create a duplicate section.
 *
 * Duplicating a section widget that lives in a checkout box always yields a
 * same-type sibling in that box, so it is vetoed. Duplicating a non-section
 * widget, or a section that is not inside a box, is allowed.
 *
 * @since 3.7.0
 * @param {Object} args The duplicate command args ({ container|containers }).
 * @return {boolean} True when the duplicate must be vetoed.
 */
const duplicateIsDuplicate = ( args ) => {
	if ( isSeeding() ) {
		return false;
	}

	return argContainers( args ).some( ( target ) => {
		const widgetType = target?.model?.get?.( 'widgetType' );
		return isSectionWidget( widgetType ) && Boolean( findEnclosingBox( target ) );
	} );
};

/**
 * Whether a `document/elements/move` would drop a duplicate section into a box.
 *
 * True only when a moved element is a checkout section whose destination box
 * already holds that type in some element OTHER than the one(s) being moved
 * (movedIds are excluded, so a plain reorder within the same box is allowed).
 * Moving out of a box, or into a box lacking the type, returns false (allowed).
 *
 * @since 3.7.0
 * @param {Object} args The move command args ({ container|containers, target }).
 * @return {boolean} True when the move must be vetoed.
 */
const moveIsDuplicate = ( args ) => {
	if ( isSeeding() ) {
		return false;
	}

	const box = findEnclosingBox( args?.target );
	if ( ! box ) {
		return false;
	}

	const moved    = argContainers( args );
	const movedIds = moved.map( containerId ).filter( Boolean );

	return moved.some( ( element ) => {
		const widgetType = element?.model?.get?.( 'widgetType' );
		return isSectionWidget( widgetType ) && boxContainsType( box, widgetType, movedIds );
	} );
};

/**
 * Register the duplicate-section lock across create, duplicate, and move.
 *
 * A checkout box holds at most ONE section of each type; a second same-type
 * section duplicates the payment-info gateway selector / #edd_purchase_form_wrap /
 * field ids and breaks the gateway switch. This blocks the three editor paths that
 * could introduce one: adding a new section (`document/elements/create`),
 * duplicating an existing one (`document/elements/duplicate`), and dragging a
 * section into a box that already has that type (`document/elements/move`).
 *
 * Implemented exactly like the delete-lock — as `document/elements/*` DATA
 * Dependency hooks that VETO BY RETURNING false (never by throwing HookBreak
 * themselves). The dependency runner turns the false into a depth-balanced
 * HookBreak, so the veto is caught cleanly AND repeats on every later attempt; a
 * self-thrown HookBreak would unwind past the runner's depth-decrement, stick the
 * recursion counter, and cause the hook to be silently skipped forever after (see
 * the delete-lock docblock for the full reasoning).
 *
 * Seeding is never vetoed: the seed hook sets `eddSeeding` while it issues its
 * `document/elements/create` commands, and each guard's condition returns false
 * while that flag is set. (The seeded types are distinct anyway, so a fresh box is
 * filled cleanly.)
 *
 * @since 3.7.0
 * @return {boolean} True when the hooks are registered (or already were).
 */
const registerDuplicateLock = () => {
	const commands = globalThis.$e;

	if ( ! commands?.modules?.hookData?.Dependency || typeof commands.hooks?.registerDataDependency !== 'function' ) {
		return false;
	}

	if ( globalThis.__eddDuplicateLockDone ) {
		return true;
	}

	const DependencyHook = commands.modules.hookData.Dependency;

	// One dependency hook per command (a Dependency hook binds a single command).
	// Each shares the same veto: show the reason via toast, then return false so the
	// runner emits a depth-balanced HookBreak. Never throw here — see docblock.
	const makeGuard = ( command, id, isDuplicate ) =>
		class extends DependencyHook {
			getCommand() {
				return command;
			}

			getId() {
				return id;
			}

			getConditions( args ) {
				return isDuplicate( args );
			}

			apply() {
				globalThis.elementor?.notifications?.showToast?.( { message: duplicateSectionMessage() } );

				return false;
			}
		};

	const CreateGuard    = makeGuard( 'document/elements/create', 'edd-checkout-box-dup-lock-create', createIsDuplicate );
	const DuplicateGuard = makeGuard( 'document/elements/duplicate', 'edd-checkout-box-dup-lock-duplicate', duplicateIsDuplicate );
	const MoveGuard      = makeGuard( 'document/elements/move', 'edd-checkout-box-dup-lock-move', moveIsDuplicate );

	commands.hooks.registerDataDependency( new CreateGuard() );
	commands.hooks.registerDataDependency( new DuplicateGuard() );
	commands.hooks.registerDataDependency( new MoveGuard() );

	globalThis.__eddDuplicateLockDone = true;

	return true;
};

export { registerDuplicateLock };
