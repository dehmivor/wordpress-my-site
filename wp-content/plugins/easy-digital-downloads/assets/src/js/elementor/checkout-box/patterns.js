/**
 * EDD Checkout box layout patterns and section constants for Elementor.
 *
 * @package     EDD\Elementor
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

/**
 * The custom element type. Must match the PHP element get_type()/get_name().
 *
 * @since 3.7.0
 * @type {string}
 */
const EDD_TYPE = 'edd-checkout-box';

/**
 * The four EDD checkout section widget types.
 *
 * All four sections are seeded by the picker patterns: the cart, the discount
 * form (seeded immediately after the cart), and the personal-info and
 * payment-info sections. The cart and discount form are also the two the
 * box-scoped re-add affordance can restore after a delete.
 *
 * @since 3.7.0
 * @type {string}
 */
const SECTION_CART          = 'edd-checkout-cart';
const SECTION_PERSONAL_INFO = 'edd-checkout-personal-info';
const SECTION_PAYMENT_INFO  = 'edd-checkout-payment-info';
const SECTION_DISCOUNT_FORM = 'edd-checkout-discount-form';

/**
 * The five block-parity checkout layout patterns.
 *
 * Mirrors the checkout block's pattern set
 * (src/Blocks/Checkout/Patterns.php::get_layout_patterns()) — same slugs (bare; the
 * block prefixes them `edd-checkout/`), section order, column widths and nesting.
 * Every multi-column pattern wraps its columns in a `row` container, the way the
 * block wraps its columns in `wp:columns`. That nesting is load-bearing: a column
 * left as a direct child of the box is a flex sibling of the box's hook-output slot,
 * which spans the whole row and squeezes whatever it sits beside.
 *
 * Each pattern is an Elementor seed tree:
 *
 *   - `boxDirection`: the box's own flex direction, re-applied on every layout switch.
 *   - `nodes`: ordered children created directly in the box — a `widget`, a `column`
 *     (a container of widgets with an optional flex-basis `width`), or a `row` (a
 *     row-flex container holding `columns`).
 *
 * The discount form is always seeded immediately after the cart. It is the one
 * section the block has no pattern node for — the cart renders it inline via its
 * `show_discount_form` attribute — so the drift-guard test
 * (tests/elementor/tests-checkout-box-patterns.php) strips that slug from both sides
 * before comparing.
 *
 * @since 3.7.0
 * @type {Object[]}
 */
const PATTERNS = [
	{
		slug: 'single-column',
		boxDirection: 'column',
		nodes: [
			{ type: 'widget', widget: SECTION_CART },
			{ type: 'widget', widget: SECTION_DISCOUNT_FORM },
			{ type: 'widget', widget: SECTION_PERSONAL_INFO },
			{ type: 'widget', widget: SECTION_PAYMENT_INFO },
		],
	},
	{
		slug: 'two-column-50-50',
		boxDirection: 'column',
		nodes: [
			{
				type: 'row',
				columns: [
					{ width: '', widgets: [ SECTION_PERSONAL_INFO, SECTION_PAYMENT_INFO ] },
					{ width: '', widgets: [ SECTION_CART, SECTION_DISCOUNT_FORM ] },
				],
			},
		],
	},
	{
		slug: 'two-column-70-30',
		boxDirection: 'column',
		nodes: [
			{
				type: 'row',
				columns: [
					{ width: '70%', widgets: [ SECTION_PERSONAL_INFO, SECTION_PAYMENT_INFO ] },
					{ width: '30%', widgets: [ SECTION_CART, SECTION_DISCOUNT_FORM ] },
				],
			},
		],
	},
	{
		slug: 'two-column-80-20',
		boxDirection: 'column',
		nodes: [
			{
				type: 'row',
				columns: [
					{ width: '80%', widgets: [ SECTION_PERSONAL_INFO, SECTION_PAYMENT_INFO ] },
					{ width: '20%', widgets: [ SECTION_CART, SECTION_DISCOUNT_FORM ] },
				],
			},
		],
	},
	{
		slug: 'cart-top-two-column',
		boxDirection: 'column',
		nodes: [
			{ type: 'widget', widget: SECTION_CART },
			{ type: 'widget', widget: SECTION_DISCOUNT_FORM },
			{
				type: 'row',
				columns: [
					{ width: '', widgets: [ SECTION_PERSONAL_INFO ] },
					{ width: '', widgets: [ SECTION_PAYMENT_INFO ] },
				],
			},
		],
	},
];

/**
 * The slug of the pattern auto-seeded into a freshly added box.
 *
 * @since 3.7.0
 * @type {string}
 */
const DEFAULT_PATTERN = 'single-column';

/**
 * The non-required sections the box-scoped re-add affordance can restore.
 *
 * Only the two non-required, delete-allowed sections: the cart and the discount
 * form. Both are seeded by every pattern, so either is only absent after a user
 * deletes it — this list gates the re-add, not the seeding. The two required
 * sections are never offered — they are delete-locked, so they can never be absent.
 *
 * @since 3.7.0
 * @type {string[]}
 */
const NON_REQUIRED_SECTIONS = [ SECTION_CART, SECTION_DISCOUNT_FORM ];

/**
 * Map of the box's native `switcher` control ids to the section they toggle.
 *
 * Each key MUST equal the control id registered in PHP
 * (CheckoutBox::register_controls, the `edd_section_*` switchers copying the
 * monolith checkout widget's show/hide control shape); each value is the section
 * widget type that switcher shows/hides. The editor listens on the box's settings
 * model `change:<id>` for these ids: flipping a switcher ON re-seeds the section
 * (reAddSection), OFF removes it (removeSection). Only the non-required sections
 * appear here — the required sections are delete-locked and get no switcher.
 *
 * @since 3.7.0
 * @type {Object}
 */
const SECTION_TOGGLE_CONTROLS = {
	edd_section_cart: SECTION_CART,
	edd_section_discount_form: SECTION_DISCOUNT_FORM,
};

/**
 * Every section widget type a checkout box can hold.
 *
 * The single source of truth for the deduped/recognised section widget types
 * used by isSectionWidget / boxContainsType / getMissingRequired — membership
 * only, so order is irrelevant here. All four seeded sections are members, so a
 * manually added second instance of any of them is still deduped.
 *
 * @since 3.7.0
 * @type {Set<string>}
 */
const SUPPORTED_WIDGETS = new Set( [
	SECTION_CART,
	SECTION_PERSONAL_INFO,
	SECTION_PAYMENT_INFO,
	SECTION_DISCOUNT_FORM,
] );

/**
 * The section widgets a checkout box is REQUIRED to contain.
 *
 * A box missing either of these two sections triggers the preview overlay
 * and each of these two widgets is protected by the delete-lock.
 * The cart and discount-form widgets are NOT required and NOT locked.
 *
 * @since 3.7.0
 * @type {string[]}
 */
const REQUIRED_WIDGETS = [
	'edd-checkout-personal-info',
	'edd-checkout-payment-info',
];

/**
 * Human-readable labels for the required section widget types.
 *
 * Used to name the specific missing section(s) in the preview overlay.
 *
 * @since 3.7.0
 * @return {Object} Map of widgetType to translated label.
 */
const requiredLabels = () => {
	const i18n = globalThis.wp?.i18n;

	// Every __() receives a string literal + text domain so the i18n string
	// extractor can pick up the translatable strings statically.
	if ( ! i18n ) {
		return {
			'edd-checkout-personal-info': 'Personal Info',
			'edd-checkout-payment-info': 'Payment Info',
		};
	}

	return {
		'edd-checkout-personal-info': i18n.__( 'Personal Info', 'easy-digital-downloads' ),
		'edd-checkout-payment-info': i18n.__( 'Payment Info', 'easy-digital-downloads' ),
	};
};

/**
 * Resolve a pattern definition by its bare slug.
 *
 * @since 3.7.0
 * @param {string} slug The bare pattern slug (no `edd-checkout/` prefix).
 * @return {?Object} The PATTERNS entry, or null when the slug is unknown.
 */
const findPattern = ( slug ) => PATTERNS.find( ( pattern ) => pattern.slug === slug ) ?? null;

export {
	EDD_TYPE,
	PATTERNS,
	DEFAULT_PATTERN,
	NON_REQUIRED_SECTIONS,
	SECTION_TOGGLE_CONTROLS,
	SUPPORTED_WIDGETS,
	REQUIRED_WIDGETS,
	requiredLabels,
	findPattern,
};
