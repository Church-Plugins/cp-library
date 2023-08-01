<?php
/**
 * Registering and server-side rendering of the `cp-library/sermon-template` block.
 */

namespace CP_Library\Setup\Blocks;
use CP_Library\Setup\Blocks\Block;
use CP_Library\Templates;
use WP_Query;
use WP_Block;

class SermonTemplate extends Block {
  public $name = 'sermon-template';
  public $is_dynamic = true;

  public function __construct() {
    parent::__construct();
    add_filter( "wp-block-cp-library-{$this->name}_block_args", [ $this, 'block_args' ] );
  }

  /**
   * Renders the `cp-library/sermon-template` block on the server.
   *
   * @param array    $attributes Block attributes.
   * @param string   $content    Block default content.
   * @param WP_Block $block      Block instance.
   *
   * @return string Returns the output of the query, structured using the layout defined by the block's inner blocks.
   */
  public function render( $attributes, $content, $block ) {
    $page_key = isset( $block->context['queryId'] ) ? 'query-' . $block->context['queryId'] . '-page' : 'query-page';
    $page     = empty( $_GET[ $page_key ] ) ? 1 : (int) $_GET[ $page_key ];

    // Use global query if needed.
    $use_global_query = ( isset( $block->context['query']['inherit'] ) && $block->context['query']['inherit'] );
    if ( $use_global_query ) {
      global $wp_query;
      $query = clone $wp_query;
    } else {
      $query_args = build_query_vars_from_query_block( $block, $page );
      $query      = new WP_Query( $query_args );
    }

    if ( ! $query->have_posts() ) {
      return '';
    }

    if ( $this->uses_featured_image( $block->inner_blocks ) ) {
      update_post_thumbnail_cache( $query );
    }

    $classnames = '';
    if ( isset( $block->context['displayLayout'] ) && isset( $block->context['query'] ) ) {
      if ( isset( $block->context['displayLayout']['type'] ) && 'flex' === $block->context['displayLayout']['type'] ) {
        $classnames = "is-flex-container columns-{$block->context['displayLayout']['columns']}";
      }
    }
    if ( isset( $attributes['style']['elements']['link']['color']['text'] ) ) {
      $classnames .= ' has-link-color';
    }

    $wrapper_attributes = get_block_wrapper_attributes( array( 'class' => trim( $classnames ) ) );

    $content = '';
    while ( $query->have_posts() ) {
      $query->the_post();

      // Get an instance of the current Post Template block.
      $block_instance = $block->parsed_block;

      // Set the block name to one that does not correspond to an existing registered block.
      // This ensures that for the inner instances of the Post Template block, we do not render any block supports.
      $block_instance['blockName'] = 'core/null';

      // Render the inner blocks of the Post Template block with `dynamic` set to `false` to prevent calling
      // `render_callback` and ensure that no wrapper markup is included.
      $block_content = (
        new WP_Block(
          $block_instance,
          array(
            'postType' => get_post_type(),
            'postId'   => get_the_ID(),
            'thumbnailAction' => $attributes['thumbnailAction']
          )
        )
      )->render( array( 'dynamic' => false ) );
      
      // Wrap the render inner blocks in a `li` element with the appropriate post classes.
      $post_classes = implode( ' ', get_post_class( 'wp-block-post' ) );
      $content     .= '<li class="' . esc_attr( $post_classes ) . '">' . $block_content . '</li>';
    }

    /*
    * Use this function to restore the context of the template tags
    * from a secondary query loop back to the main query loop.
    * Since we use two custom loops, it's safest to always restore.
    */
    wp_reset_postdata();

    return sprintf(
      '<ul %1$s>%2$s</ul>',
      $wrapper_attributes,
      $content
    );
  }

  /**
   * Determines whether a block list contains a block that uses the featured image.
   *
   * @param WP_Block_List $inner_blocks Inner block instance.
   *
   * @return bool Whether the block list contains a block that uses the featured image.
   */
  public function uses_featured_image( $inner_blocks ) {
    foreach ( $inner_blocks as $block ) {
      if ( 'core/post-featured-image' === $block->name ) {
        return true;
      }
      if (
        'core/cover' === $block->name &&
        ! empty( $block->attributes['useFeaturedImage'] )
      ) {
        return true;
      }
      if ( $block->inner_blocks && $this->uses_featured_image( $block->inner_blocks ) ) {
        return true;
      }
    }
  
    return false;
  }

  /**
   * Returns custom block args
   * 
   * @param array $args existing arguments for registering a block type
   * 
   * @return array the updated block arguments
   */
  public function block_args( $args ) {
    return array_merge( $args, [ 'skip_inner_blocks' => true ] );
  }
}


