/**
 * "Event details" panel in the document sidebar of events.
 */
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { useEntityProp } from '@wordpress/core-data';

import EventDetails from './event-details';

function EventDetailsPanel() {
	const postType = useSelect(
		( select ) => select( editorStore ).getCurrentPostType(),
		[]
	);
	const [ meta, setMeta ] = useEntityProp( 'postType', 'acme_event', 'meta' );

	if ( 'acme_event' !== postType || ! meta ) {
		return null;
	}

	return (
		<PluginDocumentSettingPanel
			name="acme-event-details"
			title={ __( 'Event details', 'acme-events-lite' ) }
		>
			<EventDetails
				meta={ meta }
				onChange={ ( changes ) => setMeta( { ...meta, ...changes } ) }
			/>
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'acme-event-details', { render: EventDetailsPanel } );
