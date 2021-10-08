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

		this.urlBase = '/wp-json';
		this.namespace = 'cp-library/v1';
	}

	/**
	 * Simple WP REST API endpoint getter
	 * @param String endpoint			The name of the endpoint
	 * @returns
	 */
	get( {endpoint = null} ) {

		return new Promise(
			(resolve, reject) => {

				let returnValue = {};

				axios.get( this.urlBase + "/" + this.namespace + "/" + endpoint )
				.then(
					(response) => { // Initial response
						returnValue = response.data;
					}
				 )
				 .catch(
					 (error) => { // Got an error
						return resolve( returnValue ); // should probably reject here
					 }
				  )
				  .then(
					  () => { // Request complete
						return resolve( returnValue );
					  }
				  );

			}
		);

	}

}
export default Controllers_WP_REST_Request;