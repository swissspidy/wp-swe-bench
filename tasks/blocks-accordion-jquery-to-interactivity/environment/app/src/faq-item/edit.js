/**
 * Editor UI for one question.
 */
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	RichText,
} from '@wordpress/block-editor';

const ANSWER_BLOCKS = [ 'core/paragraph', 'core/list', 'core/image' ];
const TEMPLATE = [ [ 'core/paragraph' ] ];

export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps( { className: 'acme-faq__item is-open' } );
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'acme-faq__answer' },
		{ allowedBlocks: ANSWER_BLOCKS, template: TEMPLATE }
	);

	return (
		<div { ...blockProps }>
			<RichText
				tagName="h3"
				className="acme-faq__question"
				value={ attributes.question }
				allowedFormats={ [ 'core/bold', 'core/italic', 'core/code' ] }
				onChange={ ( question ) => setAttributes( { question } ) }
				placeholder={ __( 'Question', 'acme-faq' ) }
			/>
			<div { ...innerBlocksProps } />
		</div>
	);
}
