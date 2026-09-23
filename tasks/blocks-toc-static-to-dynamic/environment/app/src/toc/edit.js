/**
 * Editor UI.
 *
 * The list is computed from the heading blocks in the editor and stored in
 * the `headings` attribute. Headings without an HTML anchor get one assigned
 * so that the links in the saved list work.
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
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

import { flatten, headingText, slugify, uniqueAnchor } from './utils';

const settings = window.acmeTocSettings || {};
const SITE_EXCLUDED = settings.excludedBlocks || [ 'core/details' ];

export default function Edit( { attributes, setAttributes, clientId } ) {
	const { title, maxLevel, excludedBlocks, headings } = attributes;
	const { updateBlockAttributes, __unstableMarkNextChangeAsNotPersistent } =
		useDispatch( blockEditorStore );

	const allBlocks = useSelect(
		( select ) => select( blockEditorStore ).getBlocks(),
		[]
	);

	const excluded = [ ...SITE_EXCLUDED, ...( excludedBlocks || [] ) ];

	// Collect headings and assign anchors.
	const used = new Set();
	const items = [];
	const missingAnchors = [];
	const flat = flatten( allBlocks ).filter(
		( { block } ) => block.name === 'core/heading'
	);
	flat.forEach( ( { block } ) => {
		if ( block.attributes.anchor ) {
			used.add( block.attributes.anchor );
		}
	} );
	flat.forEach( ( { block, parents } ) => {
		const level = block.attributes.level || 2;
		const text = headingText( block.attributes.content );
		if ( ! text || level < 2 || level > maxLevel ) {
			return;
		}
		if ( parents.some( ( name ) => excluded.includes( name ) ) ) {
			return;
		}
		let anchor = block.attributes.anchor;
		if ( ! anchor ) {
			anchor = uniqueAnchor( slugify( text ), used );
			missingAnchors.push( [ block.clientId, anchor ] );
		}
		items.push( { level, text, anchor } );
	} );

	useEffect( () => {
		// Persist anchors on the headings so the saved links resolve.
		missingAnchors.forEach( ( [ id, anchor ] ) => {
			__unstableMarkNextChangeAsNotPersistent();
			updateBlockAttributes( id, { anchor } );
		} );
		if ( JSON.stringify( items ) !== JSON.stringify( headings ) ) {
			__unstableMarkNextChangeAsNotPersistent();
			setAttributes( { headings: items } );
		}
	} );

	const blockProps = useBlockProps( { className: 'acme-toc' } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'acme-toc' ) }>
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
					value={ title }
					allowedFormats={ [] }
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
