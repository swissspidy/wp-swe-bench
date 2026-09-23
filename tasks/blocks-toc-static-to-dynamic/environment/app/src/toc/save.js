/**
 * Static markup (1.3+). The heading list is taken from the `headings`
 * attribute, which the editor keeps in sync while the post is edited.
 */
import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const { title, headings } = attributes;
	const blockProps = useBlockProps.save( {
		'aria-label': title || 'Table of contents',
	} );

	return (
		<nav { ...blockProps }>
			{ ! RichText.isEmpty( title ) && (
				<RichText.Content
					tagName="p"
					className="acme-toc__title"
					value={ title }
				/>
			) }
			<ol className="acme-toc__list">
				{ headings.map( ( heading, index ) => (
					<li
						key={ index }
						className={ `acme-toc__item acme-toc__item--h${ heading.level }` }
					>
						<a href={ `#${ heading.anchor }` }>{ heading.text }</a>
					</li>
				) ) }
			</ol>
		</nav>
	);
}
