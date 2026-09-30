/**
 * EDD Checkout box container tree-walk and id helpers for Elementor.
 *
 * @package     EDD\Elementor
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

import { EDD_TYPE, SUPPORTED_WIDGETS, REQUIRED_WIDGETS } from './patterns';

/**
 * The payment-info section widget type.
 *
 * This and the two below mirror the section-type constants in patterns.js, which
 * does not export them. Both info sections are delete-locked, so the re-add
 * resolver can count on finding them in every box.
 *
 * @since 3.7.0
 * @type {string}
 */
const PAYMENT_INFO_WIDGET = 'edd-checkout-payment-info';

/**
 * The cart section widget type.
 *
 * @since 3.7.0
 * @type {string}
 */
const CART_WIDGET = 'edd-checkout-cart';

/**
 * The personal-info section widget type.
 *
 * @since 3.7.1
 * @type {string}
 */
const PERSONAL_INFO_WIDGET = 'edd-checkout-personal-info';

/**
 * Whether the Elementor editor internals required by this module are present.
 *
 * Feature detection, so a change to Elementor's internal shape makes the
 * integration a no-op rather than a throw.
 *
 * @since 3.7.0
 * @return {boolean} True when the required internals are available.
 */
const hasRequiredInternals = () => {
	const elementor = globalThis.elementor;

	return Boolean(
		typeof elementor?.elementsManager?.getElementTypeClass === 'function' &&
		typeof elementor?.elementsManager?.registerElementType === 'function'
	);
};

/**
 * Walk up to the document-root container from any container.
 *
 * @since 3.7.0
 * @param {Object} container The container to start from.
 * @return {?Object} The document-root container, or null.
 */
const rootContainer = ( container ) => {
	let current = container;

	while ( current?.parent ) {
		current = current.parent;
	}

	return current ?? null;
};

/**
 * Count the edd-checkout-box elements in a document element collection.
 *
 * A box is never nested inside a box; the walk recurses defensively anyway.
 *
 * @since 3.7.0
 * @param {Object} collection A Backbone-style elements collection (has `each`).
 * @return {number} The number of checkout boxes found.
 */
const countCheckoutBoxes = ( collection ) => {
	let count = 0;

	collection?.each?.( ( child ) => {
		if ( child.get( 'elType' ) === EDD_TYPE ) {
			count += 1;
		}
		const nested = child.get( 'elements' );
		if ( nested ) {
			count += countCheckoutBoxes( nested );
		}
	} );

	return count;
};

/**
 * Compute which required section widgets are missing from a checkout box.
 *
 * A section nested inside an inner column counts as present, matching the PHP
 * detection utility, which also recurses.
 *
 * @since 3.7.0
 * @param {Object} container The box container (has a model with `elements`).
 * @return {string[]} The required widgetTypes absent from the box.
 */
const getMissingRequired = ( container ) => REQUIRED_WIDGETS.filter(
	( widgetType ) => ! boxContainsType( container, widgetType )
);

/**
 * Resolve the enclosing EDD Checkout box view for a lifecycle event target.
 *
 * Returns the box view itself when the event target IS the box, so a child
 * add/remove refreshes the right box's overlay either way.
 *
 * @since 3.7.0
 * @param {Object} view The element view from the lifecycle event.
 * @return {?Object} The box view, or null when the target is not inside a box.
 */
const findBoxView = ( view ) => {
	let current = view;

	while ( current ) {
		if ( current.model?.get?.( 'elType' ) === EDD_TYPE ) {
			return current;
		}
		current = current._parent ?? null;
	}

	return null;
};

/**
 * Resolve the box container a picker control view is editing.
 *
 * Elementor passes the edited element's container to every control view as
 * `options.container`; a defensive fallback reads `container` directly.
 *
 * @since 3.7.0
 * @param {Object} view The picker control view.
 * @return {?Object} The box container, or null.
 */
const pickerBox = ( view ) => view?.options?.container ?? view?.container ?? null;

/**
 * Whether a widget type is one of the EDD checkout section widgets we dedupe.
 *
 * Every pattern seeds all four, so a box holds at most one of each.
 *
 * @since 3.7.0
 * @param {string} widgetType The widget type to test.
 * @return {boolean} True when the type is a deduped checkout section.
 */
const isSectionWidget = ( widgetType ) => SUPPORTED_WIDGETS.has( widgetType );

/**
 * Resolve a container's element id (for excluding moved elements from a count).
 *
 * @since 3.7.0
 * @param {Object} container The Elementor container.
 * @return {?string} The element id, or null when unresolved.
 */
const containerId = ( container ) => container?.id ?? container?.model?.get?.( 'id' ) ?? null;

/**
 * Walk up the container tree to the enclosing EDD Checkout box.
 *
 * Returns the box itself when passed the box, which is what lets the guards treat a
 * direct child and a section nested in an inner column alike.
 *
 * @since 3.7.0
 * @param {Object} container The container to start from.
 * @return {?Object} The enclosing box container, or null when not inside a box.
 */
const findEnclosingBox = ( container ) => {
	let current = container;

	while ( current ) {
		if ( current.model?.get?.( 'elType' ) === EDD_TYPE ) {
			return current;
		}
		current = current.parent ?? null;
	}

	return null;
};

/**
 * Whether a checkout box subtree already contains a widget of the given type.
 *
 * `excludeIds` is what keeps the move guard from reading a plain reorder within the
 * same box as a duplicate: the elements being moved are skipped.
 *
 * @since 3.7.0
 * @param {Object}   boxContainer The enclosing box container.
 * @param {string}   widgetType   The section widget type to look for.
 * @param {string[]} excludeIds   Element ids to skip (e.g. the moved elements).
 * @return {boolean} True when a matching, non-excluded widget already exists.
 */
const boxContainsType = ( boxContainer, widgetType, excludeIds = [] ) => {
	const walk = ( collection ) => {
		let found = false;

		collection?.each?.( ( child ) => {
			if ( found ) {
				return;
			}
			if ( excludeIds.includes( child.get( 'id' ) ) ) {
				return;
			}
			if ( child.get( 'widgetType' ) === widgetType ) {
				found = true;
				return;
			}
			const nested = child.get( 'elements' );
			if ( nested && walk( nested ) ) {
				found = true;
			}
		} );

		return found;
	};

	return walk( boxContainer?.model?.get?.( 'elements' ) );
};

/**
 * Build the ancestor chain to the first section of a given type within a box.
 *
 * The chain runs from the box's own top-level child down to the section itself, so
 * its length is the section's nesting depth and chainParent() reads off the
 * container holding it.
 *
 * @since 3.7.0
 * @param {Object} box        The checkout box container to walk.
 * @param {string} widgetType The section widget type to locate.
 * @return {Object[]} The model nodes from top-level child to the section (empty when absent).
 */
const sectionChain = ( box, widgetType ) => {
	const chain = [];

	const walk = ( collection, trail ) => {
		let found = false;

		collection?.each?.( ( child ) => {
			if ( found ) {
				return;
			}
			const next = [ ...trail, child ];
			if ( child.get( 'widgetType' ) === widgetType ) {
				chain.push( ...next );
				found = true;
				return;
			}
			const nested = child.get( 'elements' );
			if ( nested && walk( nested, next ) ) {
				found = true;
			}
		} );

		return found;
	};

	walk( box?.model?.get?.( 'elements' ), [] );

	return chain;
};

/**
 * Build the payment-info ancestor chain within a checkout box.
 *
 * Payment-info is delete-locked, so this chain is never empty for a real box.
 *
 * @since 3.7.0
 * @param {Object} box The checkout box container to walk.
 * @return {Object[]} The model nodes from top-level child to payment-info (empty when absent).
 */
const paymentInfoChain = ( box ) => sectionChain( box, PAYMENT_INFO_WIDGET );

/**
 * The container model directly holding the section at the end of an ancestor chain.
 *
 * @since 3.7.1
 * @param {Object[]} chain A section ancestor chain from sectionChain().
 * @return {?Object} The parent container model, or null when the section sits at the box root.
 */
const chainParent = ( chain ) => ( chain.length >= 2 ? chain[ chain.length - 2 ] : null );

/**
 * The first container child of a collection other than the given one.
 *
 * @since 3.7.1
 * @param {Object} collection A Backbone-style elements collection (has `each`).
 * @param {string} excludeId  The container child id to skip.
 * @return {?Object} The sibling container model, or null when there is none.
 */
const findSiblingContainer = ( collection, excludeId ) => {
	let sibling = null;

	collection?.each?.( ( child ) => {
		if ( sibling ) {
			return;
		}
		if ( 'container' === child.get( 'elType' ) && child.get( 'id' ) !== excludeId ) {
			sibling = child;
		}
	} );

	return sibling;
};

/**
 * Find the index of a child model (by id) within an elements collection.
 *
 * @since 3.7.0
 * @param {Object} collection A Backbone-style elements collection (has `each`).
 * @param {string} id         The child element id to locate.
 * @return {number} The zero-based index, or -1 when not found.
 */
const childIndexById = ( collection, id ) => {
	let index = -1;
	let current = 0;

	collection?.each?.( ( child ) => {
		if ( index >= 0 ) {
			return;
		}
		if ( child.get( 'id' ) === id ) {
			index = current;
		}
		current += 1;
	} );

	return index;
};

/**
 * Whether a container holds a structural child (a nested container or a checkout
 * section) other than the given branch.
 *
 * A heading or icon beside the cart deliberately does not count, so a "Your Cart"
 * card still reads as cart-only.
 *
 * @since 3.7.0
 * @param {Object} containerModel The container model to inspect.
 * @param {Object} branchModel    The child model to ignore (the cart's branch).
 * @return {boolean} True when another container or section child is present.
 */
const hasOtherStructuralChild = ( containerModel, branchModel ) => {
	const branchId = branchModel.get( 'id' );
	let found = false;

	containerModel.get( 'elements' )?.each?.( ( child ) => {
		if ( found || child.get( 'id' ) === branchId ) {
			return;
		}
		if ( 'container' === child.get( 'elType' ) || isSectionWidget( child.get( 'widgetType' ) ) ) {
			found = true;
		}
	} );

	return found;
};

/**
 * Resolve the target container and insertion index for a re-added section.
 *
 * A re-added cart or discount form must land where the active layout puts it, which
 * is not always the box root. The layout is read from the two delete-locked info
 * sections rather than from nesting depth: a two-column layout keeps both in one
 * column, cart-top splits them across the row's columns, and both wrap those columns
 * in a row — so depth alone cannot tell them apart.
 *
 * Placement per section:
 *   - cart → the sibling of the column holding the info sections in two-column;
 *     otherwise the box root at index 0, above the stacked sections.
 *   - discount form → after the cart, at the level where sections sit as siblings.
 *     A template that nests the cart in a wrapper holding nothing else (a "Your Cart"
 *     card) wants the discount beside that card, so the resolver climbs out of any
 *     cart-only wrapper first. With no cart present it appends after payment-info.
 *
 * Every unresolvable case returns the box root with no index, so the create degrades
 * to a plain append.
 *
 * @since 3.7.0
 * @param {Object} box        The checkout box container to add into.
 * @param {string} widgetType The non-required section widget type being re-added.
 * @return {{container: Object, at: ?number}} The target container and insertion index (null appends).
 */
const resolveSectionTarget = ( box, widgetType ) => {
	const fallback = { container: box, at: null };

	const elementor = globalThis.elementor;
	if ( ! box || typeof elementor?.getContainer !== 'function' ) {
		return fallback;
	}

	const chain = paymentInfoChain( box );
	if ( ! chain.length ) {
		return fallback;
	}

	if ( widgetType === CART_WIDGET ) {
		const formColumn     = chainParent( chain );
		const personalColumn = chainParent( sectionChain( box, PERSONAL_INFO_WIDGET ) );

		// Two-column: the info sections share a column, so the cart belongs in its
		// sibling — inside their row, or at the box root when the columns are flat.
		if ( formColumn && personalColumn && formColumn.get( 'id' ) === personalColumn.get( 'id' ) ) {
			const rowModel  = chain.length >= 3 ? chain[ chain.length - 3 ] : null;
			const siblings  = ( rowModel ?? box.model )?.get?.( 'elements' );
			const other     = findSiblingContainer( siblings, formColumn.get( 'id' ) );
			const container = other ? elementor.getContainer( other.get( 'id' ) ) : null;

			return container ? { container, at: null } : fallback;
		}

		// Single-column and cart-top: the box root, at the top.
		return { container: box, at: 0 };
	}

	// Climb out of any wrapper holding nothing but the cart's branch, so the discount
	// lands beside a "Your Cart" card rather than inside it.
	const cartChain = sectionChain( box, CART_WIDGET );
	if ( cartChain.length ) {
		let anchorIndex = cartChain.length - 1; // the cart itself
		let targetIndex = cartChain.length - 2; // the cart's immediate parent

		while ( targetIndex > 0 && ! hasOtherStructuralChild( cartChain[ targetIndex ], cartChain[ anchorIndex ] ) ) {
			anchorIndex = targetIndex;
			targetIndex -= 1;
		}

		const targetModel = targetIndex >= 0 ? cartChain[ targetIndex ] : null;
		const anchorModel = cartChain[ anchorIndex ];
		const container    = targetModel ? elementor.getContainer( targetModel.get( 'id' ) ) : box;
		const siblings     = ( targetModel ?? box.model )?.get?.( 'elements' );
		const anchorInList = childIndexById( siblings, anchorModel.get( 'id' ) );

		if ( container && anchorInList >= 0 ) {
			return { container, at: anchorInList + 1 };
		}
	}

	// The cart is deletable, so it may be absent: append after payment-info instead.
	const parentModel = chainParent( chain );
	if ( ! parentModel ) {
		return { container: box, at: null };
	}

	const container = elementor.getContainer( parentModel.get( 'id' ) );
	return container ? { container, at: null } : fallback;
};

/**
 * Normalize a command's container arg(s) to an array.
 *
 * Elementor commands accept either a single `container` or a `containers` array;
 * this collapses both to an array, matching the delete-lock's own reading.
 *
 * @since 3.7.0
 * @param {Object} args The command args.
 * @return {Object[]} The containers array (possibly empty).
 */
const argContainers = ( args ) => args?.containers ?? ( args?.container ? [ args.container ] : [] );

export {
	hasRequiredInternals,
	rootContainer,
	countCheckoutBoxes,
	findBoxView,
	getMissingRequired,
	isSectionWidget,
	containerId,
	findEnclosingBox,
	boxContainsType,
	resolveSectionTarget,
	argContainers,
	pickerBox,
};
