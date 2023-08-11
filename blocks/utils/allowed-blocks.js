const ALLOWED_BLOCKS = [
	'core/spacer',
	'core/group',
	'core/columns',
	'core/column',
	'cp-library/item-date',
	'cp-library/item-title',
	'cp-library/item-graphic',
	'cp-library/pagination',
	'cp-library/sermon-template',
	'cp-library/item-description'
]

/**
 * Mapping of post types to the allowed inner blocks
 */
export const allowedBlockMapping = {
	'cpl_item_type': [
		...ALLOWED_BLOCKS
	],
	'cpl_item': [
		...ALLOWED_BLOCKS,
		'cp-library/sermon-speaker',
		'cp-library/sermon-actions',
		'cp-library/sermon-series',
		'cp-library/sermon-scripture',
		'cp-library/sermon-location',
		'cp-library/sermon-season',
		'cp-library/sermon-topics'
	]
}


/**
 * Returns an array of valid blocks for a given post type.
 *
 * @param {object[]} blocks The list of blocks to filter.
 * @param {string} postType The post type to filter by.
 * @return {object[]} An array of valid blocks.
 */
export const getValidBlocks = ( blocks, postType ) => {
	const validBlocks = []
	
	for(const block of blocks) {
		// skip adding the blocks if they are not allowed
		const allowedBlocks = allowedBlockMapping[postType]
		if( allowedBlocks && !allowedBlocks.includes( block.name ) ) continue;

		validBlocks.push({
			...block,
			innerBlocks: getValidBlocks(block.innerBlocks, postType)
		})
	}

	return validBlocks
}

/**
 * Returns an array of allowed blocks if specified, or null if there is no specification for the post type.
 * @param {string} postType the post type to get the allowed blocks for
 * @return {string[]|null} the allowed blocks
 */
export const getAllowedBlocks = ( postType ) => {
	return allowedBlockMapping[postType] || null;
}