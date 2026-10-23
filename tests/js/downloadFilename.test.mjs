/**
 * Audio download filenames taken from the audio URL.
 *
 * The saved name is the last path segment, with the query string and
 * fragment removed and percent-encoding decoded. Empty or malformed
 * segments fall back to download.mp3, and a name with no extension
 * gets .mp3 appended.
 *
 * Node's test runner. No extra dependencies.
 */

import assert from 'node:assert/strict';
import test from 'node:test';
import { getAudioDownloadName } from '../../src/app/utils/downloadFilename.js';

test( 'a plain mp3 URL uses the last path segment', () => {
	assert.equal(
		getAudioDownloadName( 'https://cdn.example.com/uploads/sermon.mp3' ),
		'sermon.mp3'
	);
} );

test( 'percent-encoded spaces are decoded', () => {
	assert.equal(
		getAudioDownloadName( 'https://cdn.example.com/uploads/Sunday%20Sermon.mp3' ),
		'Sunday Sermon.mp3'
	);
} );

test( 'a signed URL query string is not part of the filename', () => {
	assert.equal(
		getAudioDownloadName( 'https://cdn.example.com/audio/sermon.mp3?X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Credential=abc&X-Amz-Signature=deadbeef' ),
		'sermon.mp3'
	);
} );

test( 'a time fragment is not part of the filename', () => {
	assert.equal(
		getAudioDownloadName( 'https://cdn.example.com/audio/x.mp3#t=10' ),
		'x.mp3'
	);
} );

test( 'an extensionless URL gets .mp3', () => {
	assert.equal(
		getAudioDownloadName( 'https://cdn.host.com/audio/8f3a2b' ),
		'8f3a2b.mp3'
	);
} );

test( 'a trailing slash falls back to download.mp3', () => {
	assert.equal(
		getAudioDownloadName( 'https://cdn.example.com/audio/' ),
		'download.mp3'
	);
} );

test( 'a malformed percent-escape falls back to download.mp3', () => {
	assert.equal(
		getAudioDownloadName( 'https://cdn.example.com/audio/100%done.mp3' ),
		'download.mp3'
	);
} );

test( 'a non-ASCII name such as Sermón.mp3 is preserved', () => {
	const encoded = 'https://cdn.example.com/audio/Serm%C3%B3n.mp3';
	const literal = 'https://cdn.example.com/audio/Sermón.mp3';

	assert.doesNotThrow( () => getAudioDownloadName( encoded ) );
	assert.doesNotThrow( () => getAudioDownloadName( literal ) );
	assert.equal( getAudioDownloadName( encoded ), 'Sermón.mp3' );
	assert.equal( getAudioDownloadName( literal ), 'Sermón.mp3' );
} );
