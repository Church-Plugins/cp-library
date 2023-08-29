<?php

namespace CP_Library\Setup\Blocks;

class SermonScripture extends Block {
    public $name = 'sermon-scripture';
    public $is_dynamic = true;

    public function __construct() {
      parent::__construct();
    }

     /**
     * Renders the `cp-library/sermon-topics` block on the server.
     *
     * @param array    $attributes Block attributes.
     * @param string   $content    Block default content.
     * @param \WP_Block $block      Block instance.
     * @return string Returns the HTML for the sermon topics.
     */
    public function render( $attributes, $content, $block ) {
      if( ! isset( $block->context['postId'] ) ) {
        return '';
      }

      try {
        if( $block->context['postType'] === 'cpl_item' ) {
          $item = new \CP_Library\Controllers\Item( $block->context['postId'], true );
        }
        else if( $block->context['postType'] === 'cpl_item_type' ) {
          $item = new \CP_Library\Controllers\ItemType( $block->context['postId'], true );
        }
        else {
          return '';
        }
     } 
     catch( \CP_Library\Exception $err ) {
        return '';
     }

      $scriptures = $item->get_scripture();

      if( ! $scriptures || count( $scriptures ) === 0 ) {
        return '';
      }
      
      $wrapper_attributes = get_block_wrapper_attributes();

      $output = sprintf( '<div %1$s>', $wrapper_attributes );
      $output .= '<span class="material-icons-outlined">menu_book</span>';
      
      $scripture_arr = array();

      foreach( $scriptures as $scripture ) {
        array_push( $scripture_arr, sprintf( '<a class="cpl-scripture-link" href="%1$s">%2$s', esc_url( $scripture['url'] ), esc_html( $scripture['name'] ) ) );
      }

      $output .= implode( ', </a>', $scripture_arr );

      $output .= '</a>';

      $output .= '</div>';

      return $output;
    }
}