/**
 * Build the query string a filter change navigates to.
 *
 * `post_type` is a public WordPress query var. The filter form omits
 * <input name="post_type"> on Pages and other singulars, where that param
 * 404s the permalink. Taxonomy archives and the sermon/series archives
 * still include it. When the input is absent, drop post_type from the
 * built URL even if the current URL still has it.
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
