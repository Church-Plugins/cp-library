const config = require('./includes/ChurchPlugins/webpack-default.config');

module.exports = {
	...config,
	output: {
		...config.output,
		publicPath: 'auto',
		// Entry files stay [name].js so PHP can keep enqueueing build/src/{name}.js.
		// Lazy chunks are requested by the webpack runtime with no ?ver=, so the
		// filename has to change when the chunk does. Otherwise a cached
		// reactPlayer*.js can run against a newer app.js and throw.
		chunkFilename: '[name].[contenthash].js',
	}
};
