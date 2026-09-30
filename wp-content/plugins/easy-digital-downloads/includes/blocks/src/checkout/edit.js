import { __, sprintf } from '@wordpress/i18n';
import { PanelBody, ToggleControl, Notice } from '@wordpress/components';
import { useBlockProps, InspectorControls, InnerBlocks, BlockContextProvider } from '@wordpress/block-editor';
import { useSelect, useDispatch } from '@wordpress/data';
import { parse } from '@wordpress/blocks';
import './editor.scss';
import { useState, createInterpolateElement } from '@wordpress/element';
import { DownloadCombobox } from '../utilities/downloads';
import { LayoutPicker } from './patterns';

// Required blocks that must be present for checkout to function
const REQUIRED_BLOCKS = [
	'edd/checkout-personal-info',
	'edd/checkout-payment-info'
];

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/block-edit-save/#edit
 *
 * @return {WPElement} Element to render.
 */
export default function Edit ( { attributes, setAttributes, clientId } ) {
	// Use local state instead of attributes to avoid saving preview data
	const [ previewMode, setPreviewMode ] = useState( true );
	const [ previewDownload, setPreviewDownload ] = useState( '' );

	const { replaceInnerBlocks } = useDispatch( 'core/block-editor' );

	// Get all inner blocks to check for required blocks
	const innerBlocks = useSelect( ( select ) => {
		return select( 'core/block-editor' ).getBlocks( clientId );
	}, [ clientId ] );

	// Patterns tied to edd/checkout, used for the layout chooser on first insert.
	const patterns = useSelect( ( select ) => {
		return ( select( 'core' ).getBlockPatterns() ?? [] ).filter(
			( p ) => p.blockTypes?.includes( 'edd/checkout' )
		);
	}, [] );

	// Check which required blocks are missing
	const missingBlocks = REQUIRED_BLOCKS.filter( requiredBlock => {
		return ! innerBlocks.some( block => {
			// Check if this block or any nested block is the required type
			const hasRequiredBlock = ( blocks ) => {
				return blocks.some( b =>
					b.name === requiredBlock ||
					( b.innerBlocks && hasRequiredBlock( b.innerBlocks ) )
				);
			};
			return hasRequiredBlock( [ block ] );
		} );
	} );

	// Update local state (these get passed to child blocks via context)
	const handlePreviewChange = ( isChecked ) => {
		setPreviewMode( isChecked );
	};

	const handleDownloadChange = ( value ) => {
		setPreviewDownload( value );
	};

	// Show layout chooser when no blocks have been added yet.
	if ( innerBlocks.length === 0 && patterns.length > 0 ) {
		return (
			<div { ...useBlockProps() }>
				<div className="edd-checkout-pattern-picker">
					<p className="edd-checkout-pattern-picker__label">
						{ __( 'Choose a checkout layout', 'easy-digital-downloads' ) }
					</p>
					<LayoutPicker
						patterns={ patterns }
						onSelect={ ( pattern ) => replaceInnerBlocks( clientId, parse( pattern.content ), true ) }
					/>
				</div>
			</div>
		);
	}

	return (
		<div {...useBlockProps()}>
			<InspectorControls>
				{ patterns.length > 0 && (
					<PanelBody title={ __( 'Layout', 'easy-digital-downloads' ) }>
						<p className="edd-checkout-layout-panel__description">
							{ __( 'Switching layouts replaces all inner blocks.', 'easy-digital-downloads' ) }
						</p>
						<LayoutPicker
							patterns={ patterns }
							onSelect={ ( pattern ) => replaceInnerBlocks( clientId, parse( pattern.content ), true ) }
						/>
					</PanelBody>
				) }
				<PanelBody title={__( 'Preview', 'easy-digital-downloads' )}>
					<ToggleControl
						label={__( 'Preview as Guest', 'easy-digital-downloads' )}
						checked={!!previewMode}
						onChange={handlePreviewChange}
						help={__( 'Preview how the checkout appears to guest users.', 'easy-digital-downloads' )}
					/>
					<DownloadCombobox
						value={ previewDownload ?? '' }
						onChange={ handleDownloadChange }
						help={ __( 'Select a product to preview the checkout form with a specific item in the cart.', 'easy-digital-downloads' ) }
					/>
				</PanelBody>
			</InspectorControls>

			{/* Show validation notice if required blocks are missing */}
			{missingBlocks.length > 0 && (
				<Notice status="warning" isDismissible={false}>
					<p>
						{__( 'Required checkout components are missing:', 'easy-digital-downloads' )}
					</p>
					<ul>
						{missingBlocks.map( blockName => (
							<li key={blockName}>
								{blockName === 'edd/checkout-personal-info' && __( 'Personal Information', 'easy-digital-downloads' )}
								{blockName === 'edd/checkout-payment-info' && __( 'Payment Information', 'easy-digital-downloads' )}
							</li>
						))}
					</ul>
					<p>
						{__( 'Add these blocks for the checkout to function properly.', 'easy-digital-downloads' )}
					</p>
				</Notice>
			)}

			<div>
				<p className="description">{__( 'Drag and drop checkout components and any other blocks to customize your layout.', 'easy-digital-downloads' )}</p>
				<BlockContextProvider value={{
					'edd/previewMode': previewMode,
					'edd/previewDownload': previewDownload
				}}>
					{! previewMode && (
						<p className="edd-blocks__logged-in">
							<strong>{__( 'Account Information', 'easy-digital-downloads' )}:</strong>
							&nbsp;
							<span>
								{ createInterpolateElement(
									sprintf(
										/* translators: %s: The current user's email address. */
										__( 'You are currently logged in as %s. (<a>log out</a>)', 'easy-digital-downloads' ),
										EDDBlocks.current_user_email || 'user@example.com'
									),
									{ a: <a href="" /> }
								) }
							</span>
						</p>
					)}
					<InnerBlocks
						templateLock={ false }
					/>
				</BlockContextProvider>
			</div>
		</div>
	);
}
