/**
 * A silent audio source used to keep the persistent player's <audio> element
 * mounted while nothing is playing.
 *
 * Safari grants media playback permission per element, and only for a play()
 * call that happens inside a user gesture. The persistent player used to be
 * created lazily on the first click, so its <audio> element did not exist while
 * the click was being handled and never received permission — the first click
 * silently failed and the user had to click again. Keeping one element mounted
 * from page load, and unlocking it on the first interaction, is what makes a
 * single click work.
 *
 * ReactPlayer only renders a media element for a URL its FilePlayer recognises.
 * A data: URI is not recognised; a blob: URL is (see isBlobUrl in
 * react-player/lib/utils), so the placeholder is built as a Blob.
 */

/** @type {string|null} */
let cachedUrl = null;

/**
 * Build a minimal, valid, zero-sample WAV file.
 *
 * Written by hand rather than pulled from a base64 blob so the header is
 * auditable: 44 bytes of RIFF/fmt/data with a zero-length data chunk.
 *
 * @returns {Blob}
 */
function createSilentWav() {
	const buffer = new ArrayBuffer( 44 );
	const view   = new DataView( buffer );

	const writeAscii = ( offset, text ) => {
		for ( let i = 0; i < text.length; i++ ) {
			view.setUint8( offset + i, text.charCodeAt( i ) );
		}
	};

	writeAscii( 0, 'RIFF' );
	view.setUint32( 4, 36, true );      // Chunk size: 36 + data length (0).
	writeAscii( 8, 'WAVE' );
	writeAscii( 12, 'fmt ' );
	view.setUint32( 16, 16, true );     // Subchunk size for PCM.
	view.setUint16( 20, 1, true );      // Format: PCM.
	view.setUint16( 22, 1, true );      // Channels: mono.
	view.setUint32( 24, 44100, true );  // Sample rate.
	view.setUint32( 28, 88200, true );  // Byte rate: rate * channels * bits/8.
	view.setUint16( 32, 2, true );      // Block align.
	view.setUint16( 34, 16, true );     // Bits per sample.
	writeAscii( 36, 'data' );
	view.setUint32( 40, 0, true );      // Data length: no samples.

	return new Blob( [ buffer ], { type: 'audio/wav' } );
}

/**
 * Object URL for the silent placeholder. Created once and reused, so the
 * element's src only changes when a real sermon is selected.
 *
 * @returns {string}
 */
export function getSilentAudioUrl() {
	if ( cachedUrl ) {
		return cachedUrl;
	}

	try {
		cachedUrl = URL.createObjectURL( createSilentWav() );
	} catch ( e ) {
		// Without a placeholder the element simply isn't pre-mounted, and Safari
		// falls back to the old two-click behaviour rather than breaking.
		cachedUrl = '';
	}

	return cachedUrl;
}
