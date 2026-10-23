/**
 * Filename for an audio download, taken from the audio URL.
 *
 * Uses the last path segment, drops the query string and the fragment,
 * and percent-decodes it. An empty result, or a segment that cannot be
 * decoded, falls back to download.mp3. A name with no extension gets
 * .mp3 appended so the saved file still has one.
 *
 * @param {string} audio Audio URL or path.
 * @returns {string}
 */
export function getAudioDownloadName( audio ) {
	const fallback = 'download.mp3';

	if ( ! audio || typeof audio !== 'string' ) {
		return fallback;
	}

	const segment = audio.split( '/' ).pop().split( /[?#]/ )[ 0 ];

	let name;
	try {
		name = decodeURIComponent( segment );
	} catch ( e ) {
		return fallback;
	}

	if ( ! name ) {
		return fallback;
	}

	if ( ! hasExtension( name ) ) {
		name += '.mp3';
	}

	return name;
}

/**
 * @param {string} name
 * @returns {boolean}
 */
function hasExtension( name ) {
	const dot = name.lastIndexOf( '.' );
	return dot > 0 && dot < name.length - 1;
}
