/**
 * Editor UI of the Event details block: previews the event meta of the post being edited.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { useEntityProp } from '@wordpress/core-data';

export default function Edit( { attributes, setAttributes, context } ) {
	const { showEnd } = attributes;
	const { postId, postType } = context;
	const [ meta ] = useEntityProp( 'postType', postType, 'meta', postId );
	const blockProps = useBlockProps( { className: 'acme-event-details' } );

	if ( 'acme_event' !== postType ) {
		return (
			<div { ...blockProps }>
				{ __( 'Event details are only shown on events.', 'acme-events' ) }
			</div>
		);
	}

	const start = meta?._acme_event_start || '';
	const end = meta?._acme_event_end || '';
	const venue = meta?._acme_event_venue || '';

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'acme-events' ) }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show end time', 'acme-events' ) }
						checked={ showEnd }
						onChange={ ( value ) => setAttributes( { showEnd: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<p className="acme-event-details__when">
					{ start || __( 'No start date yet', 'acme-events' ) }
					{ showEnd && end ? ` – ${ end }` : '' }
				</p>
				{ venue && <p className="acme-event-details__where">{ venue }</p> }
			</div>
		</>
	);
}
