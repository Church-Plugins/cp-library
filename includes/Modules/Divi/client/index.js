import Template from './modules/template'

jQuery(window).on('et_builder_api_ready', (_, API) => {
	API.registerModules([
		Template
	])
})