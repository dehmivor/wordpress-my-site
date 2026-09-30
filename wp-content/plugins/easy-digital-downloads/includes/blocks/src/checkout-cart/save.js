import { useBlockProps } from '@wordpress/block-editor';

export default function Save( { attributes } ) {
	const blockProps = useBlockProps.save();

	return (
		<div {...blockProps}>
			{/* Content will be rendered server-side by parent checkout block */}
		</div>
	);
}
