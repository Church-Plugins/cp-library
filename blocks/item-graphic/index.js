/**
 * WordPress dependencies
 */
import { postFeaturedImage as icon } from '@wordpress/icons';
import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import metadata from './block.json';
import edit from './edit';
import './style.scss';
import './editor.scss';

registerBlockType( metadata, { 
  edit, 
  save: () => {
    const blockProps = useBlockProps.save();
    return (
      <div {...blockProps}>
         <InnerBlocks.Content />
      </div>
    )
  },
  icon
} )