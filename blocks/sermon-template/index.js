/**
 * WordPress dependencies
 */
import { quote as icon } from '@wordpress/icons';
import { registerBlockType } from '@wordpress/blocks';
import { addFilter } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import metadata from './block.json';
import edit from './edit';
import save from './save';
import './editor.scss'
import './style.scss'
import { createHigherOrderComponent } from '@wordpress/compose';

registerBlockType(metadata, { edit, save, icon })

/**
 * Makes sure the allowedBlocks are inheritied from the parent
 */

const withAllowedBlocks = createHigherOrderComponent((SermonTemplateBlock) => {
	return (props) => {
		const { allowedBlocks } = props
		return <SermonTemplateBlock {...props} allowedBlocks={allowedBlocks} />
	}
})

addFilter(
	'editor.BlockListBlock',
	'cp-library/sermon-template',
	withAllowedBlocks
)