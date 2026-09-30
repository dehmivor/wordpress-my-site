import { sprintf, __ } from '@wordpress/i18n';
import { Disabled, PanelBody, Placeholder, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import './editor.scss';
import { DownloadCombobox } from '../utilities/downloads';
import { DiscountCombobox } from '../utilities/discounts';
import { newDownload } from '../utilities/download-new';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/block-edit-save/#edit
 *
 * @return {WPElement} Element to render.
 */
export default function Edit ( { attributes, setAttributes } ) {
	if ( !EDDBlocks.has_published_downloads ) {
		return newDownload();
	}

	const toggleAttribute = ( attributeName ) => ( newValue ) =>
		setAttributes( { [ attributeName ]: newValue } );
	const postType = wp.data.select( 'core/editor' ).getCurrentPostType();

	if ( !attributes.download_id && postType && 'download' !== postType ) {
		return <div {...useBlockProps()}>
			<Placeholder
				icon="download"
				/* translators: %s: Download label singular */
				label={ sprintf( __( 'Select a %s:', 'easy-digital-downloads' ), EDDBlocks.download_label_singular ) }
			>
				<DownloadCombobox
					value={ attributes.download_id ?? '' }
					onChange={ toggleAttribute( 'download_id' ) }
				/>
			</Placeholder>
		</div>
	}

	if ( !attributes.download_id && 'download' === postType ) {
		attributes.download_id = wp.data.select( 'core/editor' ).getCurrentPostId();
	}

	return (
		<div {...useBlockProps()}>
			<InspectorControls>
				<PanelBody
					title={__( 'Settings', 'easy-digital-downloads' )}
				>
					<DownloadCombobox
						value={ attributes.download_id ? String( attributes.download_id ) : '' }
						onChange={ toggleAttribute( 'download_id' ) }
					/>
					<ToggleControl
						label={__( 'Show Price', 'easy-digital-downloads' )}
						checked={!!attributes.show_price}
						onChange={toggleAttribute( 'show_price' )}
					/>
					{!!EDDBlocks.supports_buy_now && (
						<ToggleControl
							label={__( 'Buy Now', 'easy-digital-downloads' )}
							checked={!!attributes.direct}
							onChange={toggleAttribute( 'direct' )}
							help={__( 'Enable Buy Now to process a download order without going through the full checkout.', 'easy-digital-downloads' )}
						/>
					)}
					{!attributes.direct && EDDBlocks.manage_shop_discounts && (
						<DiscountCombobox
							value={attributes.discount}
							onChange={toggleAttribute( 'discount' )}
						/>
					)}
				</PanelBody>
			</InspectorControls>
			<Disabled>
				<ServerSideRender
					block="edd/buy-button"
					attributes={{ ...attributes }}
				/>
			</Disabled>
		</div>
	);
}
