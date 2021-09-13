<?php
namespace CP_Library\Views\Admin;

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
			'post_type'      => CP_LIBRARY_UPREFIX . "_sources",
			'posts_per_page' => -1,
		];
		$posts = get_posts( $wp_query );
		$selected = "";
		if( empty( $post ) || !is_object( $post ) || empty( $post->ID ) || empty( $post->post_parent ) ) {
			$selected = "selected";
		}

		$select_list = "
			<select name='" . CP_LIBRARY_UPREFIX . "_source_parent_id'>
				<option value='0' {$selected}>&nbsp;None&nbsp;</option>
		";
		if( !empty( $posts ) ) {
			foreach( $posts as $loop_post ) {

				$selected = "";
				if( !empty( $post ) && is_object( $post ) && !empty( $post->ID ) &&
					!empty( $loop_post->post_parent ) && $loop_post->post_parent == $post->ID ) {
						$selected = "selected";
				}
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