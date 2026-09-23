/**
 * Saved markup of the pricing table (1.3+).
 *
 * Themes style these classes – see readme.txt ("Markup").
 */
import { RichText, useBlockProps } from '@wordpress/block-editor';

import { formatPrice } from './currency';

export default function save( { attributes } ) {
	const { columns, currency, highlighted, plans } = attributes;
	// Plans beyond the column count are kept in the attributes (so reducing the
	// column count is reversible) but not displayed.
	const visiblePlans = plans.slice( 0, columns );

	const blockProps = useBlockProps.save( {
		className: `acme-pricing acme-pricing--cols-${ columns } acme-pricing--currency-${ currency.toLowerCase() }`,
	} );

	return (
		<div { ...blockProps }>
			{ visiblePlans.map( ( plan, index ) => (
				<div
					key={ index }
					className={
						index === highlighted
							? 'acme-pricing__plan is-highlighted'
							: 'acme-pricing__plan'
					}
				>
					<RichText.Content
						tagName="h3"
						className="acme-pricing__name"
						value={ plan.name }
					/>
					<p className="acme-pricing__price">
						<span className="acme-pricing__amount">
							{ formatPrice( plan.price, currency ) }
						</span>
						{ !! plan.period && (
							<RichText.Content
								tagName="span"
								className="acme-pricing__period"
								value={ plan.period }
							/>
						) }
					</p>
					{ plan.features?.length > 0 && (
						<ul className="acme-pricing__features">
							{ plan.features.map( ( feature, i ) => (
								<li key={ i }>{ feature }</li>
							) ) }
						</ul>
					) }
					{ !! plan.buttonUrl && (
						<a className="acme-pricing__button" href={ plan.buttonUrl }>
							{ plan.buttonText }
						</a>
					) }
				</div>
			) ) }
		</div>
	);
}
