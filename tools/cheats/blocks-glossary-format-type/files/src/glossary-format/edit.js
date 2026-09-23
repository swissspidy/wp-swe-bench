/**
 * Toolbar button + term picker of the "Glossary term" format.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { useDebounce } from '@wordpress/compose';
import { RichTextToolbarButton } from '@wordpress/block-editor';
import { applyFormat, removeFormat, useAnchor } from '@wordpress/rich-text';
import {
	Button,
	Popover,
	SearchControl,
	Spinner,
	__experimentalVStack as VStack,
} from '@wordpress/components';

const NAME = 'acme/glossary-term';

function TermSearch( { onSelect } ) {
	const [ search, setSearch ] = useState( '' );
	const [ query, setQuery ] = useState( '' );
	const [ results, setResults ] = useState( null );
	const debouncedSetQuery = useDebounce( setQuery, 250 );

	useEffect( () => {
		debouncedSetQuery( search.trim() );
	}, [ search, debouncedSetQuery ] );

	useEffect( () => {
		if ( ! query ) {
			setResults( null );
			return;
		}
		let active = true;
		setResults( undefined );
		apiFetch( {
			path: addQueryArgs( '/acme-glossary/v1/terms', { search: query, per_page: 10 } ),
		} )
			.then( ( terms ) => active && setResults( terms ) )
			.catch( () => active && setResults( [] ) );
		return () => {
			active = false;
		};
	}, [ query ] );

	return (
		<VStack className="acme-glossary-picker" spacing={ 2 }>
			<SearchControl
				label={ __( 'Search glossary terms', 'acme-glossary' ) }
				value={ search }
				onChange={ setSearch }
				// eslint-disable-next-line jsx-a11y/no-autofocus
				autoFocus
				__nextHasNoMarginBottom
			/>
			{ results === undefined && <Spinner /> }
			{ Array.isArray( results ) && results.length === 0 && (
				<p>{ __( 'No terms found.', 'acme-glossary' ) }</p>
			) }
			{ Array.isArray( results ) && results.length > 0 && (
				<ul role="listbox" aria-label={ __( 'Glossary terms', 'acme-glossary' ) }>
					{ results.map( ( term ) => (
						<li key={ term.id }>
							<Button
								role="option"
								aria-selected={ false }
								variant="tertiary"
								onClick={ () => onSelect( term ) }
								label={ term.title }
								showTooltip={ false }
							>
								{ term.title }
							</Button>
						</li>
					) ) }
				</ul>
			) }
		</VStack>
	);
}

export default function Edit( { isActive, value, onChange, onFocus, contentRef } ) {
	const [ isOpen, setIsOpen ] = useState( false );
	const anchor = useAnchor( { editableContentElement: contentRef.current, settings: { tagName: 'span', className: 'acme-glossary-term' } } );

	const onToggle = () => {
		if ( isActive ) {
			onChange( removeFormat( value, NAME ) );
			return;
		}
		setIsOpen( true );
	};

	const onSelect = ( term ) => {
		onChange(
			applyFormat( value, {
				type: NAME,
				attributes: { termId: String( term.id ) },
			} )
		);
		setIsOpen( false );
		onFocus();
	};

	return (
		<>
			<RichTextToolbarButton
				icon="book-alt"
				title={ __( 'Glossary term', 'acme-glossary' ) }
				onClick={ onToggle }
				isActive={ isActive }
				isDisabled={ ! isActive && value.start === value.end }
			/>
			{ isOpen && (
				<Popover
					anchor={ anchor }
					placement="bottom"
					focusOnMount="firstElement"
					onClose={ () => setIsOpen( false ) }
					className="acme-glossary-popover"
				>
					<div style={ { padding: '12px', minWidth: '260px' } }>
						<TermSearch onSelect={ onSelect } />
						<p className="description">
							{ sprintf(
								/* translators: %s: selected text. */
								__( 'Mark “%s” as a glossary term.', 'acme-glossary' ),
								value.text.slice( value.start, value.end )
							) }
						</p>
					</div>
				</Popover>
			) }
		</>
	);
}
