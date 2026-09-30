import { sprintf, __ } from "@wordpress/i18n";
import { useSelect } from '@wordpress/data';
import { SearchCombobox } from './search-combobox';

/**
 * A searchable download selector for use in block editor panels.
 *
 * @param {Object}   props
 * @param {string}   props.value    The currently selected download ID.
 * @param {Function} props.onChange Called with the new download ID when selection changes.
 * @param {string}   props.help     Optional help text shown below the combobox.
 */
export const DownloadCombobox = ( { value, onChange, help } ) => {
	// Resolve the saved download's title so the combobox shows a name (not an ID)
	// when the saved record is outside the current search results.
	const savedDownload = useSelect(
		( select ) => {
			const id = Number.parseInt( value, 10 );
			return id ? select( 'core' ).getEntityRecord( 'postType', 'download', id ) : null;
		},
		[ value ]
	);
	// `getEntityRecord` returns `undefined` while resolving, `null` if not found, or the record.
	// Treat `undefined` as the loading state so the combobox shows "Loading…" instead of the ID.
	const isLoadingFallback = !! value && savedDownload === undefined;
	const fallbackLabel = savedDownload?.title?.rendered ?? savedDownload?.title?.raw ?? '';

	return (
		<SearchCombobox
			action="edd_download_search"
			mapOption={ ( d ) => ( { value: String( d.id ), label: d.name } ) }
			/* translators: %s: Download label singular */
			label={ sprintf( __( 'Select a %s', 'easy-digital-downloads' ), EDDBlocks.download_label_singular ) }
			/* translators: %s: Download label singular */
			placeholder={ sprintf( __( 'Search for a %s', 'easy-digital-downloads' ), EDDBlocks.download_label_singular ) }
			help={ help }
			value={ value }
			fallbackLabel={ fallbackLabel }
			isLoadingFallback={ isLoadingFallback }
			onChange={ onChange }
		/>
	);
};
