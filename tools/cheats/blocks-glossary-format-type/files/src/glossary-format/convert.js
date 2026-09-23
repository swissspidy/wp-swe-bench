/**
 * Convert legacy [glossary] shortcodes when classic content is converted to
 * blocks (or pasted): the shortcode becomes marked text.
 *
 * Inline shortcodes are not handled by block transforms, they end up as text
 * inside paragraphs. We add a raw transform to the paragraph block that runs
 * before the default one and converts them first.
 */
import { createBlock, getBlockAttributes } from '@wordpress/blocks';
import { replace } from '@wordpress/shortcode';
import { escapeAttribute } from '@wordpress/escape-html';

import { resolveShortcodeTerm } from './terms';
import { FORMAT_CLASS } from './format';

const hasShortcode = ( html ) => /\[glossary[\s\]]/.test( html );

const textOf = ( html ) => {
	const doc = document.implementation.createHTMLDocument( '' );
	doc.body.innerHTML = html;
	return doc.body.textContent || '';
};

/**
 * Replace [glossary] shortcodes in HTML by marked text (or plain text for unknown terms).
 *
 * @param {string} html HTML.
 * @return {string} HTML.
 */
export function convertShortcodes( html ) {
	return replace( 'glossary', html, ( shortcode ) => {
		const inner = shortcode.content || '';
		const term = resolveShortcodeTerm( shortcode.attrs.named || {}, textOf( inner ) );
		if ( ! term ) {
			return inner;
		}
		return `<span class="${ FORMAT_CLASS }" data-term-id="${ escapeAttribute( String( term.id ) ) }">${ inner }</span>`;
	} );
}

/**
 * Add the converting raw transform to blocks with a "p" raw transform.
 *
 * @param {Object} settings Block settings.
 * @param {string} name     Block name.
 * @return {Object} Settings.
 */
export function addShortcodeConversion( settings, name ) {
	if ( name !== 'core/paragraph' || ! settings.transforms?.from ) {
		return settings;
	}
	return {
		...settings,
		transforms: {
			...settings.transforms,
			from: [
				{
					type: 'raw',
					priority: 1,
					isMatch: ( node ) =>
						node.nodeName === 'P' && hasShortcode( node.innerHTML ),
					transform( node ) {
						const clone = node.cloneNode( true );
						clone.innerHTML = convertShortcodes( node.innerHTML );
						return createBlock(
							name,
							getBlockAttributes( name, clone.outerHTML )
						);
					},
				},
				...settings.transforms.from,
			],
		},
	};
}
