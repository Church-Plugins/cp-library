<?php
namespace CP_Library\Views\Admin;
use CP_Library\Util\Convenience as Convenience;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin View class for Source objects
 *
 * @author costmo
 * @since 1.0
 */
class Source {

	/**
	 * Class constructor
	 *
	 * @author costmo
	 */
	public function __construct() {

	}

	/**
	 * Admin View functionality for selecting the parent of a Source
	 *
	 * @return string
	 * @author costmo
	 */
	private function parent_source_metabox() {

		global $post;
		$title = __( 'Select a parent', 'cp_library' );
		$nonce_field = CP_LIBRARY_UPREFIX . "_source_parent_nonce";
		$nonce_value = wp_create_nonce( $nonce_field );

		$wp_query = [
			'post_type'      	=> CP_LIBRARY_UPREFIX . "_sources",
			'posts_per_page' 	=> -1,
			'orderby'			=> 'title',
			'order'				=> 'ASC'
		];
		$posts = get_posts( $wp_query );
		$selected = "";
		if( !Convenience::is_post( $post ) || empty( $post->post_parent ) ) {
			$selected = "selected";
		}

		$select_list = "
			<select name='" . CP_LIBRARY_UPREFIX . "_source_parent_id'>
				<option value='0' {$selected}>&nbsp;None&nbsp;</option>
		";
		if( !empty( $posts ) ) {
			foreach( $posts as $loop_post ) {

				$selected = "";
				if( Convenience::is_post( $post ) &&
					$loop_post->ID == $post->post_parent ) {
						$selected = "selected";
				}

				// // Make sublevels a little more apparent in the drop-down
				// // We will have to do queries by level, else this will look like mis-parented items in the UI
				// $show_title = $loop_post->post_title;
				// if( !empty( $loop_post->post_parent ) ) {
				// 	$show_title = "&nbsp;&#8212;&nbsp;" . $loop_post->post_title;
				// }

				if( $loop_post->ID !== $post->ID ) {
					$select_list .= "
						<option value='{$loop_post->ID}' {$selected}>&nbsp;{$loop_post->post_title}&nbsp;</option>
					";
				}
			}
		}
		$select_list .= "
			</select>
		";

		$content = <<<EOT
		<div class="source-parent-container">
			<div class="source-parent-title">
				{$title}
			</div>
			<div class="source-parent-form">
				{$select_list}
				<input type="hidden" name="{$nonce_field}" value="{$nonce_value}">
			</div>
		</div>
EOT;

		return $content;
	}

	/**
	 * Echo wrapper for parent_source_metabox()
	 *
	 * @return void
	 * @author costmo
	 */
	public function render_parent_source_metabox() {

		echo $this->parent_source_metabox();
	}


}