/**
 * Href for the share menu's audio download.
 *
 * Variation rows reached this helper without originID, so the endpoint
 * request was item_id=undefined. Download cast that to 0 and showed
 * "File download error". A missing originID links straight to the audio
 * URL. A sermon that has originID still uses the site download endpoint.
 */

import assert from 'node:assert/strict';
import test from 'node:test';
import { audioDownloadUrl } from '../../src/app/utils/audioDownloadUrl.js';

const site = 'https://example.test/';

test('a variation with no originID downloads its audio URL directly', () => {
	const href = audioDownloadUrl(
		{
			audio: 'https://cdn.example.test/sunday.mp3',
			title: 'Hope: Sunday Morning',
		},
		site,
		'hope_sunday_morning.mp3'
	);

	assert.equal(href, 'https://cdn.example.test/sunday.mp3');
	assert.equal(href.includes('item_id'), false);
	assert.equal(href.includes('undefined'), false);
});

test('originID 0 downloads the audio URL directly', () => {
	const href = audioDownloadUrl(
		{
			audio: 'https://cdn.example.test/sunday.mp3',
			originID: 0,
		},
		site,
		'download.mp3'
	);

	assert.equal(href, 'https://cdn.example.test/sunday.mp3');
});

test('a sermon with originID uses the download endpoint and the given filename', () => {
	const href = audioDownloadUrl(
		{
			audio: 'https://cdn.example.test/sunday.mp3',
			originID: 88,
		},
		site,
		'hope_sunday_morning.mp3'
	);

	assert.equal(
		href,
		'https://example.test/?item_id=88&key=audio&name=hope_sunday_morning.mp3'
	);
});

test('SoundCloud audio links directly even when originID is present', () => {
	const href = audioDownloadUrl(
		{
			audio: 'https://soundcloud.com/church/sermon',
			originID: 88,
		},
		site,
		'sermon.mp3'
	);

	assert.equal(href, 'https://soundcloud.com/church/sermon');
});
