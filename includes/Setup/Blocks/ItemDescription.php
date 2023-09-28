<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Initialization and server-side rendering of the `cp-library/item-description` block.
 *
 * @package CP_Library
 */

namespace CP_Library\Setup\Blocks;

use CP_Library\Setup\Blocks\Block;

/**
 * ItemDescription block class.
 */
class ItemDescription extends Block {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->name       = 'item-description';
		$this->is_dynamic = true;

		parent::__construct();

		/**
		 * If themes or plugins filter the excerpt_length, we need to
		 * override the filter in the editor, otherwise
		 * the excerpt length block setting has no effect.
		 * Returns 100 because 100 is the max length in the setting.
		 */
		if ( is_admin() ||
			defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			add_filter(
				'excerpt_length',
				function() {
					return 100;
				},
				PHP_INT_MAX
			);
		}
	}

	/**
	 * Renders the cp-library/item-description block on the server
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Block default content.
	 * @param WP_Block $block      Block instance.
	 * @return string Returns the item description HTML
	 */
	public function render( $attributes, $content, $block ) {
		if ( ! isset( $block->context['postId'] ) ) {
			return '';
		}

		/*
		* The purpose of the excerpt length setting is to limit the length of both
		* automatically generated and user-created excerpts.
		* Because the excerpt_length filter only applies to auto generated excerpts,
		* wp_trim_words is used instead.
		*/
		$excerpt_length = $attributes['excerptLength'];
		$excerpt        = get_the_excerpt( $block->context['postId'] );
		if ( isset( $excerpt_length ) ) {
			$excerpt = wp_trim_words( $excerpt, $excerpt_length );
		}

		$more_text           = ! empty( $attributes['moreText'] ) ? '<a class="wp-block-cp-library-item-description__more-link" href="' . esc_url( get_the_permalink( $block->context['postId'] ) ) . '">' . wp_kses_post( $attributes['moreText'] ) . '</a>' : '';
		$filter_excerpt_more = function( $more ) use ( $more_text ) {
			return empty( $more_text ) ? $more : '';
		};

		/**
		 * Some themes might use `excerpt_more` filter to handle the
		 * `more` link displayed after a trimmed excerpt. Since the
		 * block has a `more text` attribute we have to check and
		 * override if needed the return value from this filter.
		 * So if the block's attribute is not empty override the
		 * `excerpt_more` filter and return nothing. This will
		 * result in showing only one `read more` link at a time.
		 */
		add_filter( 'excerpt_more', $filter_excerpt_more );
		$classes = array();
		if ( isset( $attributes['textAlign'] ) ) {
			$classes[] = 'has-text-align-' . $attributes['textAlign'];
		}
		if ( isset( $attributes['style']['elements']['link']['color']['text'] ) ) {
			$classes[] = 'has-link-color';
		}
		$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => implode( ' ', $classes ) ) );

		// Hide block if there is no content.
		if ( empty( trim( $excerpt ) ) && empty( trim( $more_text ) ) ) {
			return '';
		}

		$content               = '<p class="wp-block-cp-library-item-description__excerpt">' . $excerpt;
		$show_more_on_new_line = ! isset( $attributes['showMoreOnNewLine'] ) || $attributes['showMoreOnNewLine'];
		if ( $show_more_on_new_line && ! empty( $more_text ) ) {
			$content .= '</p><p class="wp-block-cp-library-item-description__more-text">' . $more_text . '</p>';
		} else {
			$content .= " $more_text</p>";
		}
		remove_filter( 'excerpt_more', $filter_excerpt_more );

		return sprintf( '<div %1$s>%2$s</div>', $wrapper_attributes, $content );
	}
}
