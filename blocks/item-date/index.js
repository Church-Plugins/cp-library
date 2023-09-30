/**
 * WordPress dependencies
 */
import { postDate as icon } from '@wordpress/icons';
import { registerBlockType, registerBlockVariation } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */

import metadata from './block.json';
import edit from './edit';
import './style.scss'

registerBlockType( metadata, { edit, icon } )

// register a block variation for the updated date
registerBlockVariation( 'cp-library/item-date', {
	name: 'post-date-modified',
	title: __( 'Item Modified Date', 'cp-library' ),
	description: __( "Display a items's last updated date.", 'cp-library' ),
	attributes: { displayType: 'modified' },
	scope: [ 'block', 'inserter' ],
	isActive: ( blockAttributes ) =>
		blockAttributes.displayType === 'modified',
	icon: icon,
} )
