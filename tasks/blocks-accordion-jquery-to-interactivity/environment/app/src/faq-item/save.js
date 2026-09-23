/**
 * Saved markup of one question.
 */
import {
	useBlockProps,
	useInnerBlocksProps,
	RichText,
} from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const blockProps = useBlockProps.save( { className: 'acme-faq__item' } );
	return (
		<div { ...blockProps }>
			<RichText.Content
				tagName="h3"
				className="acme-faq__question"
				value={ attributes.question }
			/>
			<div
				{ ...useInnerBlocksProps.save( {
					className: 'acme-faq__answer',
				} ) }
			/>
		</div>
	);
}
