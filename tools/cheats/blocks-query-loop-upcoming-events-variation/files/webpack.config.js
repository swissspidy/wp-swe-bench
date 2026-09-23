/**
 * Build config: the block(s) in src/ (block.json) plus the plain editor scripts.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		...defaultConfig.entry(),
		index: './src/index.js',
		'upcoming-events': './src/upcoming-events/index.js',
	},
};
