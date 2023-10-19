/**
 * WordPress dependencies
 */
import { postExcerpt as icon } from '@wordpress/icons';
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import metadata from './block.json';
import edit from './edit';
import './style.scss';
import './editor.scss';

registerBlockType( metadata, { edit, icon } );