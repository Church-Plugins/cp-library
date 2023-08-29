<?php

/**
 * Server-side rendering of the `cp-library/pagination` block.
 */

namespace CP_Library\Setup\Blocks;

class Pagination extends Block {
	public $name = 'pagination';
	public $is_dynamic = true;

	public function __construct() {
		parent::__construct();
	}

	/**
	 * Renders the `cp-library/pagination` block on the server.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block default content.
	 *
	 * @return string Returns the wrapper for the pagination.
	 */
	function render( $attributes, $content ) {
		if ( empty( trim( $content ) ) ) {
			return '';
		}

		$classes            = ( isset( $attributes['style']['elements']['link']['color']['text'] ) ) ? 'has-link-color' : '';
		$wrapper_attributes = get_block_wrapper_attributes(
			array(
				'aria-label' => __( 'Pagination' ),
				'class'      => $classes,
			)
		);

		return sprintf(
			'<nav %1$s>%2$s</nav>',
			$wrapper_attributes,
			$content
		);
	}
}
