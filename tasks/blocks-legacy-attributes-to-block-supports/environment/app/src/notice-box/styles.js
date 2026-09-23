/**
 * Inline styles of a notice box, from its bespoke attributes.
 *
 * Shared by the editor and save() so both render the same thing.
 *
 * @param {Object} attributes Block attributes.
 * @return {Object} React style object.
 */
export default function noticeStyle( { bgColor, textColor, padding, fontSize } ) {
	const style = {};
	if ( bgColor ) {
		style.backgroundColor = bgColor;
	}
	if ( textColor ) {
		style.color = textColor;
	}
	if ( padding ) {
		style.padding = `${ padding }px`;
	}
	if ( fontSize ) {
		style.fontSize = `${ fontSize }px`;
	}
	return style;
}
