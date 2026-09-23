/**
 * Editor preview: uses the `acme_event.date_label` REST field, which is formatted
 * on the server exactly like the front end (site formats + timezone).
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { useEntityProp } from '@wordpress/core-data';

export default function Edit( { context: { postId, postType } } ) {
	const [ details ] = useEntityProp(
		'postType',
		postType || 'acme_event',
		'acme_event',
		postId
	);
	const blockProps = useBlockProps();

	if ( ! postId || postType !== 'acme_event' ) {
		return (
			<time { ...blockProps }>{ __( 'Event date', 'acme-events-lite' ) }</time>
		);
	}

	return (
		<time { ...blockProps } dateTime={ details?.start || undefined }>
			{ details?.date_label || __( 'Event date', 'acme-events-lite' ) }
		</time>
	);
}
