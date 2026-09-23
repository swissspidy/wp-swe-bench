/**
 * Shared editor implementation of the field blocks.
 */
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls, PlainText } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl, TextareaControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

/**
 * Field key from a label ("Phone number" → "phone-number").
 *
 * @param {string} value Label.
 * @return {string} Key.
 */
export function toFieldName( value ) {
	return String( value || '' )
		.toLowerCase()
		.normalize( 'NFD' )
		.replace( /[̀-ͯ]/g, '' )
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^-+|-+$/g, '' )
		.slice( 0, 40 );
}

function Preview( { type, options } ) {
	switch ( type ) {
		case 'textarea':
			return <textarea rows={ 4 } disabled />;
		case 'select':
			return (
				<select disabled>
					<option>{ __( '— Please choose —', 'acme-contact' ) }</option>
					{ ( options || [] ).map( ( option ) => (
						<option key={ option }>{ option }</option>
					) ) }
				</select>
			);
		case 'checkbox':
			return <input type="checkbox" disabled />;
		default:
			return <input type={ 'email' === type ? 'email' : 'text' } disabled />;
	}
}

function FieldEdit( { attributes, setAttributes, clientId, type } ) {
	const { name, label, required, options } = attributes;

	// Each field needs a key that is unique within its form.
	const { siblingNames, isFirstWithName } = useSelect(
		( select ) => {
			const editor = select( 'core/block-editor' );
			const siblings = editor.getBlocks( editor.getBlockRootClientId( clientId ) );
			const first = siblings.find( ( block ) => block.attributes.name === name );
			return {
				siblingNames: siblings.filter( ( block ) => block.clientId !== clientId ).map( ( block ) => block.attributes.name ),
				isFirstWithName: ! first || first.clientId === clientId,
			};
		},
		[ clientId, name ]
	);
	useEffect( () => {
		if ( name && isFirstWithName ) {
			return;
		}
		const base = toFieldName( label ) || type;
		let candidate = base;
		let i = 2;
		while ( siblingNames.includes( candidate ) ) {
			candidate = `${ base }-${ i++ }`;
		}
		setAttributes( { name: candidate } );
	}, [ name, isFirstWithName, siblingNames.join( '|' ) ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const blockProps = useBlockProps( { className: `acme-contact-field acme-contact-field--${ type }` } );
	const labelInput = (
		<PlainText
			className="acme-contact-field__label"
			value={ label }
			onChange={ ( value ) => setAttributes( { label: value } ) }
			placeholder={ __( 'Label', 'acme-contact' ) }
			aria-label={ __( 'Field label', 'acme-contact' ) }
		/>
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Field settings', 'acme-contact' ) }>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Label', 'acme-contact' ) }
						value={ label }
						onChange={ ( value ) => setAttributes( { label: value } ) }
					/>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Field name (used in exports)', 'acme-contact' ) }
						value={ name }
						onChange={ ( value ) => setAttributes( { name: toFieldName( value ) } ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Required', 'acme-contact' ) }
						checked={ !! required }
						onChange={ ( value ) => setAttributes( { required: value } ) }
					/>
					{ 'select' === type && (
						<TextareaControl
							__nextHasNoMarginBottom
							label={ __( 'Options (one per line)', 'acme-contact' ) }
							value={ ( options || [] ).join( '\n' ) }
							onChange={ ( value ) =>
								setAttributes( {
									options: value
										.split( '\n' )
										.map( ( option ) => option.trim() )
										.filter( Boolean ),
								} )
							}
						/>
					) }
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ 'checkbox' === type ? (
					<div className="acme-contact-field__row">
						<Preview type={ type } options={ options } />
						{ labelInput }
						{ required && <span className="acme-contact-field__required"> *</span> }
					</div>
				) : (
					<>
						<div className="acme-contact-field__row">
							{ labelInput }
							{ required && <span className="acme-contact-field__required"> *</span> }
						</div>
						<Preview type={ type } options={ options } />
					</>
				) }
			</div>
		</>
	);
}

/**
 * Register a field block.
 *
 * @param {Object} metadata block.json.
 * @param {string} type     Field type.
 */
export function registerFieldBlock( metadata, type ) {
	registerBlockType( metadata, {
		edit: ( props ) => <FieldEdit { ...props } type={ type } />,
		save: () => null,
	} );
}
