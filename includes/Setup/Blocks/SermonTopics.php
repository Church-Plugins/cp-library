<?php

namespace CP_Library\Setup\Blocks;

class SermonTopics extends Block {
    public $name = 'sermon-topics';
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
      if( ! isset( $block->context['postId'] ) || $block->context['postType'] !== 'cpl_item' ) {
        return '';
      }
      
      $item = new \CP_Library\Controllers\Item( $block->context['postId'] );

      $topics = $item->get_topics();

      if( count( $topics ) === 0 ) {
        return '';
      }
      
      $wrapper_attributes = get_block_wrapper_attributes();

      $output = sprintf( '<div %1$s>', $wrapper_attributes );
      $output .= '<span class="material-icons-outlined">sell</span>';

      $topics_arr = array();
      
      foreach( $topics as $topic ) {
        array_push( $topics_arr, sprintf( '<a class="cpl-topic-link" href="%1$s">%2$s', esc_url( $topic['url'] ), esc_html( $topic['name'] ) ) );
      }

      $output .= implode( ', </a>', $topics_arr );

      $output .= '</a>';

      $output .= '</div>';

      return $output;
    }
}