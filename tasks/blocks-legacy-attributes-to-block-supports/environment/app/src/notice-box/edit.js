/**
 * Editor UI for the notice box.
 */
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	PanelColorSettings,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	SelectControl,
	ToggleControl,
} from '@wordpress/components';

import noticeStyle from './styles';
import { getTones } from './tones';

const TEMPLATE = [ [ 'core/paragraph', {} ] ];

export default function Edit( { attributes, setAttributes } ) {
	const { tone, showIcon, bordered, bgColor, textColor, padding, fontSize } =
		attributes;
	const blockProps = useBlockProps( {
		className: [ `is-tone-${ tone }`, bordered ? 'is-bordered' : '' ]
			.filter( Boolean )
			.join( ' ' ),
		style: noticeStyle( attributes ),
	} );
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'wp-block-acme-notice-box__body' },
		{ template: TEMPLATE, templateLock: false }
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Notice', 'acme-content-blocks' ) }>
					<SelectControl
						label={ __( 'Tone', 'acme-content-blocks' ) }
						value={ tone }
						options={ getTones() }
						onChange={ ( value ) => setAttributes( { tone: value } ) }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __( 'Show icon', 'acme-content-blocks' ) }
						checked={ showIcon }
						onChange={ ( value ) =>
							setAttributes( { showIcon: value } )
						}
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __( 'Border', 'acme-content-blocks' ) }
						checked={ bordered }
						onChange={ ( value ) =>
							setAttributes( { bordered: value } )
						}
						__nextHasNoMarginBottom
					/>
				</PanelBody>
				<PanelColorSettings
					title={ __( 'Colors', 'acme-content-blocks' ) }
					colorSettings={ [
						{
							value: bgColor,
							onChange: ( value ) =>
								setAttributes( { bgColor: value } ),
							label: __( 'Background', 'acme-content-blocks' ),
						},
						{
							value: textColor,
							onChange: ( value ) =>
								setAttributes( { textColor: value } ),
							label: __( 'Text', 'acme-content-blocks' ),
						},
					] }
				/>
				<PanelBody title={ __( 'Size', 'acme-content-blocks' ) }>
					<RangeControl
						label={ __( 'Padding (px)', 'acme-content-blocks' ) }
						value={ padding }
						onChange={ ( value ) =>
							setAttributes( { padding: value } )
						}
						min={ 0 }
						max={ 64 }
						allowReset
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					<RangeControl
						label={ __( 'Font size (px)', 'acme-content-blocks' ) }
						value={ fontSize }
						onChange={ ( value ) =>
							setAttributes( { fontSize: value } )
						}
						min={ 12 }
						max={ 40 }
						allowReset
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ showIcon && (
					<span
						className="wp-block-acme-notice-box__icon"
						aria-hidden="true"
					/>
				) }
				<div { ...innerBlocksProps } />
			</div>
		</>
	);
}
