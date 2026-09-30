import { __ } from '@wordpress/i18n';
import { Disabled } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { useBlockProps } from '@wordpress/block-editor';
import './editor.scss';
import { queryArgs as baseQueryArgs } from '../utilities/query-args';

export default function Edit({ attributes, context }) {
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
			<Disabled>
				<ServerSideRender
					block="edd/checkout-payment-info"
					attributes={attributes}
					urlQueryArgs={queryArgs}
				/>
			</Disabled>
		</div>
	);
}
