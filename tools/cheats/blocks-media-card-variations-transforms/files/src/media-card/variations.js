/**
 * Card variations. A card's variation is determined by its card type only, so that changing the
 * layout or the texts of a product card keeps it a product card.
 */
import { __ } from '@wordpress/i18n';

const variations = [
	{
		name: 'product',
		title: __( 'Product card', 'acme-media-card' ),
		description: __( 'A product with its price and a "Buy now" link.', 'acme-media-card' ),
		icon: 'cart',
		keywords: [ __( 'shop', 'acme-media-card' ), __( 'price', 'acme-media-card' ) ],
		attributes: { cardType: 'product', layout: 'stacked', ctaText: __( 'Buy now', 'acme-media-card' ) },
		scope: [ 'inserter', 'block', 'transform' ],
	},
	{
		name: 'profile',
		title: __( 'Profile card', 'acme-media-card' ),
		description: __( 'A person with their role.', 'acme-media-card' ),
		icon: 'admin-users',
		keywords: [ __( 'team', 'acme-media-card' ), __( 'person', 'acme-media-card' ) ],
		attributes: { cardType: 'profile', layout: 'media-left', ctaText: __( 'View profile', 'acme-media-card' ) },
		scope: [ 'inserter', 'block', 'transform' ],
	},
	{
		name: 'event',
		title: __( 'Event card', 'acme-media-card' ),
		description: __( 'An event with its date and a registration link.', 'acme-media-card' ),
		icon: 'calendar-alt',
		keywords: [ __( 'date', 'acme-media-card' ), __( 'meetup', 'acme-media-card' ) ],
		attributes: { cardType: 'event', layout: 'media-right', ctaText: __( 'Register', 'acme-media-card' ) },
		scope: [ 'inserter', 'block', 'transform' ],
	},
];

/** Placeholder of the meta line per card type. */
export const META_PLACEHOLDERS = {
	product: __( 'Price', 'acme-media-card' ),
	profile: __( 'Role', 'acme-media-card' ),
	event: __( 'Date and place', 'acme-media-card' ),
};

export default variations;
