/**
 * Editor side of the `acme/team-member` block bindings source: shows member
 * values in connected blocks and lets users who may edit a member change its
 * text fields in place (saved with the member entity).
 */
import { registerBlockBindingsSource } from '@wordpress/blocks';
import { store as coreStore } from '@wordpress/core-data';
import { escapeHTML } from '@wordpress/escape-html';
import { __ } from '@wordpress/i18n';

const SOURCE = 'acme/team-member';
const POST_TYPE = 'acme_member';
const EDITABLE_TYPES = [ 'text', 'email', 'phone' ];

/** Field definitions passed by the server (key, label, type). */
function getFields() {
	return window.acmeTeam?.fields || [];
}

function getField( key ) {
	return getFields().find( ( field ) => field.key === key );
}

/**
 * The member a binding refers to: `memberId`, or the current post of the
 * block context when it's a team member.
 *
 * @param {Object} args    Binding args.
 * @param {Object} context Block context.
 * @return {number|null} Member post ID.
 */
function getMemberId( args, context ) {
	if (
		args?.memberId !== undefined &&
		args?.memberId !== null &&
		args?.memberId !== ''
	) {
		const raw = args.memberId;
		let id = NaN;
		if ( typeof raw === 'number' ) {
			id = raw;
		} else if ( typeof raw === 'string' && /^\d+$/.test( raw ) ) {
			id = Number( raw );
		}
		return Number.isInteger( id ) && id > 0 ? id : null;
	}
	if ( context?.postType === POST_TYPE && context?.postId ) {
		return Number( context.postId );
	}
	return null;
}

/**
 * Can the current user edit this member?
 *
 * @param {Function} select Registry select.
 * @param {number}   id     Member ID.
 * @return {boolean} Whether the user may update the member.
 */
function canEditMember( select, id ) {
	return !! select( coreStore ).canUser( 'update', {
		kind: 'postType',
		name: POST_TYPE,
		id,
	} );
}

/**
 * Member record: the edited record (with unsaved changes) for users who may
 * edit it, the public ("view") record otherwise. Null when the user can't
 * see the member at all.
 *
 * @param {Function} select Registry select.
 * @param {number}   id     Member ID.
 * @return {Object|null} Record.
 */
function getMember( select, id ) {
	if ( ! id ) {
		return null;
	}
	const store = select( coreStore );
	if ( canEditMember( select, id ) ) {
		const raw = store.getEntityRecord( 'postType', POST_TYPE, id );
		return raw?.id
			? store.getEditedEntityRecord( 'postType', POST_TYPE, id )
			: null;
	}
	const record = store.getEntityRecord( 'postType', POST_TYPE, id, {
		context: 'view',
	} );
	return record?.id ? record : null;
}

const titleOf = ( record ) => {
	const title = record?.title;
	if ( typeof title === 'string' ) {
		return title;
	}
	if ( title && typeof title.raw === 'string' ) {
		return title.raw;
	}
	return toPlainText( title?.rendered || '' );
};

function phoneHref( phone ) {
	const trimmed = String( phone || '' ).trim();
	const digits = trimmed.replace( /\D+/g, '' );
	if ( ! digits ) {
		return '';
	}
	return `tel:${ trimmed.startsWith( '+' ) ? '+' : '' }${ digits }`;
}

function photoOf( select, record ) {
	const id = Number( record?.acme_fields?.photo || 0 );
	if ( ! id ) {
		return null;
	}
	return select( coreStore ).getEntityRecord( 'postType', 'attachment', id ) || null;
}

/**
 * Raw (text) value of a field for an attribute, or '' when there is none.
 *
 * @param {Function} select    Registry select.
 * @param {Object}   record    Member record.
 * @param {string}   key       Field key.
 * @param {string}   attribute Attribute name.
 * @return {string} Value.
 */
function rawValue( select, record, key, attribute ) {
	if ( ! record || ! getField( key ) ) {
		return '';
	}
	const fields = record.acme_fields || {};
	if ( attribute === 'url' ) {
		switch ( key ) {
			case 'email':
				return fields.email ? `mailto:${ fields.email }` : '';
			case 'phone':
				return phoneHref( fields.phone );
			case 'profile_url':
				return /^https?:\/\//i.test( fields.profile_url || '' )
					? fields.profile_url
					: '';
			case 'name':
				return record.link || '';
			case 'photo':
				return photoOf( select, record )?.source_url || '';
			default:
				return '';
		}
	}
	if ( key === 'name' ) {
		return titleOf( record );
	}
	if ( key === 'photo' ) {
		const photo = photoOf( select, record );
		if ( ! photo ) {
			return '';
		}
		return photo.alt_text || titleOf( record );
	}
	return String( fields[ key ] ?? '' );
}

/** Plain text from the rich text HTML the editor produces. */
function toPlainText( html ) {
	const doc = new window.DOMParser().parseFromString(
		`<body>${ String( html ?? '' ) }</body>`,
		'text/html'
	);
	return doc.body.textContent || '';
}

registerBlockBindingsSource( {
	name: SOURCE,
	label: __( 'Team member', 'acme-team' ),
	// queryId: values inside a Query Loop are read only.
	usesContext: [ 'postId', 'postType', 'queryId' ],

	getValues( { select, context, bindings } ) {
		const values = {};
		for ( const [ attribute, { args } ] of Object.entries( bindings ) ) {
			const field = getField( args?.key );
			const record = getMember( select, getMemberId( args, context ) );
			const value = rawValue( select, record, args?.key, attribute );
			if ( attribute === 'url' ) {
				values[ attribute ] = value || null;
			} else if ( value ) {
				values[ attribute ] =
					attribute === 'alt' || attribute === 'title'
						? value
						: escapeHTML( value );
			} else {
				values[ attribute ] = field ? field.label : args?.key || '';
			}
		}
		return values;
	},

	setValues( { select, dispatch, context, bindings } ) {
		const edits = new Map();
		for ( const { args, newValue } of Object.values( bindings ) ) {
			const id = getMemberId( args, context );
			const field = getField( args?.key );
			if ( ! id || ! field ) {
				continue;
			}
			const record = getMember( select, id );
			if ( ! record ) {
				continue;
			}
			const edit = edits.get( id ) || {
				acme_fields: { ...( record.acme_fields || {} ) },
			};
			const text = toPlainText( newValue );
			if ( args.key === 'name' ) {
				edit.title = text;
			} else if ( EDITABLE_TYPES.includes( field.type ) ) {
				edit.acme_fields[ args.key ] = text;
			}
			edits.set( id, edit );
		}
		for ( const [ id, edit ] of edits ) {
			dispatch( coreStore ).editEntityRecord(
				'postType',
				POST_TYPE,
				id,
				edit
			);
		}
	},

	canUserEditValue( { select, context, args } ) {
		if ( context?.query || context?.queryId ) {
			return false;
		}
		const field = getField( args?.key );
		if ( ! field ) {
			return false;
		}
		if ( args.key !== 'name' && ! EDITABLE_TYPES.includes( field.type ) ) {
			return false;
		}
		const id = getMemberId( args, context );
		return !! id && canEditMember( select, id ) && !! getMember( select, id );
	},

	getFieldsList( { context } ) {
		if ( context?.postType !== POST_TYPE ) {
			return [];
		}
		return getFields().map( ( field ) => ( {
			label: field.label,
			type: 'string',
			args: { key: field.key },
		} ) );
	},
} );
