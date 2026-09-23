/**
 * Convert bespoke attributes to block supports (custom values).
 */
const px = ( n ) => `${ n }px`;

export function migrateNotice( attributes ) {
	const { bgColor, textColor, padding, fontSize, bordered, ...rest } =
		attributes;
	const style = {};
	if ( bgColor || textColor ) {
		style.color = {};
		if ( bgColor ) style.color.background = bgColor;
		if ( textColor ) style.color.text = textColor;
	}
	if ( padding ) {
		style.spacing = { padding: { top: px( padding ), right: px( padding ), bottom: px( padding ), left: px( padding ) } };
	}
	if ( fontSize ) {
		style.typography = { fontSize: px( fontSize ) };
	}
	return {
		...rest,
		style,
		className: bordered ? 'is-style-outlined' : rest.className,
	};
}

export function migrateStat( attributes ) {
	const { color, fontSize, boxed, ...rest } = attributes;
	const style = {};
	if ( color ) style.color = { text: color };
	if ( fontSize ) style.typography = { fontSize: px( fontSize ) };
	return { ...rest, style, className: boxed ? 'is-style-card' : rest.className };
}
