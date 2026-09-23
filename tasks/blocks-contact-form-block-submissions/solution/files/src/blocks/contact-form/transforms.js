/**
 * Convert the classic [acme_contact] shortcode into a Contact form block.
 */
import { __ } from '@wordpress/i18n';
import { createBlock } from '@wordpress/blocks';

const transforms = {
	from: [
		{
			type: 'shortcode',
			tag: 'acme_contact',
			transform( { named = {} } ) {
				const subjects = String( named.subjects || '' )
					.split( /[|,]/ )
					.map( ( subject ) => subject.trim() )
					.filter( Boolean );
				const fields = [
					createBlock( 'acme/field-text', { name: 'name', label: __( 'Your name', 'acme-contact' ), required: true } ),
					createBlock( 'acme/field-email', { name: 'email', label: __( 'Your email', 'acme-contact' ), required: true } ),
				];
				if ( subjects.length ) {
					fields.push( createBlock( 'acme/field-select', { name: 'subject', label: __( 'Subject', 'acme-contact' ), options: subjects, required: true } ) );
				}
				fields.push( createBlock( 'acme/field-textarea', { name: 'message', label: __( 'Message', 'acme-contact' ), required: true } ) );
				return createBlock( 'acme/contact-form', { submitLabel: named.button || __( 'Send message', 'acme-contact' ) }, fields );
			},
		},
	],
};

export default transforms;
