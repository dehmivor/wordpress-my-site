/**
 * EDD Checkout box pattern seeding, switching and section add/remove for Elementor.
 *
 * @package     EDD\Elementor
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

import { findPattern, NON_REQUIRED_SECTIONS } from './patterns';
import { boxContainsType, resolveSectionTarget } from './container-utils';

/**
 * Recursion guard for the seed hook.
 *
 * Set while the hook is creating its own children so the create commands it
 * issues do not re-enter the seed logic.
 *
 * @since 3.7.0
 * @type {boolean}
 */
let eddSeeding = false;

/**
 * Read the current seeding-guard state.
 *
 * The exported read-only accessor for the module-owned `eddSeeding` guard so other
 * modules can test whether a programmatic seed / switch / re-add is in flight
 * without importing (and never reassigning) the raw `let`.
 *
 * @since 3.7.0
 * @return {boolean} True while a programmatic seed/switch/re-add is running.
 */
const isSeeding = () => eddSeeding;

/**
 * Run a callback with the programmatic-replace flag set, always clearing it.
 *
 * Sets `eddSeeding` for the duration of a programmatic seed / pattern switch /
 * re-add so the create-half of those operations bypasses the dedup guards and the
 * switch's delete-half bypasses the delete-lock, then ALWAYS clears the flag in a
 * `finally`. A throw inside the callback must never leave the flag stuck `true`: a
 * stuck flag would silently disable the delete-lock and the dup-lock for every
 * subsequent USER action. The seed hook, the layout switch, and the box-scoped
 * re-add all route their create/delete commands through here.
 *
 * @since 3.7.0
 * @param {Function} callback The programmatic seed/switch/re-add work to run.
 * @return {void}
 */
const withSeedingFlag = ( callback ) => {
	eddSeeding = true;
	try {
		callback();
	} finally {
		eddSeeding = false;
	}
};

/**
 * Set a container's native flex direction via a settings command.
 *
 * Used by the seed/switch to give the box the pattern's own flex direction, and each
 * seeded `row` node the `row` direction its columns sit side by side in. Runs as its
 * own `document/elements/settings` command — not a create/delete/move — so the box
 * locks never see it.
 *
 * @since 3.7.0
 * @param {Object} commands  The Elementor command runner ($e).
 * @param {Object} container The container whose flex direction to set.
 * @param {string} direction The flex direction (`row` / `column`).
 * @return {void}
 */
const setFlexDirection = ( commands, container, direction ) => {
	commands.run( 'document/elements/settings', {
		container,
		settings: { flex_direction: direction },
	} );
};

/**
 * Create one seed node (widget, column, or row) into a parent container.
 *
 * Each element is created as its OWN `document/elements/create` command:
 * `document/elements/create` only instantiates the TOP element of its model as a
 * real editor element (id, Container, view); nested `elements` in the same model
 * are stored as raw data but never instantiated, so a section passed as a nested
 * model would be invisible, unselectable, and undeletable (defeating the
 * delete-lock). So a `column`/`row` container is created first, then its widgets /
 * nested columns are each created into it.
 *
 * @since 3.7.0
 * @param {Object} commands The Elementor command runner ($e).
 * @param {Object} parent   The container to create the node into.
 * @param {Object} node     A pattern seed node (widget / column / row).
 * @return {void}
 */
const seedNode = ( commands, parent, node ) => {
	if ( 'widget' === node.type ) {
		commands.run( 'document/elements/create', {
			container: parent,
			model: { elType: 'widget', widgetType: node.widget },
			options: { edit: false },
		} );
		return;
	}

	const result = commands.run( 'document/elements/create', {
		container: parent,
		model: { elType: 'container' },
		options: { edit: false },
	} );
	const container = Array.isArray( result ) ? result[ 0 ] : result;
	if ( ! container ) {
		return;
	}

	// A `row` container lays its child columns side by side.
	if ( 'row' === node.type ) {
		setFlexDirection( commands, container, 'row' );
		( node.columns ?? [] ).forEach( ( column ) => {
			seedNode( commands, container, { type: 'column', width: column.width, widgets: column.widgets } );
		} );
		return;
	}

	// A `column` container: an optional flex-basis width, then its section widgets.
	if ( node.width ) {
		commands.run( 'document/elements/settings', {
			container,
			settings: { width: { unit: '%', size: parseFloat( node.width ), sizes: [] } },
		} );
	}

	( node.widgets ?? [] ).forEach( ( widgetType ) => {
		commands.run( 'document/elements/create', {
			container,
			model: { elType: 'widget', widgetType },
			options: { edit: false },
		} );
	} );
};

/**
 * Seed a pattern's container/widget tree into a box.
 *
 * Sets the box's flex direction for the pattern, then creates each of the
 * pattern's nodes in order. Must be called inside withSeedingFlag so the creates
 * bypass the dup-lock.
 *
 * @since 3.7.0
 * @param {Object} commands The Elementor command runner ($e).
 * @param {Object} box      The checkout box container to seed into.
 * @param {Object} pattern  A PATTERNS entry.
 * @return {void}
 */
const seedPattern = ( commands, box, pattern ) => {
	setFlexDirection( commands, box, pattern.boxDirection );
	pattern.nodes.forEach( ( node ) => seedNode( commands, box, node ) );
};

/**
 * Delete every direct child of a box.
 *
 * Resolves each child element id to its container and runs a delete command per
 * child. Called only inside withSeedingFlag (the pattern switch's delete-half), so
 * the delete-lock's `eddSeeding` exemption lets these programmatic deletes pass
 * while a plain user delete of a required section is still vetoed.
 *
 * @since 3.7.0
 * @param {Object} commands The Elementor command runner ($e).
 * @param {Object} box      The checkout box container to empty.
 * @return {void}
 */
const deleteBoxChildren = ( commands, box ) => {
	const ids = [];
	box?.model?.get?.( 'elements' )?.each?.( ( child ) => {
		const id = child.get( 'id' );
		if ( id ) {
			ids.push( id );
		}
	} );

	ids.forEach( ( id ) => {
		const container = globalThis.elementor?.getContainer?.( id );
		if ( container ) {
			commands.run( 'document/elements/delete', { container } );
		}
	} );
};

/**
 * Switch a box to a layout pattern: delete its inner widgets, re-seed the pattern.
 *
 * Mirrors the Gutenberg checkout block's LayoutPicker (edit.js), which replaces
 * all inner blocks on selection. The delete-half and the re-seed-half both run
 * inside the SAME withSeedingFlag call so the delete bypasses the delete-lock and
 * the creates bypass the dup-lock, and the flag is always cleared afterwards. The
 * re-seed runs synchronously right after the delete in the same batch, so the box
 * is never left empty. A passive warning fires before the switch (not a blocking
 * confirm), matching the block's passive PanelBody description.
 *
 * @since 3.7.0
 * @param {Object} box  The checkout box container to switch.
 * @param {string} slug The bare target pattern slug.
 * @return {void}
 */
const switchPattern = ( box, slug ) => {
	const commands = globalThis.$e;
	const pattern  = findPattern( slug );
	if ( ! commands || ! box || ! pattern ) {
		return;
	}

	const i18n = globalThis.wp?.i18n;
	const message = i18n
		? i18n.__( 'Switching layout replaces all widget contents.', 'easy-digital-downloads' )
		: 'Switching layout replaces all widget contents.';
	globalThis.elementor?.notifications?.showToast?.( { message } );

	withSeedingFlag( () => {
		deleteBoxChildren( commands, box );
		seedPattern( commands, box, pattern );
	} );

	// Re-select the box so its panel is restored, keeping focus on the layout picker after a switch.
	globalThis.$e?.run?.( 'document/elements/select', { container: box } );
};

/**
 * Give a re-added section the flex order of its new DOM neighbor.
 *
 * A freshly created widget carries no `_flex_order`, so it renders at the CSS
 * default `order: 0` and floats above any siblings the template ordered
 * explicitly (1, 2, 3 …) — landing at the top of the box instead of its slot.
 * Copying the order of the sibling it now sits after in the DOM (or the sibling
 * before it, when the section is first) drops it back into place: equal `order`
 * values fall back to source order, so the re-added section renders immediately
 * after its neighbor. Only the re-added widget is touched, so an intentional
 * sibling reorder (e.g. a swapped two-column layout) is never disturbed.
 *
 * @since 3.7.0
 * @param {Object} commands  The Elementor command runner ($e).
 * @param {Object} container The container the section was re-added into.
 * @param {string} id        The re-added widget's element id.
 * @return {void}
 */
const matchNeighborOrder = ( commands, container, id ) => {
	const children = container?.model?.get?.( 'elements' );
	if ( ! children?.each ) {
		return;
	}

	const models = [];
	children.each( ( child ) => models.push( child ) );
	const index = models.findIndex( ( model ) => model.get( 'id' ) === id );
	if ( index < 0 ) {
		return;
	}

	// Prefer the sibling before the re-added one (it renders right after it); fall
	// back to the sibling after it when the section is now the first child.
	const neighbor = models[ index - 1 ] ?? models[ index + 1 ];
	const settings = neighbor?.get?.( 'settings' );
	if ( ! settings ) {
		return;
	}

	const order  = 'custom' === settings.get( '_flex_order' ) ? settings.get( '_flex_order_custom' ) : 0;
	const widget = globalThis.elementor?.getContainer?.( id );
	if ( widget ) {
		commands.run( 'document/elements/settings', {
			container: widget,
			settings: { _flex_order: 'custom', _flex_order_custom: order },
		} );
	}
};

/**
 * Re-add an absent non-required section into a box (box-scoped re-add affordance).
 *
 * Creates the given non-required section (cart / discount form) into the container
 * the active layout places it in — resolved from the payment-info ancestor chain by
 * resolveSectionTarget — rather than always at the box root, so a re-added cart or
 * discount lands in the correct column and order. Never adds a section the box
 * already contains. Routed through
 * withSeedingFlag — the same guarded programmatic-replace flag as the switch — so a
 * throw cannot strand the flag `true`; the create of a genuinely-absent type would
 * pass the dup-lock anyway, but the flag path is the one already proven safe for
 * programmatic creates.
 *
 * @since 3.7.0
 * @param {Object} box        The checkout box container to add into.
 * @param {string} widgetType The non-required section widget type to re-add.
 * @return {void}
 */
const reAddSection = ( box, widgetType ) => {
	const commands = globalThis.$e;
	if ( ! commands || ! box || ! NON_REQUIRED_SECTIONS.includes( widgetType ) ) {
		return;
	}

	// Never add a section the box already holds (offer-only-absent is also enforced
	// at render time, but guard here too).
	if ( boxContainsType( box, widgetType ) ) {
		return;
	}

	const target    = resolveSectionTarget( box, widgetType );
	const container = target.container ?? box;

	withSeedingFlag( () => {
		const result  = commands.run( 'document/elements/create', {
			container,
			model: { elType: 'widget', widgetType },
			options: { at: target.at ?? null, edit: false },
		} );
		const created = Array.isArray( result ) ? result[ 0 ] : result;
		const id      = created?.id ?? created?.model?.get?.( 'id' );
		if ( id ) {
			matchNeighborOrder( commands, container, id );
		}
	} );
};

/**
 * Remove a present non-required section from a box (box-scoped toggle-off).
 *
 * Deletes every widget of the given non-required section type (cart / discount
 * form) found anywhere in the box subtree — the mirror of reAddSection. Routed
 * through withSeedingFlag, the same guarded programmatic-replace flag as reAddSection
 * and the pattern switch, so the programmatic delete rides the proven-safe flag path
 * and a throw cannot strand the flag `true`. Non-required sections are not
 * delete-locked, so the flag is not strictly required to pass the lock, but the flag
 * path keeps every programmatic create/delete consistent. Never touches a section the
 * box does not hold.
 *
 * @since 3.7.0
 * @param {Object} box        The checkout box container to remove from.
 * @param {string} widgetType The non-required section widget type to remove.
 * @return {void}
 */
const removeSection = ( box, widgetType ) => {
	const commands = globalThis.$e;
	if ( ! commands || ! box || ! NON_REQUIRED_SECTIONS.includes( widgetType ) ) {
		return;
	}

	// Collect every element id in the box subtree whose widgetType matches.
	const ids = [];
	const walk = ( collection ) => {
		collection?.each?.( ( child ) => {
			if ( child.get( 'widgetType' ) === widgetType ) {
				const id = child.get( 'id' );
				if ( id ) {
					ids.push( id );
				}
			}
			const nested = child.get( 'elements' );
			if ( nested ) {
				walk( nested );
			}
		} );
	};
	walk( box?.model?.get?.( 'elements' ) );

	if ( ! ids.length ) {
		return;
	}

	withSeedingFlag( () => {
		ids.forEach( ( id ) => {
			const container = globalThis.elementor?.getContainer?.( id );
			if ( container ) {
				commands.run( 'document/elements/delete', { container } );
			}
		} );
	} );
};

export {
	isSeeding,
	withSeedingFlag,
	seedPattern,
	switchPattern,
	reAddSection,
	removeSection,
};
