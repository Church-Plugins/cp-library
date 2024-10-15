const config = require('./includes/ChurchPlugins/webpack-default.config');

module.exports = {
	...config,
	output: {
		...config.output,
		publicPath: 'auto'
	}
};