/**
 * Saved markup (2.0+).
 *
 * Themes style `.wp-block-acme-cta.is-variant-{variant}`, `__heading` and
 * `__button`; keep these class names stable.
 */
import { RichText, useBlockProps } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const {
		heading,
		headingLevel,
		buttonText,
		buttonUrl,
		variant,
		opensInNewTab,
	} = attributes;
	const blockProps = useBlockProps.save( {
		className: `is-variant-${ variant }`,
	} );

	return (
		<div { ...blockProps }>
			{ ! RichText.isEmpty( heading ) && (
				<RichText.Content
					tagName={ `h${ headingLevel }` }
					className="wp-block-acme-cta__heading"
					value={ heading }
				/>
			) }
			<RichText.Content
				tagName="a"
				className="wp-block-acme-cta__button wp-element-button"
				href={ buttonUrl || undefined }
				target={ opensInNewTab ? '_blank' : undefined }
				rel={ opensInNewTab ? 'noopener noreferrer' : undefined }
				value={ buttonText }
			/>
		</div>
	);
}
