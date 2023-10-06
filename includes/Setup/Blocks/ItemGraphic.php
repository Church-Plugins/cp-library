<?php

namespace CP_Library\Setup\Blocks;

use CP_Library\Models\Item;
use CP_Library\Setup\Blocks\Block;

class ItemGraphic extends Block {
	public $name = 'item-graphic';
	public $is_dynamic = true;

	public function __construct() {
		parent::__construct();

		add_filter( 'wp_get_attachment_image_attributes', function( $attr, $attachment, $size ) {
			return $attr;
		}, 10, 3 );
	}

	/**
	 * Renders the `cp-library/item-graphic` block on the server.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Block default content.
	 * @param \WP_Block $block      Block instance.
	 * @return string Returns the HTML for the item graphic.
	 */
	public function render( $attributes, $content, $block ) {
		if ( ! isset( $block->context['postId'] ) || ! isset( $block->context['item'] ) ) {
			return '';
		}
		$post_ID = $block->context['postId'];

		$metadata = $this->get_metadata();

		// Check is needed for backward compatibility with third-party plugins
		// that might rely on the `in_the_loop` check; calling `the_post` sets it to true.
		if ( ! in_the_loop() && have_posts() ) {
			the_post();
		}

		$is_link        = isset( $attributes['isLink'] ) && $attributes['isLink'];
		$size_slug      = isset( $attributes['sizeSlug'] ) ? $attributes['sizeSlug'] : 'post-thumbnail';
		$attr           = $this->get_border_attributes( $attributes );
		$overlay_markup = $this->get_overlay_element_markup( $attributes );

		if ( $is_link ) {
			if ( get_the_title( $post_ID ) ) {
				$attr['alt'] = trim( strip_tags( get_the_title( $post_ID ) ) );
			} else {
				$attr['alt'] = sprintf(
					// translators: %d is the post ID.
					__( 'Untitled post %d' ),
					$post_ID
				);
			}
		}

		$extra_styles = '';

		// Aspect ratio with a height set needs to override the default width/height.
		if ( ! empty( $attributes['aspectRatio'] ) ) {
			$extra_styles .= 'width:100%;height:100%;';
		} elseif ( ! empty( $attributes['height'] ) ) {
			$extra_styles .= "height:{$attributes['height']};";
		}

		if ( ! empty( $attributes['scale'] ) ) {
			$extra_styles .= "object-fit:{$attributes['scale']};";
		}

		if ( ! empty( $extra_styles ) ) {
			$attr['style'] = empty( $attr['style'] ) ? $extra_styles : $attr['style'] . $extra_styles;
		}

		$featured_image = attachment_url_to_postid( $block->context['item']['thumb'] );
		$featured_image = wp_get_attachment_image( $featured_image, $size_slug, $attr );

		if ( $is_link ) {
			$link_target    = $attributes['linkTarget'];
			$rel            = ! empty( $attributes['rel'] ) ? 'rel="' . esc_attr( $attributes['rel'] ) . '"' : '';
			$height         = ! empty( $attributes['height'] ) ? 'style="' . esc_attr( safecss_filter_attr( 'height:' . $attributes['height'] ) ) . '"' : '';
			$featured_image = sprintf(
				'<a href="%1$s" target="%2$s" %3$s %4$s>%5$s%6$s</a>',
				get_the_permalink( $post_ID ),
				esc_attr( $link_target ),
				$rel,
				$height,
				$featured_image,
				$overlay_markup
			);
		} else {
			$featured_image = $featured_image . $overlay_markup;
		}

		$aspect_ratio = ! empty( $attributes['aspectRatio'] )
			? esc_attr( safecss_filter_attr( 'aspect-ratio:' . $attributes['aspectRatio'] ) ) . ';'
			: '';
		$width        = ! empty( $attributes['width'] )
			? esc_attr( safecss_filter_attr( 'width:' . $attributes['width'] ) ) . ';'
			: '';
		$height       = ! empty( $attributes['height'] )
			? esc_attr( safecss_filter_attr( 'height:' . $attributes['height'] ) ) . ';'
			: '';
		if ( ! $height && ! $width && ! $aspect_ratio ) {
			$wrapper_attributes = get_block_wrapper_attributes();
		} else {
			$wrapper_attributes = get_block_wrapper_attributes( array( 'style' => $aspect_ratio . $width . $height ) );
		}

		$inner_block_html = $this->get_inner_blocks( $attributes, $block, $content );

		return "<figure {$wrapper_attributes}>{$featured_image}{$inner_block_html}</figure>";
	}

	/**
	 * Generates class names and styles to apply the border support styles for
	 * the Item Graphic block
	 *
	 * @param array $attributes The block attributes.
	 * @return array The border-related classnames and styles for the block.
	 */
	public function get_border_attributes( $attributes ) {
		$border_styles = array();
		$sides         = array( 'top', 'right', 'bottom', 'left' );
	
		// Border radius.
		if ( isset( $attributes['style']['border']['radius'] ) ) {
			$border_styles['radius'] = $attributes['style']['border']['radius'];
		}
	
		// Border style.
		if ( isset( $attributes['style']['border']['style'] ) ) {
			$border_styles['style'] = $attributes['style']['border']['style'];
		}
	
		// Border width.
		if ( isset( $attributes['style']['border']['width'] ) ) {
			$border_styles['width'] = $attributes['style']['border']['width'];
		}
	
		// Border color.
		$preset_color           = array_key_exists( 'borderColor', $attributes ) ? "var:preset|color|{$attributes['borderColor']}" : null;
		$custom_color           = _wp_array_get( $attributes, array( 'style', 'border', 'color' ), null );
		$border_styles['color'] = $preset_color ? $preset_color : $custom_color;
	
		// Individual border styles e.g. top, left etc.
		foreach ( $sides as $side ) {
			$border                 = _wp_array_get( $attributes, array( 'style', 'border', $side ), null );
			$border_styles[ $side ] = array(
				'color' => isset( $border['color'] ) ? $border['color'] : null,
				'style' => isset( $border['style'] ) ? $border['style'] : null,
				'width' => isset( $border['width'] ) ? $border['width'] : null,
			);
		}
	
		$styles     = wp_style_engine_get_styles( array( 'border' => $border_styles ) );
		$attributes = array();
		if ( ! empty( $styles['classnames'] ) ) {
			$attributes['class'] = $styles['classnames'];
		}
		if ( ! empty( $styles['css'] ) ) {
			$attributes['style'] = $styles['css'];
		}
		return $attributes;
	}

	/**
	 * Generate markup for the HTML element that will be used for the overlay.
	 *
	 * @param array $attributes Block attributes.
	 *
	 * @return string HTML markup in string format.
	 */
	public function get_overlay_element_markup( $attributes ) {
		$has_dim_background  = isset( $attributes['dimRatio'] ) && $attributes['dimRatio'];
		$has_gradient        = isset( $attributes['gradient'] ) && $attributes['gradient'];
		$has_custom_gradient = isset( $attributes['customGradient'] ) && $attributes['customGradient'];
		$has_solid_overlay   = isset( $attributes['overlayColor'] ) && $attributes['overlayColor'];
		$has_custom_overlay  = isset( $attributes['customOverlayColor'] ) && $attributes['customOverlayColor'];
		$class_names         = array( 'wp-block-cp-library-item-graphic__overlay' );
		$styles              = array();

		if ( ! $has_dim_background ) {
			return '';
		}

		// Apply border classes and styles.
		$border_attributes = get_block_core_post_featured_image_border_attributes( $attributes );

		if ( ! empty( $border_attributes['class'] ) ) {
			$class_names[] = $border_attributes['class'];
		}

		if ( ! empty( $border_attributes['style'] ) ) {
			$styles[] = $border_attributes['style'];
		}

		// Apply overlay and gradient classes.
		if ( $has_dim_background ) {
			$class_names[] = 'has-background-dim';
			$class_names[] = "has-background-dim-{$attributes['dimRatio']}";
		}

		if ( $has_solid_overlay ) {
			$class_names[] = "has-{$attributes['overlayColor']}-background-color";
		}

		if ( $has_gradient || $has_custom_gradient ) {
			$class_names[] = 'has-background-gradient';
		}

		if ( $has_gradient ) {
			$class_names[] = "has-{$attributes['gradient']}-gradient-background";
		}

		// Apply background styles.
		if ( $has_custom_gradient ) {
			$styles[] = sprintf( 'background-image: %s;', $attributes['customGradient'] );
		}

		if ( $has_custom_overlay ) {
			$styles[] = sprintf( 'background-color: %s;', $attributes['customOverlayColor'] );
		}

		return sprintf(
			'<span class="%s" style="%s" aria-hidden="true"></span>',
			esc_attr( implode( ' ', $class_names ) ),
			esc_attr( safecss_filter_attr( implode( ' ', $styles ) ) )
		);
	}

	/**
	 * Turns an associative array into a list of attributes
	 * 
	 * @param array $attributes
	 * @return string
	 */
	public function attributes_to_string($attributes) {
		$attribute_parts = array_map(
				function($key, $value) {
						return sprintf('%s="%s"', $key, esc_attr($value));
				},
				array_keys($attributes),
				array_values($attributes)
		);

		return implode(' ', $attribute_parts);
	}

	/**
	 * Gets the wrapper as well as the inner blocks for the current block
	 *
	 * @param array     $attributes The block attributes.
	 * @param \WP_Block $block Block instance.
	 * @param string    $content Block inner content.
	 * @return string The inner blocks HTML.
	 */
	public function get_inner_blocks( $attributes, $block, $content ) {
		$item_post_type = cp_library()->setup->post_types->item->post_type;

		$show_play_btn = (
			true === $attributes['playIcon'] &&
			$item_post_type === $block->context['postType'] &&
			isset( $block->context['item']['video']['value'] ) &&
			filter_var( $block->context['item']['video']['value'], FILTER_VALIDATE_URL )
		);

		if ( ! $show_play_btn && empty( $block->inner_blocks ) ) {
			return '';
		}

		$inner_block_html = (
			$show_play_btn ?
			'<div class="cpl_play_overlay" data-item="' . esc_attr( wp_json_encode( $block->context['item'] ) ) . '"></div>' :
			do_blocks( $content )
		);

		return sprintf(
			'<div class="cpl-item-graphic-inner-blocks-wrapper">%1$s</div>',
			$inner_block_html
		);
	}
}
