const config = require('./includes/ChurchPlugins/webpack-default.config');

module.exports = {
	...config,
	output: {
		...config.output,
		publicPath: 'auto',
		// Entry files stay [name].js so PHP can keep enqueueing build/src/{name}.js.
		// Lazy chunks keep the v1.8.0 names on disk (reactPlayerFilePlayer.js).
		// Webpack writes the file without the query and the runtime requests
		// reactPlayerFilePlayer.js?ver=<contenthash>, so a cached copy of the
		// previous chunk is not reused after a release. A page still holding
		// v1.8.0's app.js requests the unversioned name and receives this file.
		chunkFilename: '[name].js?ver=[contenthash]',
	}
};
