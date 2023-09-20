import Template from "./Template"

const modules = [
	Template
]

jQuery(window).on('et_builder_api_ready', (_, API) => {
	API.registerModules(modules)
})