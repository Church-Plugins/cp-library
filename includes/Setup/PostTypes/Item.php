<?php
namespace CP_Library\Setup\PostTypes;

// Exit if accessed directly
use CP_Library\Admin\Settings;
use CP_Library\Templates;

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
		$this->post_type = CP_LIBRARY_UPREFIX . "_item";

		$this->single_label = apply_filters( "cpl_single_{$this->post_type}_label", Settings::get_item( 'singular_label', 'Message' ) );
		$this->plural_label = apply_filters( "cpl_plural_{$this->post_type}_label", Settings::get_item( 'plural_label', 'Messages' ) );

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
		$icon   = apply_filters( "cpl_{$this->post_type}_icon", 'dashicons-format-video' );
		$slug   = apply_filters( "cpl_{$this->post_type}_slug", strtolower( sanitize_title( $plural ) ) );

		$args = [
			'public'        => true,
			'menu_icon'     => $icon,
			'show_in_menu'  => true,
			'show_in_rest'  => true,
			'has_archive'   => $slug,
			'hierarchical'  => true,
			'label'         => $single,
			'rewrite'       => [
				'slug' 		=> $slug
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

		return apply_filters( "{$this->post_type}_args", $args, $this );
	}

	public function register_metaboxes() {
		$this->meta_details();
//		$this->meta_topics();
//		$this->meta_scripture();
//		$this->meta_season();
	}

	protected function meta_details() {
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
			'name' => __( 'Youtube video permalink', 'cp-library' ),
			'id'   => 'video_id_youtube',
			'type' => 'text_medium',
		] );

		$cmb->add_field( [
			'name' => __( 'Vimeo video id', 'cp-library' ),
			'id'   => 'video_id_vimeo',
			'type' => 'text_medium',
		] );
	}

	protected function meta_topics() {
		$topics_file = Templates::get_template_hierarchy( '__data/topics.json' );

		if ( ! $topics_file ) {
			return;
		}

		$topics = json_decode( file_get_contents( $topics_file ) );
		$topic_terms = wp_list_pluck( $topics, 'term' );
		$topic_terms = array_combine( array_map( 'esc_attr', $topic_terms ), $topic_terms );

		$cmb = new_cmb2_box( array(
			'id'           => 'cpl_topic_data',
			'object_types' => [ $this->post_type ],
			'title'        => __( "Topics", 'cp-library' ),
			'context'      => 'side',
			'show_names'   => false,
			'priority'     => 'default',
			'closed'       => false,
		) );

		$cmb->add_field( [
			'name'              => __( 'Assign Topic', 'cp-library' ),
			'id'                => 'cpl_topics',
			'type'              => 'multicheck',
			'select_all_button' => false,
			'options'           => $topic_terms
		] );
	}

	protected function meta_scripture() {
		$scripture_file = Templates::get_template_hierarchy( '__data/scripture.json' );

		if ( ! $scripture_file ) {
			return;
		}

		$scripture = json_decode( file_get_contents( $scripture_file ) );
		$terms = [];

		foreach( $scripture as $section ) {
			foreach( $section as $book ) {
				$terms[ esc_attr( $book ) ] = $book;
			}
		}

		$cmb = new_cmb2_box( array(
			'id'           => 'cpl_scripture_data',
			'object_types' => [ $this->post_type, cp_library()->setup->post_types->item_type->post_type ],
			'title'        => __( "Scripture", 'cp-library' ),
			'context'      => 'side',
			'show_names'   => false,
			'priority'     => 'default',
			'closed'       => false,
		) );

		$cmb->add_field( [
			'name'              => __( 'Assign Scripture', 'cp-library' ),
			'id'                => 'cpl_scripture',
			'type'              => 'multicheck',
			'select_all_button' => false,
			'options'           => $terms
		] );
	}

	protected function meta_season() {
		$season_file = Templates::get_template_hierarchy( '__data/season.json' );

		if ( ! $season_file ) {
			return;
		}

		$seasons = json_decode( file_get_contents( $season_file ) );
		$season_terms = wp_list_pluck( $seasons, 'term' );
		$season_terms = array_combine( array_map( 'esc_attr', $season_terms ), $season_terms );

		$cmb = new_cmb2_box( array(
			'id'           => 'cpl_season_data',
			'object_types' => [ $this->post_type, cp_library()->setup->post_types->item_type->post_type ],
			'title'        => __( "Season", 'cp-library' ),
			'context'      => 'side',
			'show_names'   => false,
			'priority'     => 'default',
			'closed'       => false,
		) );

		$cmb->add_field( [
			'name'              => __( 'Assign Season', 'cp-library' ),
			'id'                => 'cpl_season',
			'type'              => 'multicheck',
			'select_all_button' => false,
			'options'           => $season_terms
		] );
	}

}
