<?php

namespace CP_Library\Setup\Blocks;

class SermonActions extends Block {
    public $name = 'sermon-actions';
    public $is_dynamic = true;

    public function __construct() {
      parent::__construct();
    }
    /**
     * Renders the `cp-library/sermon-audio` block on the server.
     *
     * @param array    $attributes Block attributes.
     * @param string   $content    Block default content.
     * @param \WP_Block $block      Block instance.
     * @return string Returns the HTML for the sermon audio button.
     */
    public function render( $attributes, $content, $block ) {
      if( ! isset( $block->context['postId'] ) || $block->context['postType'] !== 'cpl_item' ) {
        return '';
      }

      try {
        $item = new \CP_Library\Controllers\Item( $block->context['postId'] );
        $item_data = $item->get_api_data();
      }
      catch( \CP_Library\Exception $err ) {
        return '';
      }

      $wrapper_attributes = get_block_wrapper_attributes( array(
        'class' => 'cpl_item_actions cpl-item--actions',
        'data-item' => esc_attr( json_encode( $item_data ) )
      ) );
      
      return sprintf(
        '<div %1$s></div>',
        $wrapper_attributes
      );
    }
}