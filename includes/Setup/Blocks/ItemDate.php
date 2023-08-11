<?php
/**
 * Initialization and server-side rendering of the `cp-library/item-date` block.
 *
 * @package CP_Library
 */

namespace CP_Library\Setup\Blocks;
use CP_Library\Setup\Blocks\Block;

class ItemDate extends Block {
	public $name = 'item-date';
	public $is_dynamic = true;

	public function __construct() {
		parent::__construct();
	}

	/**
	 * Renders the cp-library/item-date block on the server
	 * 
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Block default content.
	 * @param WP_Block $block      Block instance.
	 * @return string Returns the item date HTML
	 */
	public function render( $attributes, $content, $block ) {
		if ( ! isset( $block->context['postId'] ) ) {
			return '';
		}
	
		$post_ID = $block->context['postId'];
	
		$classes = array();
		if ( isset( $attributes['textAlign'] ) ) {
			$classes[] = 'has-text-align-' . $attributes['textAlign'];
		}
		if ( isset( $attributes['style']['elements']['link']['color']['text'] ) ) {
			$classes[] = 'has-link-color';
		}
		$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => implode( ' ', $classes ) ) );
	
		if ( isset( $attributes['displayType'] ) && 'modified' === $attributes['displayType'] ) {
			$formatted_date   = get_the_modified_date( empty( $attributes['format'] ) ? '' : $attributes['format'], $post_ID );
			$unformatted_date = esc_attr( get_the_modified_date( 'c', $post_ID ) );
		} else {
			$formatted_date   = get_the_date( empty( $attributes['format'] ) ? '' : $attributes['format'], $post_ID );
			$unformatted_date = esc_attr( get_the_date( 'c', $post_ID ) );
		}
	
		if ( isset( $attributes['isLink'] ) && $attributes['isLink'] ) {
			$formatted_date = sprintf( '<a href="%1s">%2s</a>', get_the_permalink( $post_ID ), $formatted_date );
		}
	
		return sprintf(
			'<div %1$s><time datetime="%2$s">%3$s</time></div>',
			$wrapper_attributes,
			$unformatted_date,
			$formatted_date
		);
	}
}