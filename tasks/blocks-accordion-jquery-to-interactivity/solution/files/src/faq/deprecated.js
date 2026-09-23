/**
 * Earlier saved formats of the FAQ block.
 */
import { createBlock } from '@wordpress/blocks';
import {
	useBlockProps,
	useInnerBlocksProps,
	RichText,
} from '@wordpress/block-editor';

/**
 * 1.2 – 1.3: static wrapper around the question blocks.
 */
const v12 = {
	attributes: {
		openFirst: {
			type: 'boolean',
			default: false,
		},
	},
	supports: {
		html: false,
		anchor: true,
		align: [ 'wide' ],
		spacing: {
			margin: true,
			padding: true,
		},
	},
	save( { attributes } ) {
		const blockProps = useBlockProps.save( {
			className: 'acme-faq',
			'data-open-first': attributes.openFirst ? 'true' : undefined,
		} );
		return <div { ...useInnerBlocksProps.save( blockProps ) } />;
	},
};

/**
 * 1.0 – 1.1: all questions in one block, stored as a definition list.
 */
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
		return [ { openFirst: false, allowMultiple: false }, innerBlocks ];
	},
};

export default [ v12, v1 ];
