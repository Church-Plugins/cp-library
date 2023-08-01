/**
 * WordPress dependencies
 */
import { quote as icon } from '@wordpress/icons';
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import metadata from './block.json';
import edit from './edit';
import save from './save';
import './editor.scss'
import './style.scss'

registerBlockType(metadata, { edit, save, icon })
