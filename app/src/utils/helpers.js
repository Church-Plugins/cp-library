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

/**
 * Calculate the important information about an item's scrubber marker
 *
 * @param Object item
 * @param String mode
 * @param int duration
 * @returns Object
 */
export function cplMarker( item, mode, duration ) {

	let markPosition = 0;
	let snapDiff = 60;
	const videoMarks = [];
	let markerLabel = "Sermon";

	if( item && item.video && item.video.marker ) {
	  markPosition = item.video.marker;
	}

	if( markPosition > 0 ) {
	  let relativeDistance = (markPosition / duration);

	  if( relativeDistance < 0.05 || relativeDistance >= 0.95 ) {
		  // Do not show marker or label
		  markPosition = 0;
	  } else if ( relativeDistance < 0.2 || relativeDistance >= 0.8 ) {
		  // Do not show label
		  markerLabel = null;
	  }
	}

	if( 'video' === mode && markPosition > 0 ) {
	  videoMarks.push(
		  {
			  value: markPosition,
			  label: markerLabel
		  }
	  );
	}

	return {
		position		: markPosition,
		snapDistance	: snapDiff,
		marks			: videoMarks
	}
}

export function isURL(string) {
	try {
		new URL(string);
		return true;
	}
	catch(e) {
		return false;
	}
}