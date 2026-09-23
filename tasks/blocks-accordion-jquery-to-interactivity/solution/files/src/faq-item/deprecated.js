/**
 * Earlier saved formats of a question.
 */
import {
	useBlockProps,
	useInnerBlocksProps,
	RichText,
} from '@wordpress/block-editor';

/**
 * 1.2 – 1.3: static markup with the question in an <h3>.
 */
const v12 = {
	attributes: {
		question: {
			type: 'string',
			source: 'html',
			selector: '.acme-faq__question',
		},
	},
	supports: {
		html: false,
		anchor: true,
		reusable: false,
	},
	save( { attributes } ) {
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
	},
};

export default [ v12 ];
