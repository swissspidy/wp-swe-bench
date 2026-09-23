/**
 * Contact form block: editor.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, useInnerBlocksProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, TextareaControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { FIELD_BLOCKS, DEFAULT_FIELDS, newFormId } from './constants';

export default function Edit( { attributes, setAttributes, clientId } ) {
	const { formId, submitLabel, successMessage } = attributes;

	// Every form in a post needs its own ID (submissions are matched to the form by it).
	const isDuplicate = useSelect(
		( select ) => {
			const editor = select( 'core/block-editor' );
			const ids = editor.getBlocksByName
				? editor.getBlocksByName( 'acme/contact-form' )
				: editor.__experimentalGetGlobalBlocksByName( 'acme/contact-form' );
			for ( const id of ids || [] ) {
				if ( id === clientId ) {
					return false;
				}
				if ( formId && editor.getBlockAttributes( id )?.formId === formId ) {
					return true;
				}
			}
			return false;
		},
		[ clientId, formId ]
	);
	useEffect( () => {
		if ( ! formId || isDuplicate ) {
			setAttributes( { formId: newFormId() } );
		}
	}, [ formId, isDuplicate ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const blockProps = useBlockProps( { className: 'acme-contact-form' } );
	const { children, ...innerBlocksProps } = useInnerBlocksProps( blockProps, {
		allowedBlocks: FIELD_BLOCKS,
		template: DEFAULT_FIELDS,
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Form settings', 'acme-contact' ) }>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Button label', 'acme-contact' ) }
						value={ submitLabel }
						onChange={ ( value ) => setAttributes( { submitLabel: value } ) }
					/>
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __( 'Success message', 'acme-contact' ) }
						help={ __( 'Leave empty to use the message from Settings → Contact form.', 'acme-contact' ) }
						value={ successMessage }
						onChange={ ( value ) => setAttributes( { successMessage: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...innerBlocksProps }>
				{ children }
				<div className="acme-contact-form__actions">
					<span className="wp-element-button">{ submitLabel || __( 'Send', 'acme-contact' ) }</span>
				</div>
			</div>
		</>
	);
}
