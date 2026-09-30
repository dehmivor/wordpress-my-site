import { __ } from '@wordpress/i18n';
import { SearchCombobox } from './search-combobox';

/**
 * A searchable discount code selector for use in block editor panels.
 *
 * @param {Object}   props
 * @param {string}   props.value    The currently selected discount code.
 * @param {Function} props.onChange Called with the new discount code when selection changes.
 */
export const DiscountCombobox = ( { value, onChange } ) => (
	<SearchCombobox
		action="edd_discount_search"
		params={ { filter_invalid: true } }
		mapOption={ ( d ) => ( { value: d.code, label: `${ d.code } — ${ d.name }` } ) }
		label={ __( 'Discount Code', 'easy-digital-downloads' ) }
		help={ __( 'Automatically apply this discount when the item is added to the cart.', 'easy-digital-downloads' ) }
		value={ value }
		onChange={ onChange }
	/>
);
