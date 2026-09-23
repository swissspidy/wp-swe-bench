/**
 * Editor preview of the glossary index.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, Placeholder, Spinner, ToggleControl } from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
	const { showLetters } = attributes;
	const [ terms, setTerms ] = useState( null );

	useEffect( () => {
		let active = true;
		apiFetch( { path: '/acme-glossary/v1/terms?per_page=100' } )
			.then( ( result ) => active && setTerms( result ) )
			.catch( () => active && setTerms( [] ) );
		return () => {
			active = false;
		};
	}, [] );

	return (
		<div { ...useBlockProps() }>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'acme-glossary' ) }>
					<ToggleControl
						label={ __( 'Show letter headings', 'acme-glossary' ) }
						checked={ showLetters }
						onChange={ ( value ) => setAttributes( { showLetters: value } ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>
			<Placeholder icon="book-alt" label={ __( 'Glossary index', 'acme-glossary' ) }>
				{ terms === null ? (
					<Spinner />
				) : (
					sprintf(
						/* translators: %d: number of terms. */
						_n( '%d term, listed A–Z on the site.', '%d terms, listed A–Z on the site.', terms.length, 'acme-glossary' ),
						terms.length
					)
				) }
			</Placeholder>
		</div>
	);
}
