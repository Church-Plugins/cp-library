<?php
namespace CP_Library\Setup\PostTypes;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Setup for custom post type: Item
 *
 * @author costmo
 * @since 1.0
 */
class Item extends PostType  {

	/**
	 * Child class constructor. Punts to the parent.
	 *
	 * @author costmo
	 */
	protected function __construct() {
		$this->post_type = CP_LIBRARY_UPREFIX . "_items";

		$this->single_label = apply_filters( "cpl_single_{$this->post_type}_label", __( 'Sermon', 'cp_library' ) );
		$this->plural_label = apply_filters( "cpl_plural_{$this->post_type}_label", __( 'Sermons', 'cp_library' ) );

		parent::__construct();
	}

	/**
	 * Setup arguments for this CPT
	 *
	 * @return array
	 * @author costmo
	 */
	public function get_args() {

		$plural = $this->plural_label;
		$single = $this->single_label;
		$icon   = apply_filters( "cpl_{$this->post_type}_icon", 'dashicons-album' );

		$args = [
			'public'        => true,
			'menu_icon'     => $icon,
			'show_in_menu'  => true,
			'show_in_rest'  => true,
			'has_archive'   => CP_LIBRARY_UPREFIX . '-' . $single . '-archive',
			'hierarchical'  => true,
			'label'         => $single,
			'rewrite'       => [
				'slug' 		=> strtolower( $plural )
			],
			'supports' 		=> [ 'title', 'editor', 'thumbnail' ],
			'labels'        => [
				'name'               => $plural,
				'singular_name'      => $single,
				'add_new'            => 'Add New',
				'add_new_item'       => 'Add New ' . $single,
				'edit'               => 'Edit',
				'edit_item'          => 'Edit ' . $single,
				'new_item'           => 'New ' . $single,
				'view'               => 'View',
				'view_item'          => 'View ' . $single,
				'search_items'       => 'Search ' . $plural,
				'not_found'          => 'No ' . $plural . ' found',
				'not_found_in_trash' => 'No ' . $plural . ' found in Trash',
				'parent'             => 'Parent ' . $single
			]
		];

		return apply_filters( "cpl_{$this->post_type}_args", $args, $this );
	}

	public function register_metaboxes() {

		$cmb = new_cmb2_box( [
			'id' => 'item_meta',
			'title' => $this->single_label . ' ' . __( 'Details', 'cp-library' ),
			'object_types' => [ $this->post_type ],
			'context' => 'normal',
			'priority' => 'high',
			'show_names' => true,
		] );

		$cmb->add_field( [
			'name' => __( 'Video URL', 'cp-library' ),
			'desc' => __( 'The URL of the video to show, leave blank to hide this field.', 'cp-library' ),
			'id'   => 'video_url',
			'type' => 'file',
		] );

		$cmb->add_field( [
			'name' => __( 'Audio URL', 'cp-library' ),
			'desc' => __( 'The URL of the audio to show, leave blank to hide this field.', 'cp-library' ),
			'id'   => 'audio_url',
			'type' => 'file',
		] );

		$cmb->add_field( [
			'name' => __( 'Facebook video permalink', 'cp-library' ),
			'id'   => 'video_id_facebook',
			'type' => 'text_medium',
		] );

		$cmb->add_field( [
			'name' => __( 'Vimeo video id', 'cp-library' ),
			'id'   => 'video_id_vimeo',
			'type' => 'text_medium',
		] );

	}

}
