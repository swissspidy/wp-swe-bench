/**
 * Editor UI: a static preview of the form + settings in the sidebar.
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls, RichText, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
	const { heading, buttonLabel, showName } = attributes;
	const blockProps = useBlockProps( {
		className: 'acme-newsletter acme-newsletter--block',
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Form', 'acme-newsletter' ) }>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Button label', 'acme-newsletter' ) }
						help={ __( 'Leave empty to use the label from Settings → Newsletter.', 'acme-newsletter' ) }
						value={ buttonLabel }
						onChange={ ( value ) => setAttributes( { buttonLabel: value } ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Ask for the name', 'acme-newsletter' ) }
						checked={ showName }
						onChange={ ( value ) => setAttributes( { showName: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<RichText
					tagName="h2"
					className="acme-newsletter__heading"
					value={ heading }
					allowedFormats={ [] }
					placeholder={ __( 'Get our newsletter', 'acme-newsletter' ) }
					onChange={ ( value ) => setAttributes( { heading: value } ) }
				/>
				<div className="acme-newsletter__form" aria-hidden="true">
					{ showName && (
						<p className="acme-newsletter__field">
							<span>{ __( 'Name', 'acme-newsletter' ) }</span>
							<input type="text" disabled />
						</p>
					) }
					<p className="acme-newsletter__field">
						<span>{ __( 'Email address', 'acme-newsletter' ) }</span>
						<input type="email" disabled />
					</p>
					<p className="acme-newsletter__actions">
						<span className="acme-newsletter__submit wp-element-button">
							{ buttonLabel || __( 'Subscribe', 'acme-newsletter' ) }
						</span>
					</p>
				</div>
			</div>
		</>
	);
}
