/**
 * Build the query string a filter change navigates to.
 *
 * `post_type` is a public WordPress query var. The filter form only renders
 * <input name="post_type"> on the cpl_item and cpl_item_type archives. On a
 * Page, leaving that param in the URL makes WordPress 404 the permalink, so
 * it is removed even if the current URL still has it.
 *
 * @param {URLSearchParams} urlParams Current page query string.
 * @param {Iterable<[string, string]>} formEntries Form fields, as FormData.entries().
 * @param {boolean} formSubmitsPostType True when the form still has a post_type input.
 * @returns {URLSearchParams}
 */
export function mergeFilterFormIntoSearchParams (urlParams, formEntries, formSubmitsPostType) {
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

	if (!formSubmitsPostType) {
		mergedParams.delete('post_type');
	}

	return mergedParams;
}
