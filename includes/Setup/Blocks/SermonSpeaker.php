<?php

namespace CP_Library\Setup\Blocks;

use CP_Library\Admin\Settings;
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
		if( ! isset( $block->context['postId'] ) || $block->context['postType'] !== 'cpl_item' ) {
			return '';
		}

		$item = new \CP_Library\Controllers\Item( $block->context['postId'], true );

		$speakers = $item->get_speakers();

		if( count( $speakers ) === 0 ) {
			return '';
		}
		
		$wrapper_attributes = get_block_wrapper_attributes();

		$output = sprintf( '<div %1$s>', $wrapper_attributes );
		$output .= '<span class="material-icons-outlined">person</span>';

		$speakers_arr = array();

		foreach( $speakers as $speaker ) {
			$title = $speaker['title'];

			if( Settings::get_speaker( 'enable_permalinks', false ) ) {
				$url = get_permalink( $speaker['origin_id'] );
				$title = sprintf( '<a href="%1$s" class="cpl-speaker-link">%2$s</a>', $url, $title );
			}

			$speakers_arr[] = sprintf( '<span class="cpl-speaker-link">%1$s', $title );
		}

		$output .= implode( ', </span>', $speakers_arr );

		$output .= '</span>';

		$output .= '</div>';

		return $output;
	}
}
