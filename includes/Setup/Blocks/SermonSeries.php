<?php

namespace CP_Library\Setup\Blocks;

class SermonSeries extends Block {
    public $name = 'sermon-series';
    public $is_dynamic = true;

    public function __construct() {
      parent::__construct();
    }

     /**
     * Renders the `cp-library/sermon-series` block on the server.
     *
     * @param array    $attributes Block attributes.
     * @param string   $content    Block default content.
     * @param \WP_Block $block      Block instance.
     * @return string Returns the HTML for the sermon series.
     */
    public function render( $attributes, $content, $block ) {
      $item = new \CP_Library\Controllers\Item( $block->context['postId'] );

      $item_types = $item->get_types();

      if( count( $item_types ) === 0 ) {
        return '';
      }
      
      $wrapper_attributes = get_block_wrapper_attributes();

      $output = sprintf( '<div %1$s>', $wrapper_attributes );
      $output .= '<span class="material-icons-outlined">view_list</span>';
      
      foreach( $item_types as $index => $item_type ) {
        $comma = $index === count( $item_types ) - 1 ? '' : ',';
        $output .= sprintf( '<a class="cpl-series-link" href="%1$s">%2$s%3$s</a>', esc_url( $item_type['permalink'] ), esc_html( $item_type['title'] ), $comma );
      }

      $output .= '</div>';

      return $output;
    }
}