/**
 * Helpers shared by the pricing table and the pricing plan blocks.
 */
import { createBlock } from '@wordpress/blocks';
import { escapeHTML } from '@wordpress/escape-html';
import { __, sprintf } from '@wordpress/i18n';

export const MIN_PLANS = 1;
export const MAX_PLANS = 4;
export const DEFAULT_PLANS = 3;
export const FEATURES_CLASS = 'acme-pricing__features';

/**
 * Whether pricing tables are locked for the current user (Settings → Pricing).
 *
 * @return {boolean} Locked.
 */
export function isLockedForCurrentUser() {
	return !! window.acmePricing?.locked;
}

/**
 * Inner block template of a plan: exactly one feature list.
 *
 * The list items are deliberately not part of the template: a locked template
 * is synchronized recursively, which would cut existing lists down to the
 * template's items. The list block adds its own first item when empty.
 *
 * @return {Array} Template.
 */
export function featureListTemplate() {
	return [ [ 'core/list', { className: FEATURES_CLASS } ] ];
}

/**
 * Create a plan block.
 *
 * @param {Object}   attributes Plan attributes.
 * @param {string[]} features   Feature texts (HTML).
 * @return {Object} Block.
 */
export function createPlanBlock( attributes = {}, features = [] ) {
	return createBlock(
		'acme/pricing-plan',
		attributes,
		[
			createBlock(
				'core/list',
				{ className: FEATURES_CLASS },
				( features.length ? features : [ '' ] ).map( ( content ) =>
					createBlock( 'core/list-item', { content } )
				)
			),
		]
	);
}

/**
 * Attributes of a new, empty plan.
 *
 * @param {number} index Zero-based position.
 * @return {Object} Attributes.
 */
export function emptyPlanAttributes( index ) {
	return {
		name: sprintf(
			/* translators: %d: plan number. */
			__( 'Plan %d', 'acme-pricing' ),
			index + 1
		),
		price: '',
		period: __( '/month', 'acme-pricing' ),
		buttonText: __( 'Choose plan', 'acme-pricing' ),
	};
}

/**
 * Plan blocks from a legacy (1.x) plan object. Legacy feature texts are plain text.
 *
 * @param {Object}  plan     Legacy plan.
 * @param {boolean} featured Whether it was the highlighted plan.
 * @return {Object} Block.
 */
export function planBlockFromLegacy( plan, featured ) {
	return createPlanBlock(
		{
			name: plan.name || '',
			price: String( plan.price ?? '' ),
			period: plan.period || '',
			featured: !! featured,
			buttonText: plan.buttonText || '',
			buttonUrl: plan.buttonUrl || '',
		},
		( plan.features || [] ).map( ( feature ) => escapeHTML( String( feature ) ) )
	);
}
