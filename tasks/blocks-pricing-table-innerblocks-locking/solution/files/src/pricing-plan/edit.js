/**
 * Editor UI for one pricing plan.
 */
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect, useRegistry } from '@wordpress/data';
import {
	InspectorControls,
	RichText,
	store as blockEditorStore,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';

import { formatPrice, getDefaultCurrency } from '../pricing-table/currency';
import { featureListTemplate, isLockedForCurrentUser } from '../pricing-table/plans';

const FEATURES_TEMPLATE = featureListTemplate();

export default function Edit( { clientId, attributes, setAttributes, context } ) {
	const { name, price, period, featured, buttonText, buttonUrl } = attributes;
	const currency = context[ 'acme/pricingCurrency' ] || getDefaultCurrency();
	const locked = isLockedForCurrentUser();
	const registry = useRegistry();
	const { updateBlockAttributes } = useDispatch( blockEditorStore );

	const siblingIds = useSelect(
		( select ) => {
			const store = select( blockEditorStore );
			const parentId = store.getBlockRootClientId( clientId );
			return store.getBlockOrder( parentId ).filter( ( id ) => id !== clientId );
		},
		[ clientId ]
	);

	const setFeatured = ( value ) => {
		registry.batch( () => {
			if ( value && siblingIds.length ) {
				updateBlockAttributes( siblingIds, { featured: false } );
			}
			setAttributes( { featured: !! value } );
		} );
	};

	const blockProps = useBlockProps( {
		className: featured ? 'acme-pricing__plan is-featured' : 'acme-pricing__plan',
	} );
	const { children: featureList, ...innerProps } = useInnerBlocksProps(
		{ className: 'acme-pricing__features-wrapper' },
		{
			allowedBlocks: [ 'core/list' ],
			template: FEATURES_TEMPLATE,
			templateLock: 'all',
			renderAppender: false,
		}
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Plan settings', 'acme-pricing' ) }>
					{ ! locked && (
						<ToggleControl
							label={ __( 'Featured plan', 'acme-pricing' ) }
							help={ __( 'Only one plan per table can be featured.', 'acme-pricing' ) }
							checked={ !! featured }
							onChange={ setFeatured }
							__nextHasNoMarginBottom
						/>
					) }
					<TextControl
						label={ __( 'Button link', 'acme-pricing' ) }
						type="url"
						value={ buttonUrl }
						onChange={ ( value ) => setAttributes( { buttonUrl: value } ) }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<RichText
					tagName="h3"
					className="acme-pricing__name"
					value={ name }
					onChange={ ( value ) => setAttributes( { name: value } ) }
					placeholder={ __( 'Plan name', 'acme-pricing' ) }
					allowedFormats={ [ 'core/bold', 'core/italic' ] }
				/>
				<div className="acme-pricing__price">
					<TextControl
						label={ __( 'Price', 'acme-pricing' ) }
						hideLabelFromVision
						value={ price }
						onChange={ ( value ) => setAttributes( { price: value } ) }
						placeholder={ __( 'Amount', 'acme-pricing' ) }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					<span className="acme-pricing__amount-preview">
						{ formatPrice( price, currency ) }
					</span>
					<RichText
						tagName="span"
						className="acme-pricing__period"
						value={ period }
						onChange={ ( value ) => setAttributes( { period: value } ) }
						placeholder={ __( '/month', 'acme-pricing' ) }
						allowedFormats={ [] }
					/>
				</div>
				<div { ...innerProps }>{ featureList }</div>
				<RichText
					tagName="span"
					className="acme-pricing__button"
					value={ buttonText }
					onChange={ ( value ) => setAttributes( { buttonText: value } ) }
					placeholder={ __( 'Button text', 'acme-pricing' ) }
					allowedFormats={ [] }
				/>
			</div>
		</>
	);
}
