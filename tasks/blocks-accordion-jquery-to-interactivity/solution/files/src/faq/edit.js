/**
 * Editor UI for the FAQ container.
 */
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';

const ALLOWED = [ 'acme/faq-item' ];
const TEMPLATE = [ [ 'acme/faq-item' ] ];

export default function Edit( { attributes, setAttributes } ) {
	const { openFirst, allowMultiple } = attributes;
	const blockProps = useBlockProps( { className: 'acme-faq' } );
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		allowedBlocks: ALLOWED,
		template: TEMPLATE,
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'acme-faq' ) }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Open the first question by default', 'acme-faq' ) }
						checked={ !! openFirst }
						onChange={ ( value ) =>
							setAttributes( { openFirst: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Allow several open questions', 'acme-faq' ) }
						help={ __(
							'When off, opening a question closes the others.',
							'acme-faq'
						) }
						checked={ !! allowMultiple }
						onChange={ ( value ) =>
							setAttributes( { allowMultiple: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...innerBlocksProps } />
		</>
	);
}
