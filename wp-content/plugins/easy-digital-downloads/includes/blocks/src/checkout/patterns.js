import {
	SingleColumnIcon,
	FiftyFiftySplitIcon,
	SeventyThirtySplitIcon,
	EightyTwentySplitIcon,
	CartTopTwoColumnIcon,
} from './layout-icons';

const PATTERN_ICONS = {
	'edd-checkout/single-column':        SingleColumnIcon,
	'edd-checkout/two-column-50-50':     FiftyFiftySplitIcon,
	'edd-checkout/two-column-70-30':     SeventyThirtySplitIcon,
	'edd-checkout/two-column-80-20':     EightyTwentySplitIcon,
	'edd-checkout/cart-top-two-column':  CartTopTwoColumnIcon,
};

export const LayoutPicker = ( { patterns, onSelect } ) => (
	<div className="edd-checkout-layout-picker">
		{ patterns.map( ( pattern ) => {
			const Icon = PATTERN_ICONS[ pattern.name ];
			return (
				<button
					key={ pattern.name }
					className="edd-checkout-layout-picker__item"
					onClick={ () => onSelect( pattern ) }
					type="button"
				>
					<div className="edd-checkout-layout-picker__thumb">
						{ Icon && <Icon /> }
					</div>
					<div className="edd-checkout-layout-picker__label">
						{ pattern.title }
					</div>
				</button>
			);
		} ) }
	</div>
);
