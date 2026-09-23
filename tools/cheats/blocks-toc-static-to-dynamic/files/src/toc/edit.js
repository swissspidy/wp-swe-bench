/**
 * Editor UI.
 *
 * The front end is rendered on the server from the current headings of the
 * post. The editor shows a live preview computed from the heading blocks with
 * the same anchor scheme; it never changes the headings themselves.
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	FormTokenField,
	Notice,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';

import { flatten, headingText, slugify, uniqueAnchor } from './utils';

const settings = window.acmeTocSettings || {};
const SITE_EXCLUDED = settings.excludedBlocks || [ 'core/details' ];

/**
 * Preview items: same selection and anchor rules as the server.
 *
 * @param {Object[]} blocks   Editor blocks.
 * @param {Object}   options  { minLevel, maxLevel, excluded }.
 * @return {Object[]} Items.
 */
function previewItems( blocks, { minLevel, maxLevel, excluded } ) {
	const flat = flatten( blocks ).filter(
		( { block } ) => block.name === 'core/heading'
	);
	const used = new Set();
	flat.forEach( ( { block } ) => {
		if ( block.attributes.anchor ) {
			used.add( block.attributes.anchor );
		}
	} );
	const items = [];
	flat.forEach( ( { block, parents } ) => {
		const level = block.attributes.level || 2;
		const text = headingText( block.attributes.content );
		if ( ! text ) {
			return;
		}
		const anchor =
			block.attributes.anchor || uniqueAnchor( slugify( text ), used );
		if ( level < minLevel || level > maxLevel ) {
			return;
		}
		if ( parents.some( ( name ) => excluded.includes( name ) ) ) {
			return;
		}
		items.push( { level, text, anchor } );
	} );
	return items;
}

export default function Edit( { attributes, setAttributes } ) {
	const { title, minLevel, maxLevel, excludedBlocks } = attributes;

	const allBlocks = useSelect(
		( select ) => select( blockEditorStore ).getBlocks(),
		[]
	);

	const items = previewItems( allBlocks, {
		minLevel: Math.min( minLevel, maxLevel ),
		maxLevel: Math.max( minLevel, maxLevel ),
		excluded: [ ...SITE_EXCLUDED, ...( excludedBlocks || [] ) ],
	} );

	const blockProps = useBlockProps( { className: 'acme-toc' } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'acme-toc' ) }>
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Highest heading level', 'acme-toc' ) }
						min={ 2 }
						max={ 6 }
						value={ minLevel }
						onChange={ ( value ) =>
							setAttributes( { minLevel: value || 2 } )
						}
					/>
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Deepest heading level', 'acme-toc' ) }
						min={ 2 }
						max={ 6 }
						value={ maxLevel }
						onChange={ ( value ) =>
							setAttributes( { maxLevel: value || 3 } )
						}
					/>
					<FormTokenField
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __(
							'Skip headings inside these blocks',
							'acme-toc'
						) }
						value={ excludedBlocks }
						onChange={ ( value ) =>
							setAttributes( { excludedBlocks: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<nav { ...blockProps }>
				<RichText
					tagName="p"
					className="acme-toc__title"
					value={ title ?? __( 'Table of contents', 'acme-toc' ) }
					allowedFormats={ [ 'core/bold', 'core/italic' ] }
					onChange={ ( value ) => setAttributes( { title: value } ) }
					placeholder={ __( 'Table of contents', 'acme-toc' ) }
				/>
				{ items.length === 0 ? (
					<Notice status="info" isDismissible={ false }>
						{ __(
							'Add headings to the post to build the table of contents.',
							'acme-toc'
						) }
					</Notice>
				) : (
					<ol className="acme-toc__list">
						{ items.map( ( item ) => (
							<li
								key={ item.anchor }
								className={ `acme-toc__item acme-toc__item--h${ item.level }` }
							>
								<a
									href={ `#${ item.anchor }` }
									onClick={ ( event ) =>
										event.preventDefault()
									}
									aria-label={ sprintf(
										/* translators: %s: heading text */
										__( 'Heading: %s', 'acme-toc' ),
										item.text
									) }
								>
									{ item.text }
								</a>
							</li>
						) ) }
					</ol>
				) }
			</nav>
		</>
	);
}
