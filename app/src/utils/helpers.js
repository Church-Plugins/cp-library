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
