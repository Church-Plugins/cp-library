/**
 * WordPress dependencies
 */
import { queryPagination as icon } from '@wordpress/icons';
import { registerBlockType } from '@wordpress/blocks';
/**
 * Internal dependencies
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';

registerBlockType( metadata, { edit, save, icon } )