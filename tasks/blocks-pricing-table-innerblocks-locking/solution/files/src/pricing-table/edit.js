/**
 * Editor UI for the pricing table: a container of pricing plan blocks.
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';
import { useDispatch, useSelect, useRegistry } from '@wordpress/data';
import {
	InspectorControls,
	store as blockEditorStore,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import { PanelBody, RangeControl, SelectControl } from '@wordpress/components';

import { getCurrencies, getDefaultCurrency } from './currency';
import {
	DEFAULT_PLANS,
	MAX_PLANS,
	MIN_PLANS,
	createPlanBlock,
	emptyPlanAttributes,
	featureListTemplate,
	isLockedForCurrentUser,
} from './plans';

const NEW_TABLE_TEMPLATE = Array.from( { length: DEFAULT_PLANS }, ( _, i ) => [
	'acme/pricing-plan',
	emptyPlanAttributes( i ),
	featureListTemplate(),
] );

export default function Edit( { clientId, attributes, setAttributes } ) {
	const { currency } = attributes;
	const locked = isLockedForCurrentUser();
	const registry = useRegistry();
	const { insertBlocks, removeBlocks, updateBlockAttributes, __unstableMarkNextChangeAsNotPersistent } =
		useDispatch( blockEditorStore );

	const plans = useSelect(
		( select ) => select( blockEditorStore ).getBlocks( clientId ),
		[ clientId ]
	);
	const planCount = plans.length;

	// New tables get the site's default currency.
	const isNew = useRef( planCount === 0 );
	useEffect( () => {
		if ( isNew.current ) {
			__unstableMarkNextChangeAsNotPersistent();
			setAttributes( { currency: getDefaultCurrency() } );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	// At most one featured plan per table: if several are featured (duplicated or
	// pasted plans, plans moved in from another table), the first one wins.
	const featuredIds = plans
		.filter( ( plan ) => plan.attributes.featured )
		.map( ( plan ) => plan.clientId );
	const featuredKey = featuredIds.join( ',' );
	useEffect( () => {
		if ( featuredIds.length > 1 ) {
			__unstableMarkNextChangeAsNotPersistent();
			updateBlockAttributes( featuredIds.slice( 1 ), { featured: false } );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ featuredKey ] );

	const setPlanCount = ( value ) => {
		const target = Math.max( MIN_PLANS, Math.min( MAX_PLANS, parseInt( value, 10 ) || MIN_PLANS ) );
		if ( target === planCount ) {
			return;
		}
		registry.batch( () => {
			if ( target > planCount ) {
				const added = [];
				for ( let i = planCount; i < target; i++ ) {
					added.push( createPlanBlock( emptyPlanAttributes( i ) ) );
				}
				insertBlocks( added, planCount, clientId, false );
			} else {
				removeBlocks(
					plans.slice( target ).map( ( plan ) => plan.clientId ),
					false
				);
			}
		} );
	};

	const blockProps = useBlockProps( {
		className: `acme-pricing acme-pricing--cols-${ planCount } acme-pricing--currency-${ String( currency ).toLowerCase() }`,
	} );
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		allowedBlocks: [ 'acme/pricing-plan' ],
		orientation: 'horizontal',
		// Only fill empty (new) tables; never re-shape existing ones.
		template: planCount === 0 ? NEW_TABLE_TEMPLATE : undefined,
		templateLock: locked ? 'all' : false,
		renderAppender: false,
	} );

	const currencyOptions = Object.entries( getCurrencies() ).map(
		( [ code, rules ] ) => ( { value: code, label: `${ code } – ${ rules.label }` } )
	);

	return (
		<>
			{ ! locked && (
				<InspectorControls>
					<PanelBody title={ __( 'Table settings', 'acme-pricing' ) }>
						<RangeControl
							label={ __( 'Number of plans', 'acme-pricing' ) }
							value={ planCount }
							onChange={ setPlanCount }
							min={ MIN_PLANS }
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
					</PanelBody>
				</InspectorControls>
			) }
			<div { ...innerBlocksProps } />
		</>
	);
}
