/**
 * WordPress dependencies
 */
import { listView as icon } from '@wordpress/icons';
import { addFilter } from '@wordpress/hooks';
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import metadata from './block.json';
import edit from './edit';
import save from './save';
import queryInspectorControls from './hooks';
import './editor.scss';

registerBlockType(metadata, { edit, save, icon })

addFilter( 'editor.BlockEdit', 'cp-groups/query', queryInspectorControls )