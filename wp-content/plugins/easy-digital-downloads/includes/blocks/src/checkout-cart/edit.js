import { __ } from '@wordpress/i18n';
import { Disabled, PanelBody, ToggleControl, RangeControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import './editor.scss';
import { queryArgs as baseQueryArgs } from '../utilities/query-args';

export default function Edit({ attributes, setAttributes, context }) {
	// Get preview data from parent checkout block
	const { 'edd/previewMode': previewMode, 'edd/previewDownload': previewDownload } = context;

	// merge query args with base query args
	const queryArgs = {
		...( previewMode && { preview: true } ),
		...( previewDownload && { cart_item: previewDownload } ),
		...baseQueryArgs
	};

	return (
		<div {...useBlockProps()}>
			<InspectorControls>
				<PanelBody title={__('Cart Settings', 'easy-digital-downloads')}>
					<ToggleControl
						label={__('Show Header Row', 'easy-digital-downloads')}
						checked={attributes.show_header}
						onChange={(value) => setAttributes({ show_header: value })}
					/>
					<ToggleControl
						label={__('Show Thumbnails', 'easy-digital-downloads')}
						checked={attributes.show_thumbnails}
						onChange={(value) => setAttributes({ show_thumbnails: value })}
					/>
					{attributes.show_thumbnails && (
						<RangeControl
							label={__('Thumbnail Width (px)', 'easy-digital-downloads')}
							value={attributes.thumbnail_width}
							onChange={(value) => setAttributes({ thumbnail_width: value })}
							min={10}
							max={100}
							step={1}
						/>
					)}
					{EDDBlocks.quantities_enabled && (
						<ToggleControl
							label={__('Show Quantity Controls', 'easy-digital-downloads')}
							checked={attributes.show_quantity_controls}
							onChange={(value) => setAttributes({ show_quantity_controls: value })}
						/>
					)}
					<ToggleControl
						label={__('Show Discount Form', 'easy-digital-downloads')}
						checked={attributes.show_discount_form}
						onChange={(value) => setAttributes({ show_discount_form: value })}
						help={__('Show the discount code form within the cart. Uncheck to hide it or use a separate Discount Form block.', 'easy-digital-downloads')}
					/>
				</PanelBody>
			</InspectorControls>
			<Disabled>
				<ServerSideRender
					block="edd/checkout-cart"
					attributes={attributes}
					urlQueryArgs={queryArgs}
				/>
			</Disabled>
		</div>
	);
}
