/**
 * Press releases: the body group is free-form, also in press releases that
 * were written before the structure was locked (their body group has no
 * `templateLock` attribute and would inherit the lock of the post).
 */
( function ( wp ) {
	'use strict';

	var BODY_CLASS = 'press-release__body';

	wp.hooks.addFilter(
		'blocks.getBlockAttributes',
		'acme-newsroom/press-release-body',
		function ( attributes, blockType ) {
			var name = blockType && blockType.name;
			if (
				name === 'core/group' &&
				attributes &&
				attributes.templateLock === undefined &&
				( ' ' + ( attributes.className || '' ) + ' ' ).indexOf( ' ' + BODY_CLASS + ' ' ) !== -1
			) {
				return Object.assign( {}, attributes, { templateLock: false } );
			}
			return attributes;
		}
	);
} )( window.wp );
