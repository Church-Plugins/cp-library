/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';
import { Volume2 } from 'react-feather'
/**
 * Internal dependencies
 */
import metadata from './block.json';
import edit from './edit';
import './style.scss'

registerBlockType(metadata, { 
  edit, 
  icon: (
    <span className='material-icons'>volume_up</span>
  )
})