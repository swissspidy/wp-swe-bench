/**
 * The theme's presets, as passed by the server (theme origin only).
 */
export function getPresets() {
	const presets = window.acmeContentBlocks?.presets || {};
	return {
		colors: presets.colors || [],
		spacingSizes: presets.spacingSizes || [],
		fontSizes: presets.fontSizes || [],
	};
}
