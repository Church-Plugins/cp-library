<?php

namespace CP_Library\Setup\Blocks;
use CP_Library\Setup\Blocks\Block;

class SermonTitle extends Block {
    public $name = 'sermon-title';
    public $is_dynamic = true;

    public function __construct() {
      parent::__construct();
    }

    /**
     * Renders the cp-library/sermon-title block on the server
     * 
     * @param array    $attributes Block attributes.
     * @param string   $content    Block default content.
     * @param WP_Block $block      Block instance.
     * @return string Returns the sermon title HTML
     */
    public function render( $attributes, $content, $block ) {
      if ( ! isset( $block->context['postId'] ) ) {
        return '';
      }
    
      $post  = get_post( $block->context['postId'] );
      $title = get_the_title( $post );
    
      if ( ! $title ) {
        return '';
      }
    
      $tag_name = 'h2';
      if ( isset( $attributes['level'] ) ) {
        $tag_name = 0 === $attributes['level'] ? 'p' : 'h' . $attributes['level'];
      }
    
      if ( isset( $attributes['isLink'] ) && $attributes['isLink'] ) {
        $rel   = ! empty( $attributes['rel'] ) ? 'rel="' . esc_attr( $attributes['rel'] ) . '"' : '';
        $title = sprintf( '<a href="%1$s" target="%2$s" %3$s>%4$s</a>', get_the_permalink( $post ), esc_attr( $attributes['linkTarget'] ), $rel, $title );
      }
    
      $classes = array();
      if ( isset( $attributes['textAlign'] ) ) {
        $classes[] = 'has-text-align-' . $attributes['textAlign'];
      }
      if ( isset( $attributes['style']['elements']['link']['color']['text'] ) ) {
        $classes[] = 'has-link-color';
      }
      $wrapper_attributes = get_block_wrapper_attributes( array( 'class' => implode( ' ', $classes ) ) );
    
      return sprintf(
        '<%1$s %2$s>%3$s</%1$s>',
        $tag_name,
        $wrapper_attributes,
        $title
      );
    }
}