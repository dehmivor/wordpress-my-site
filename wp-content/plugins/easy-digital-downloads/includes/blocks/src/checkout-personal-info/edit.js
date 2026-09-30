import { __ } from '@wordpress/i18n';
import { Disabled, PanelBody, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import './editor.scss';
import { queryArgs as baseQueryArgs } from '../utilities/query-args';

export default function Edit({ attributes, setAttributes, context }) {
	// Get preview data from parent checkout block
	const { 'edd/previewMode': previewMode, 'edd/previewDownload': previewDownload } = context;

	// Build query args for ServerSideRender based on context
	const queryArgs = {
		...( previewMode && { preview: true } ),
		...( previewDownload && { cart_item: previewDownload } ),
		...baseQueryArgs
	};
	return (
		<div {...useBlockProps()}>
			<InspectorControls>
				<PanelBody title={__('Personal Info Settings', 'easy-digital-downloads')}>
					<ToggleControl
						label={__('Single Line Name', 'easy-digital-downloads')}
						checked={attributes.name_single_line}
						onChange={(value) => setAttributes({ name_single_line: value })}
						help={__('Show First Name and Last Name side by side instead of stacked.', 'easy-digital-downloads')}
					/>
				</PanelBody>
			</InspectorControls>
			<Disabled>
				<ServerSideRender
					block="edd/checkout-personal-info"
					attributes={attributes}
					urlQueryArgs={queryArgs}
				/>
			</Disabled>
		</div>
	);
}
