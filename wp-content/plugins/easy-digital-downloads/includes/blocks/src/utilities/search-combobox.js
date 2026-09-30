import { useState, useEffect, useMemo } from '@wordpress/element';
import { ComboboxControl } from '@wordpress/components';
import { useDebounce } from '@wordpress/compose';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Generic live-search combobox backed by an EDD AJAX endpoint.
 *
 * @param {Object}   props
 * @param {string}   props.action            AJAX action name.
 * @param {Object}   props.params            Additional URL params merged into the request.
 * @param {Function} props.mapOption         Maps each result item to { value, label }.
 * @param {string}   props.label             ComboboxControl label.
 * @param {string}   props.help              ComboboxControl help text.
 * @param {string}   props.value             Currently selected value.
 * @param {Function} props.onChange          Called with the new value on selection change.
 * @param {string}   props.fallbackLabel     Label to display when the saved value is not in the current options.
 * @param {boolean}  props.isLoadingFallback Whether the fallback label is still being resolved (shows "Loading…").
 * @param {string}   props.placeholder       Placeholder text shown in the input when empty.
 */
export const SearchCombobox = ( { action, params = {}, mapOption, label, help, value, onChange, fallbackLabel, isLoadingFallback, placeholder } ) => {
	const [ options, setOptions ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( true );

	const fetchOptions = async ( search ) => {
		setIsLoading( true );
		const urlParams = new URLSearchParams( {
			action,
			s: search ?? '',
			...params,
		} );

		try {
			const results = await apiFetch( {
				url: `${ globalThis.ajaxurl }?${ urlParams.toString() }`,
			} );
			setOptions( Object.values( results ).map( ( item ) => mapOption( item ) ) );
		} catch ( e ) {
			console.error( e );
			setOptions( [] );
		} finally {
			setIsLoading( false );
		}
	};

	const debouncedFetch = useDebounce( fetchOptions, 300 );

	useEffect( () => {
		fetchOptions( '' );
	}, [] );

	// Always ensure the saved value is present so ComboboxControl can display
	// it while the async fetch is in flight or if it was removed from results.
	const allOptions = useMemo( () => {
		if ( value && ! options.some( ( o ) => o.value === value ) ) {
			const placeholderLabel = isLoadingFallback ? __( 'Loading…', 'easy-digital-downloads' ) : value;
			return [ { value, label: fallbackLabel || placeholderLabel }, ...options ];
		}
		return options;
	}, [ value, options, fallbackLabel, isLoadingFallback ] );

	return (
		<ComboboxControl
			className="edd-search-combobox"
			label={ label }
			help={ help }
			value={ value || '' }
			options={ allOptions }
			isLoading={ isLoading }
			placeholder={ placeholder }
			onFilterValueChange={ debouncedFetch }
			onChange={ ( newValue ) => onChange( newValue ?? '' ) }
		/>
	);
};
