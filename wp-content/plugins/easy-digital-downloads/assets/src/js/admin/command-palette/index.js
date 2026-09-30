/**
 * EDD command palette integration.
 *
 * Registers EDD's static "jump to" commands and a single debounced search
 * loader with WordPress's command palette (WP 6.9+). Only enqueued when
 * `EDD\CommandPalette\Assets::is_supported()` passes, so the palette store is
 * always present here.
 */
import { dispatch } from '@wordpress/data';
import { store as commandsStore } from '@wordpress/commands';
import { useEffect, useMemo, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

const settings = globalThis.eddCommandPalette ?? {};

/**
 * Navigate to a URL and close the palette.
 *
 * @param {string}   url   The destination URL.
 * @param {Function} close The palette close callback.
 */
const navigate = ( url, close ) => {
	if ( typeof close === 'function' ) {
		close();
	}
	globalThis.location.href = url;
};

/**
 * Build a palette command from an EDD navigation item or search result.
 *
 * @param {Object} item             The item to convert.
 * @param {string} item.name        Unique command name.
 * @param {string} item.label       The label shown in the palette.
 * @param {string} item.url         The destination URL.
 * @param {string} item.keywords    Term the command is also matched on, so a result
 *                                  is found by what it is ("orders", "settings").
 * @param {string} item.searchLabel Optional string the palette matches on instead
 *                                  of the label, so a command can rank on its
 *                                  sub-view rather than on the brand it displays.
 * @return {Object} A command for the palette store.
 */
const toCommand = ( item ) => ( {
	name: item.name,
	label: item.label,
	...( item.searchLabel && { searchLabel: item.searchLabel } ),
	keywords: item.keywords ? [ item.keywords ] : [],
	category: 'view',
	callback: ( { close } ) => navigate( item.url, close ),
} );

/**
 * Debounce a changing value.
 *
 * @param {string} value The value to debounce.
 * @param {number} delay Delay in milliseconds.
 * @return {string} The debounced value.
 */
const useDebounced = ( value, delay ) => {
	const [ debounced, setDebounced ] = useState( value );

	useEffect( () => {
		const timer = globalThis.setTimeout( () => setDebounced( value ), delay );
		return () => globalThis.clearTimeout( timer );
	}, [ value, delay ] );

	return debounced;
};

/**
 * Command loader hook: searches EDD sources for the current palette query.
 *
 * @param {Object} props        Loader props.
 * @param {string} props.search The current palette search string.
 * @return {Object} The `{ commands, isLoading }` result for the palette.
 */
const useEddSearchCommands = ( { search } ) => {
	const [ results, setResults ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( false );
	const minChars = settings.minChars ?? 2;
	const debounced = useDebounced( ( search ?? '' ).trim(), 250 );

	useEffect( () => {
		if ( ! settings.searchPath || debounced.length < minChars ) {
			setResults( [] );
			return undefined;
		}

		let cancelled = false;
		setIsLoading( true );

		apiFetch( {
			path: addQueryArgs( settings.searchPath, { search: debounced } ),
		} )
			.then( ( response ) => {
				if ( ! cancelled ) {
					setResults( response?.results ?? [] );
				}
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setResults( [] );
				}
			} )
			.finally( () => {
				if ( ! cancelled ) {
					setIsLoading( false );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ debounced, minChars ] );

	const commands = useMemo( () => results.map( toCommand ), [ results ] );

	return { commands, isLoading };
};

/**
 * Initialize the EDD command palette integration.
 */
const init = () => {
	const { registerCommand, registerCommandLoader } = dispatch( commandsStore );

	// Static "jump to" navigation commands.
	( settings.navigation ?? [] ).forEach( ( item ) => registerCommand( toCommand( item ) ) );

	// Single debounced search loader for all EDD sources.
	registerCommandLoader( {
		name: 'edd/search',
		hook: useEddSearchCommands,
	} );
};

init();
