/**
 * Template Filters Component
 *
 * Filter controls for the template grid.
 *
 * @package EDD
 * @since   3.7.0
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import { SelectControl, SearchControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { STORE_NAME, FILTER_OPTIONS } from '../store/constants';

/**
 * TemplateFilters component
 *
 * @return {JSX.Element} The TemplateFilters component.
 */
const TemplateFilters = () => {
	const { filters, availableTags, availableEditors, editors } = useSelect( ( select ) => {
		const store = select( STORE_NAME );
		return {
			filters: store.getFilters(),
			availableTags: store.getAvailableTags(),
			availableEditors: store.getAvailableEditorsFromApi(),
			editors: store.getAvailableEditors(),
		};
	}, [] );

	const { setFilter } = useDispatch( STORE_NAME );

	// Build editor options from API-reported editors (what templates support).
	// Labels come from the server editor registry; never hardcoded here.
	const editorOptions = [
		{ label: __( 'All Editors', 'easy-digital-downloads' ), value: FILTER_OPTIONS.EDITOR.ALL },
	];

	( availableEditors || [] ).forEach( ( editor ) => {
		const id = editor.id ?? editor;
		if ( 'all' === id ) {
			return;
		}
		// Do not offer an editor that is unavailable on this site as a filter.
		if ( false === editors[ id ]?.available ) {
			return;
		}
		editorOptions.push( {
			label: editors[ id ]?.label ?? id,
			value: id,
		} );
	} );

	// Build category options dynamically from available tags returned by the API.
	const categoryOptions = ( availableTags || [] ).map( ( tag ) => ( {
		label: tag.name,
		value: tag.id,
	} ) );

	// Only show the category dropdown when there are tags beyond the "all" option.
	const hasCategoryOptions = categoryOptions.length > 1;

	return (
		<div className="edd-checkout-templates__filters">
			<SearchControl
				__nextHasNoMarginBottom
				label={ __( 'Search templates', 'easy-digital-downloads' ) }
				value={ filters.search }
				onChange={ ( value ) => setFilter( 'search', value ) }
				placeholder={ __( 'Search...', 'easy-digital-downloads' ) }
			/>

			{ editorOptions.length > 1 && (
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Editor', 'easy-digital-downloads' ) }
					value={ filters.editor }
					options={ editorOptions }
					onChange={ ( value ) => setFilter( 'editor', value ) }
				/>
			) }

			{ hasCategoryOptions && (
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Category', 'easy-digital-downloads' ) }
					value={ filters.tag }
					options={ categoryOptions }
					onChange={ ( value ) => setFilter( 'tag', value ) }
				/>
			) }
		</div>
	);
};

export default TemplateFilters;
