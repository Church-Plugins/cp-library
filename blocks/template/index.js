/**
 * WordPress dependencies
 */
import { layout as icon } from '@wordpress/icons';
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import metadata from './block.json';
import edit from './edit';
import './index.scss'

registerBlockType(metadata, { edit, icon })