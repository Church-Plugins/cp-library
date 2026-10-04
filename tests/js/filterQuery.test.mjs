/**
 * URL building for the sermon filter form.
 *
 * The bug: a hidden <input name="post_type" value="cpl_item"> was copied into
 * the query string, so a Page containing [cp-sermons] was sent to
 * /test-sermons/?facet-speaker=17&post_type=cpl_item and WordPress 404'd.
 * Facet params without post_type filter correctly.
 *
 * Node's test runner. No extra dependencies.
 */

import assert from 'node:assert/strict';
import test from 'node:test';
import { mergeFilterFormIntoSearchParams } from '../../src/filters/filterQuery.js';

function search(params) {
	return params.toString();
}

test('a page form does not put post_type on the filter URL', () => {
	const params = mergeFilterFormIntoSearchParams(
		new URLSearchParams(),
		[
			['facet-speaker[]', '17'],
			['cpl_search', 'hope'],
		],
		false
	);

	assert.equal(params.get('facet-speaker'), '17');
	assert.equal(params.get('cpl_search'), 'hope');
	assert.equal(params.get('post_type'), null);
	assert.equal(search(params).includes('post_type'), false);
});

test('a bookmarked post_type on a page is dropped on the next filter change', () => {
	const params = mergeFilterFormIntoSearchParams(
		new URLSearchParams('post_type=cpl_item&facet-topic=4'),
		[['facet-speaker[]', '17']],
		false
	);

	assert.equal(params.get('post_type'), null);
	assert.equal(params.get('facet-topic'), '4');
	assert.equal(params.get('facet-speaker'), '17');
});

test('the sermon archive still submits post_type', () => {
	const params = mergeFilterFormIntoSearchParams(
		new URLSearchParams('paged=3'),
		[
			['post_type', 'cpl_item'],
			['facet-speaker[]', '17'],
		],
		true
	);

	assert.equal(params.get('post_type'), 'cpl_item');
	assert.equal(params.get('facet-speaker'), '17');
	assert.equal(params.get('paged'), null);
});

test('the series archive still submits cpl_item_type', () => {
	const params = mergeFilterFormIntoSearchParams(
		new URLSearchParams(),
		[
			['post_type', 'cpl_item_type'],
			['facet-season[]', '2024'],
		],
		true
	);

	assert.equal(params.get('post_type'), 'cpl_item_type');
	assert.equal(params.get('facet-season'), '2024');
});

/**
 * submitOnChange() as of 1.7.0 (commit 9733532f): every non-empty form field is
 * copied into the query string, and there is no special case for post_type.
 */
function legacyMerge (urlParams, formEntries) {
	const mergedParams = new URLSearchParams();

	for (const [key, value] of urlParams.entries()) {
		if (key !== 'paged' && key !== 'page') {
			mergedParams.append(key, value);
		}
	}

	for (const [key, value] of formEntries) {
		const cleanKey = key.endsWith('[]') ? key.slice(0, -2) : key;

		if (!value || !value.trim()) {
			continue;
		}

		if (key.endsWith('[]')) {
			mergedParams.append(cleanKey, value);
		} else {
			mergedParams.set(cleanKey, value);
		}
	}

	return mergedParams;
}

test('1.7.0 URL builder copies the hidden post_type field', () => {
	const params = legacyMerge(new URLSearchParams(), [
		['post_type', 'cpl_item'],
		['facet-speaker[]', '17'],
	]);

	assert.equal(params.get('post_type'), 'cpl_item');
	assert.equal(params.get('facet-speaker'), '17');
});

test('1.7.0 workaround: removing that input before submit leaves post_type off a clean page URL', () => {
	const afterSnippet = [
		['facet-speaker[]', '17'],
		['cpl_search', 'hope'],
	];
	const params = legacyMerge(new URLSearchParams(), afterSnippet);

	assert.equal(params.get('post_type'), null);
	assert.equal(params.get('facet-speaker'), '17');
	assert.equal(params.get('cpl_search'), 'hope');
});
