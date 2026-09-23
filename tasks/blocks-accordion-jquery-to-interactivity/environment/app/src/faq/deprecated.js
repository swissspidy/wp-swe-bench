/**
 * FAQ 1.0/1.1: all questions in one block, stored as a definition list.
 */
import { createBlock } from '@wordpress/blocks';
import { useBlockProps, RichText } from '@wordpress/block-editor';

const v1 = {
	attributes: {
		items: {
			type: 'array',
			source: 'query',
			selector: '.acme-faq__item',
			query: {
				question: {
					type: 'string',
					source: 'html',
					selector: 'dt',
				},
				answer: {
					type: 'string',
					source: 'html',
					selector: 'dd',
				},
			},
			default: [],
		},
	},
	supports: {
		html: false,
	},
	save( { attributes } ) {
		return (
			<div { ...useBlockProps.save( { className: 'acme-faq' } ) }>
				<dl className="acme-faq__list">
					{ attributes.items.map( ( item, index ) => (
						<div className="acme-faq__item" key={ index }>
							<RichText.Content
								tagName="dt"
								className="acme-faq__question"
								value={ item.question }
							/>
							<RichText.Content
								tagName="dd"
								className="acme-faq__answer"
								value={ item.answer }
							/>
						</div>
					) ) }
				</dl>
			</div>
		);
	},
	migrate( attributes ) {
		const innerBlocks = ( attributes.items || [] ).map( ( item ) =>
			createBlock( 'acme/faq-item', { question: item.question }, [
				createBlock( 'core/paragraph', { content: item.answer } ),
			] )
		);
		return [ { openFirst: false }, innerBlocks ];
	},
};

export default [ v1 ];
