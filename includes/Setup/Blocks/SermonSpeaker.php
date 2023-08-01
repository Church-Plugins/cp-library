<?php

namespace CP_Library\Setup\Blocks;
use CP_Library\Setup\Blocks\Block;
use CP_Library\Models\Speaker as Speaker_Model;
use CP_Library\Models\Item as Item_Model;

class SermonSpeaker extends Block {
    public $name = 'sermon-speaker';
    public $is_dynamic = true;

    public function __construct() {
      parent::__construct();
    }

    /**
     * Renders the `cp-library/sermon-speaker` block on the server.
     *
     * @param array    $attributes Block attributes.
     * @param string   $content    Block default content.
     * @param \WP_Block $block      Block instance.
     * @return string Returns the HTML for the sermon speaker.
     */
    function render( $attributes, $content, $block ) {
      $item = Item_Model::get_instance_from_origin( $block->context['postId'] );

      $speakers = $item->get_speakers();

      if( count( $speakers ) === 0 ) {
        return '';
      }
      
      $wrapper_attributes = get_block_wrapper_attributes();

      $output = sprintf( '<div %1$s>', $wrapper_attributes );
      $output .= '<span class="material-icons-outlined">person</span>';
      
      foreach( $speakers as $index => $speaker_id ) {
        $speaker_model = Speaker_Model::get_instance( $speaker_id );
        $speaker = get_post( $speaker_model->origin_id );
        $permalink = get_permalink( $speaker );
        $comma = $index === count( $speakers ) - 1 ? '' : ','; 
        $output .= sprintf( '<a class="cpl-speaker-link" href="%1$s">%2$s%3$s</a>', esc_url( $permalink ), esc_html( $speaker->post_title ), $comma );
      }

      $output .= '</div>';

      return $output;
    }
}