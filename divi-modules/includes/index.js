import modules from "./modules";

jQuery(window).on('et_builder_api_ready', (_, API) => {
	console.log(API, modules)
	API.registerModules(modules);
})