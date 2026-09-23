/**
 * Conversion of the bespoke 1.x attributes to the block supports
 * representation. Mirrors includes/class-migrator.php.
 */
import { getPresets } from './presets';

/**
 * Lowercase 6-digit hex colour, or '' when the value isn't a hex colour.
 *
 * @param {*} value Raw value.
 * @return {string} Normalized colour.
 */
export function normalizeHex( value ) {
	if ( typeof value !== 'string' ) {
		return '';
	}
	const v = value.trim().toLowerCase();
	const short = v.match( /^#([0-9a-f])([0-9a-f])([0-9a-f])$/ );
	if ( short ) {
		return `#${ short[ 1 ] }${ short[ 1 ] }${ short[ 2 ] }${ short[ 2 ] }${ short[ 3 ] }${ short[ 3 ] }`;
	}
	return /^#[0-9a-f]{6}$/.test( v ) ? v : '';
}

const px = ( n ) => `${ Number( n ) }px`;

function sizeSlug( list, n ) {
	const match = list.find( ( preset ) => String( preset.size ).trim() === px( n ) );
	return match ? match.slug : null;
}

function setStyle( attributes, path, value ) {
	const style = { ...( attributes.style || {} ) };
	let node = style;
	path.slice( 0, -1 ).forEach( ( key ) => {
		node[ key ] = { ...( node[ key ] || {} ) };
		node = node[ key ];
	} );
	node[ path[ path.length - 1 ] ] = value;
	return { ...attributes, style };
}

function applyColor( attributes, value, presetAttribute, styleKey ) {
	const hex = normalizeHex( value );
	if ( ! hex ) {
		return attributes;
	}
	const preset = getPresets().colors.find(
		( color ) => normalizeHex( color.color ) === hex
	);
	if ( preset ) {
		return { ...attributes, [ presetAttribute ]: preset.slug };
	}
	return setStyle( attributes, [ 'color', styleKey ], hex );
}

function applyFontSize( attributes, value ) {
	if ( typeof value !== 'number' || value <= 0 ) {
		return attributes;
	}
	const slug = sizeSlug( getPresets().fontSizes, value );
	if ( slug ) {
		return { ...attributes, fontSize: slug };
	}
	return setStyle( attributes, [ 'typography', 'fontSize' ], px( value ) );
}

function addClass( className, extra ) {
	const list = ( className || '' ).split( /\s+/ ).filter( Boolean );
	if ( ! list.includes( extra ) ) {
		list.push( extra );
	}
	return list.join( ' ' );
}

/**
 * Notice box: bgColor, textColor (hex), padding, fontSize (px), bordered.
 *
 * @param {Object} attributes Attributes parsed by a deprecated version
 *                            (undefined = never chosen).
 * @return {Object} Attributes for the current version.
 */
export function migrateNotice( attributes ) {
	const { bgColor, textColor, padding, fontSize, bordered, ...rest } =
		attributes;
	let next = { ...rest };
	next = applyColor( next, bgColor, 'backgroundColor', 'background' );
	next = applyColor( next, textColor, 'textColor', 'text' );
	if ( typeof padding === 'number' && padding > 0 ) {
		const slug = sizeSlug( getPresets().spacingSizes, padding );
		const value = slug ? `var:preset|spacing|${ slug }` : px( padding );
		next = setStyle( next, [ 'spacing', 'padding' ], {
			top: value,
			right: value,
			bottom: value,
			left: value,
		} );
	}
	next = applyFontSize( next, fontSize );
	if ( bordered ) {
		next.className = addClass( next.className, 'is-style-outlined' );
	}
	return next;
}

/**
 * Statistic: color (hex), fontSize (px), boxed.
 *
 * @param {Object} attributes Attributes parsed by a deprecated version.
 * @return {Object} Attributes for the current version.
 */
export function migrateStat( attributes ) {
	const { color, fontSize, boxed, ...rest } = attributes;
	let next = { ...rest };
	next = applyColor( next, color, 'textColor', 'text' );
	next = applyFontSize( next, fontSize );
	if ( boxed ) {
		next.className = addClass( next.className, 'is-style-card' );
	}
	return next;
}
