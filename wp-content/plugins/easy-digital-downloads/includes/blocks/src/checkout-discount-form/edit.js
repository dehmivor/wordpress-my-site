import { __ } from '@wordpress/i18n';
import { Disabled } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { useBlockProps } from '@wordpress/block-editor';
import './editor.scss';

export default function Edit({ attributes }) {
	return (
		<div {...useBlockProps()}>
			<Disabled>
				<ServerSideRender
					block="edd/checkout-discount-form"
					attributes={attributes}
				/>
			</Disabled>
		</div>
	);
}
