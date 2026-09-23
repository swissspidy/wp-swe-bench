/**
 * Build config: the default @wordpress/scripts config (blocks found through
 * block.json) plus the editor script of the "Glossary term" format, which is
 * not a block and therefore not picked up automatically.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const withFormatEntry = ( config ) => ( {
	...config,
	entry: async () => {
		const entries =
			typeof config.entry === 'function' ? await config.entry() : config.entry;
		return {
			...entries,
			'glossary-format/index': './src/glossary-format/index.js',
		};
	},
} );

module.exports = Array.isArray( defaultConfig )
	? [ withFormatEntry( defaultConfig[ 0 ] ), ...defaultConfig.slice( 1 ) ]
	: withFormatEntry( defaultConfig );
