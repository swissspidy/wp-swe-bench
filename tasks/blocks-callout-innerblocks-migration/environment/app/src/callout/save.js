/**
 * Saved markup (v1.3+).
 */
import { RichText, useBlockProps } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const { type, title, content } = attributes;
	const blockProps = useBlockProps.save( {
		className: `acme-callout acme-callout--${ type }`,
	} );

	return (
		<div { ...blockProps }>
			{ title && (
				<RichText.Content
					tagName="p"
					className="acme-callout__title"
					value={ title }
				/>
			) }
			<RichText.Content
				tagName="div"
				className="acme-callout__content"
				value={ content }
			/>
		</div>
	);
}
