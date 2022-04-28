import Controllers_WP_REST_Request from '../Controllers/WP_REST_Request';

export function cplVar( key, index ) {
	if ( ! window.hasOwnProperty( 'cplVars' ) ) {
		return '';
	}

	if ( ! window.cplVars.hasOwnProperty( index ) ) {
		return '';
	}

	if ( ! window.cplVars[ index ].hasOwnProperty( key ) ) {
		return '';
	}

	return window.cplVars[ index ][ key ];
}

export function cplLog( itemID, action ) {
	const restRequest = new Controllers_WP_REST_Request();
	return restRequest.post({endpoint: `items/${itemID}/log`, data: {action: action}});
}

