/**
 * Saved markup (1.2+): wrapper around the question items.
 */
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const blockProps = useBlockProps.save( {
		className: 'acme-faq',
		'data-open-first': attributes.openFirst ? 'true' : undefined,
	} );
	return <div { ...useInnerBlocksProps.save( blockProps ) } />;
}
