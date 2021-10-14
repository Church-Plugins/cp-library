import { Component } from 'react';

let axios = require( 'axios' );

/**
 * Perform REST requests against the WP host
 *
 */
class Controllers_WP_REST_Request extends Component {

	/**
	 * Class constructor
	 * @param object props 				Input properties
	 */
	constructor( props ) {

		super( props );

		// In dev mode, we need the whole URL. Otherwise, it'll hit localhost:<port>/page/wp-json/...
		// which results in 404.
		// this.urlBase = 'https://richardellis.local/wp-json';
		// this.urlBase = 'https://re.local/wp-json';
		this.urlBase = '/wp-json';
		this.namespace = 'cp-library/v1';
	}

	/**
	 * Simple WP REST API endpoint getter
	 * @param String endpoint			The name of the endpoint
	 * @param String params				Query parameters
	 * @returns
	 */
	get( {endpoint = null, params = null} ) {
		let url = this.urlBase + "/" + this.namespace + "/" + endpoint;

		if( params ) {
			url = url + "?" + params;
		}

		return axios
			.get( url )
			.then(response => response.data)
			.catch(error => {
				// Usually consumers want to handle the error themselves. If there's any global error
				// handler (e.g reporting to a monitoring tool) we can run it here before we throw it.
				throw error;
			})
	}

}
export default Controllers_WP_REST_Request;
