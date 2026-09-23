/**
 * Wrapper class name + style of a statistic, shared by edit and save.
 *
 * @param {Object} attributes Block attributes.
 * @return {Object} Props for useBlockProps().
 */
export default function statProps( { alignment, color, fontSize, boxed } ) {
	const classes = [];
	if ( boxed ) {
		classes.push( 'is-boxed' );
	}
	if ( alignment && alignment !== 'left' ) {
		classes.push( `has-text-align-${ alignment }` );
	}
	return {
		className: classes.join( ' ' ) || undefined,
		style: {
			color: color || undefined,
			fontSize: `${ fontSize }px`,
		},
	};
}
