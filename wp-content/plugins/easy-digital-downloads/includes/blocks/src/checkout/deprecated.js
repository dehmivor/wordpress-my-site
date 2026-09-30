import { createBlock } from '@wordpress/blocks';

/**
 * Attributes the checkout block used before it became a parent of inner blocks.
 * These are no longer registered on the block, but legacy posts still carry them
 * in the block comment, so the deprecation needs to parse them to migrate.
 */
const legacyAttributes = {
	layout: {
		type: 'string',
		default: '',
	},
	show_discount_form: {
		type: 'boolean',
		default: true,
	},
	thumbnail_width: {
		type: 'number',
		default: 25,
	},
};

/**
 * Builds the cart inner block, carrying over the legacy cart-related settings.
 *
 * @param {Object} attributes Parsed legacy attributes.
 * @return {Object} The checkout-cart block.
 */
const cartBlock = ( attributes ) =>
	createBlock( 'edd/checkout-cart', {
		thumbnail_width: attributes.thumbnail_width,
		show_discount_form: attributes.show_discount_form,
	} );

const personalInfo = () => createBlock( 'edd/checkout-personal-info' );
const paymentInfo = () => createBlock( 'edd/checkout-payment-info' );

/**
 * Single column: cart stacked above the purchase form. Mirrors the "full" layout.
 *
 * @param {Object} attributes Parsed legacy attributes.
 * @return {Object[]} Inner blocks.
 */
const singleColumn = ( attributes ) => [
	cartBlock( attributes ),
	personalInfo(),
	paymentInfo(),
];

/**
 * Two columns: purchase form on the left, cart on the right. Mirrors the
 * half / two-thirds / four-fifths layouts. Widths are omitted for an even
 * 50/50 split to match the registered pattern markup.
 *
 * @param {Object} attributes Parsed legacy attributes.
 * @param {string} formWidth  CSS width for the form column, or '' for 50/50.
 * @param {string} cartWidth  CSS width for the cart column, or '' for 50/50.
 * @return {Object[]} Inner blocks.
 */
const twoColumn = ( attributes, formWidth, cartWidth ) => [
	createBlock( 'core/columns', {}, [
		createBlock( 'core/column', formWidth ? { width: formWidth } : {}, [
			personalInfo(),
			paymentInfo(),
		] ),
		createBlock( 'core/column', cartWidth ? { width: cartWidth } : {}, [
			cartBlock( attributes ),
		] ),
	] ),
];

/**
 * Cart on top (full width), personal and payment side by side.
 * Mirrors the half-bottom / two-thirds-bottom layouts.
 *
 * @param {Object} attributes Parsed legacy attributes.
 * @return {Object[]} Inner blocks.
 */
const cartTopTwoColumn = ( attributes ) => [
	cartBlock( attributes ),
	createBlock( 'core/columns', {}, [
		createBlock( 'core/column', {}, [ personalInfo() ] ),
		createBlock( 'core/column', {}, [ paymentInfo() ] ),
	] ),
];

/**
 * Maps a legacy layout value to the equivalent inner block structure.
 *
 * @param {Object} attributes Parsed legacy attributes.
 * @return {Object[]} Inner blocks.
 */
const innerBlocksForLayout = ( attributes ) => {
	switch ( attributes.layout ) {
		case 'half':
			return twoColumn( attributes, '', '' );
		case 'two-thirds':
			return twoColumn( attributes, '70%', '30%' );
		case 'four-fifths':
			return twoColumn( attributes, '80%', '20%' );
		case 'half-bottom':
		case 'two-thirds-bottom':
			return cartTopTwoColumn( attributes );
		case 'full':
		case '':
		default:
			return singleColumn( attributes );
	}
};

/**
 * Deprecations for the checkout block.
 *
 * The block was originally a single dynamic block (save returned null) with
 * layout/cart settings as attributes. It is now a parent block whose inner
 * blocks render each section. Existing posts validate against the new save
 * (both produce empty markup), so `isEligible` forces the migration whenever a
 * legacy block has no inner blocks yet, converting it to the matching layout.
 */
const deprecated = [
	{
		attributes: legacyAttributes,
		supports: {
			html: false,
		},
		save: () => null,
		isEligible( attributes, innerBlocks ) {
			return ! innerBlocks || 0 === innerBlocks.length;
		},
		migrate( attributes ) {
			return [ {}, innerBlocksForLayout( attributes ) ];
		},
	},
];

export default deprecated;
