/**
 * "Upcoming events" variation of the Query Loop block.
 *
 * The `acmeEvents: 'upcoming'` query key is passed on to the REST API by the Query
 * Loop's editor preview, where the plugin applies the same query as on the front end
 * (includes/class-query-loop.php).
 */
import { __ } from '@wordpress/i18n';
import { registerBlockVariation } from '@wordpress/blocks';

const VARIATION = 'acme/upcoming-events';

registerBlockVariation( 'core/query', {
	name: VARIATION,
	title: __( 'Upcoming events', 'acme-events-lite' ),
	description: __(
		'Upcoming events, soonest first. Past and cancelled events are hidden.',
		'acme-events-lite'
	),
	icon: 'calendar-alt',
	category: 'theme',
	keywords: [ __( 'events', 'acme-events-lite' ), __( 'calendar', 'acme-events-lite' ) ],
	scope: [ 'inserter' ],
	isActive: ( blockAttributes ) => blockAttributes?.namespace === VARIATION,
	allowedControls: [],
	attributes: {
		namespace: VARIATION,
		query: {
			perPage: 5,
			pages: 0,
			offset: 0,
			postType: 'acme_event',
			order: 'asc',
			orderBy: 'date',
			author: '',
			search: '',
			exclude: [],
			sticky: '',
			inherit: false,
			acmeEvents: 'upcoming',
		},
	},
	innerBlocks: [
		[
			'core/post-template',
			{},
			[
				[ 'core/post-title', { isLink: true, level: 3 } ],
				[ 'acme/event-date' ],
			],
		],
		[
			'core/query-pagination',
			{},
			[
				[ 'core/query-pagination-previous' ],
				[ 'core/query-pagination-numbers' ],
				[ 'core/query-pagination-next' ],
			],
		],
		[
			'core/query-no-results',
			{},
			[
				[
					'core/paragraph',
					{ content: __( 'No upcoming events.', 'acme-events-lite' ) },
				],
			],
		],
	],
} );
