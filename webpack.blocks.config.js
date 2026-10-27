const configs = require( '@wordpress/scripts/config/webpack.config' );

/**
 * The editor bundle (blocks/item-graphic) imports the front-end player, so it
 * emits the same reactPlayer*.js chunks. Disk names stay [name].js. Webpack
 * writes the file without the query and the runtime requests
 * reactPlayerFilePlayer.js?ver=<contenthash>.
 *
 * @param {object} config Webpack config.
 * @return {object}
 */
function withVersionedChunks( config ) {
	return {
		...config,
		output: {
			...config.output,
			chunkFilename: '[name].js?ver=[contenthash]',
		},
	};
}

module.exports = Array.isArray( configs ) ? configs.map( withVersionedChunks ) : withVersionedChunks( configs );
