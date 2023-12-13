/**
 * WordPress dependencies
 */
import { tag as icon } from '@wordpress/icons';
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import metadata from './block.json';
import edit from './edit';
import './style.scss'

registerBlockType(metadata, { edit, icon })