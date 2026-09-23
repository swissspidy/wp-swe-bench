/**
 * Editor UI for the pricing table block.
 *
 * All plans live in the `plans` attribute of this one block. The column count
 * decides how many plans are displayed; plans beyond it are kept so that
 * reducing and increasing the count again doesn't lose anything.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';
import {
	InspectorControls,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	SelectControl,
	TextareaControl,
	TextControl,
} from '@wordpress/components';

import { formatPrice, getCurrencies, getDefaultCurrency } from './currency';

export const MAX_PLANS = 4;

/**
 * An empty plan.
 *
 * @param {number} index Zero-based position (used for the default name).
 * @return {Object} Plan.
 */
export function emptyPlan( index ) {
	return {
		name: sprintf(
			/* translators: %d: plan number. */
			__( 'Plan %d', 'acme-pricing' ),
			index + 1
		),
		price: '',
		period: __( '/month', 'acme-pricing' ),
		features: [],
		buttonText: __( 'Choose plan', 'acme-pricing' ),
		buttonUrl: '',
	};
}

function PlanEditor( { plan, index, currency, isHighlighted, onChange } ) {
	const update = ( changes ) => onChange( { ...plan, ...changes } );

	return (
		<div
			className={
				isHighlighted
					? 'acme-pricing__plan is-highlighted'
					: 'acme-pricing__plan'
			}
		>
			<RichText
				tagName="h3"
				className="acme-pricing__name"
				value={ plan.name }
				onChange={ ( name ) => update( { name } ) }
				placeholder={ __( 'Plan name', 'acme-pricing' ) }
				allowedFormats={ [ 'core/bold', 'core/italic' ] }
			/>
			<div className="acme-pricing__price">
				<TextControl
					label={ sprintf(
						/* translators: %d: plan number. */
						__( 'Price of plan %d', 'acme-pricing' ),
						index + 1
					) }
					hideLabelFromVision
					value={ plan.price }
					onChange={ ( price ) => update( { price } ) }
					placeholder={ __( 'Amount', 'acme-pricing' ) }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				<span className="acme-pricing__amount-preview">
					{ formatPrice( plan.price, currency ) }
				</span>
				<RichText
					tagName="span"
					className="acme-pricing__period"
					value={ plan.period }
					onChange={ ( period ) => update( { period } ) }
					placeholder={ __( '/month', 'acme-pricing' ) }
					allowedFormats={ [] }
				/>
			</div>
			<TextareaControl
				label={ __( 'Features (one per line)', 'acme-pricing' ) }
				value={ ( plan.features || [] ).join( '\n' ) }
				onChange={ ( value ) =>
					update( {
						features: value
							.split( '\n' )
							.map( ( line ) => line.trim() )
							.filter( Boolean ),
					} )
				}
				__nextHasNoMarginBottom
			/>
			<RichText
				tagName="span"
				className="acme-pricing__button"
				value={ plan.buttonText }
				onChange={ ( buttonText ) => update( { buttonText } ) }
				placeholder={ __( 'Button text', 'acme-pricing' ) }
				allowedFormats={ [] }
			/>
			<TextControl
				label={ __( 'Button link', 'acme-pricing' ) }
				type="url"
				value={ plan.buttonUrl }
				onChange={ ( buttonUrl ) => update( { buttonUrl } ) }
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
		</div>
	);
}

export default function Edit( { attributes, setAttributes } ) {
	const { columns, currency, highlighted, plans } = attributes;

	// New tables: fill in the plans and use the site's default currency.
	useEffect( () => {
		if ( plans.length === 0 ) {
			setAttributes( {
				currency: getDefaultCurrency(),
				plans: Array.from( { length: columns }, ( _, i ) => emptyPlan( i ) ),
			} );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const setColumns = ( value ) => {
		const next = [ ...plans ];
		// Keep plans beyond the new count (reversible), add missing ones.
		while ( next.length < value ) {
			next.push( emptyPlan( next.length ) );
		}
		setAttributes( {
			columns: value,
			plans: next,
			highlighted: highlighted >= value ? -1 : highlighted,
		} );
	};

	const updatePlan = ( index, plan ) => {
		const next = [ ...plans ];
		next[ index ] = plan;
		setAttributes( { plans: next } );
	};

	const currencyOptions = Object.entries( getCurrencies() ).map(
		( [ code, rules ] ) => ( { value: code, label: `${ code } – ${ rules.label }` } )
	);

	const blockProps = useBlockProps( {
		className: `acme-pricing acme-pricing--cols-${ columns }`,
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Table settings', 'acme-pricing' ) }>
					<RangeControl
						label={ __( 'Columns', 'acme-pricing' ) }
						value={ columns }
						onChange={ setColumns }
						min={ 1 }
						max={ MAX_PLANS }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Currency', 'acme-pricing' ) }
						value={ currency }
						options={ currencyOptions }
						onChange={ ( value ) => setAttributes( { currency: value } ) }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Highlighted plan', 'acme-pricing' ) }
						value={ String( highlighted ) }
						options={ [
							{ value: '-1', label: __( 'None', 'acme-pricing' ) },
							...plans.slice( 0, columns ).map( ( plan, i ) => ( {
								value: String( i ),
								label: sprintf(
									/* translators: %d: plan number. */
									__( 'Plan %d', 'acme-pricing' ),
									i + 1
								),
							} ) ),
						] }
						onChange={ ( value ) =>
							setAttributes( { highlighted: parseInt( value, 10 ) } )
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ plans.slice( 0, columns ).map( ( plan, index ) => (
					<PlanEditor
						key={ index }
						plan={ plan }
						index={ index }
						currency={ currency }
						isHighlighted={ index === highlighted }
						onChange={ ( value ) => updatePlan( index, value ) }
					/>
				) ) }
			</div>
		</>
	);
}
