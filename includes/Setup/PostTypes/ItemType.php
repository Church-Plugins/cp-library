<?php
namespace CP_Library\Setup\PostTypes;

use CP_Library\Exception;
use CP_Library\Models\ItemType as Model;
use CP_Library\Models\Item as ItemModel;
use \CP_Library\Controllers\Item as ItemController;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Setup for custom post type: ItemType
 *
 * @author tanner moushey
 * @since 1.0
 */
class ItemType extends PostType  {

	/**
	 * Child class constructor. Punts to the parent.
	 *
	 * @author costmo
	 */
	protected function __construct() {
		$this->post_type = CP_LIBRARY_UPREFIX . "_item_type";

		$this->single_label = apply_filters( "cpl_single_{$this->post_type}_label", __( 'Series', 'cp_library' ) );
		$this->plural_label = apply_filters( "cpl_plural_{$this->post_type}_label", __( 'Series', 'cp_library' ) );

		parent::__construct();

		$item_type   = Item::get_instance()->post_type;
		$source_type = Source::get_instance()->post_type;

		add_filter( "{$item_type}_args", [ $this, 'cpt_menu_position' ], 10, 2 );
		add_filter( "{$source_type}_args", [ $this, 'cpt_menu_position' ], 10 , 2 );

	}

	public function cpt_menu_position( $args, $class ) {
		$args['show_in_menu'] = 'edit.php?post_type=' . $this->post_type;

		return $args;
	}

	public function add_actions() {
		parent::add_actions();

		add_filter( 'cmb2_save_post_fields_cpl_series_items_data', [ $this, 'save_series_items' ], 10, 4 );
		add_filter( 'cmb2_override_meta_value', [ $this, 'meta_get_override' ], 10, 4 );

	}

	/**
	 * Save title to custom table
	 *
	 * @param $post_id
	 *
	 * @return void
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function save_post( $post_id ) {
		$model = parent::save_post( $post_id );

		try {
			$model->update( [ 'title' => get_the_title( $post_id ) ] );
		} catch( Exception $e ) {
			error_log( $e );
		}
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
		$icon   = apply_filters( "cpl_{$this->post_type}_icon", 'dashicons-list-view' );

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

		return apply_filters( "{$this->post_type}_args", $args, $this );
	}

	protected function sermon_series_metabox() {

		$series = Model::get_all_types();
		$series = array_combine( wp_list_pluck( $series, 'id' ), wp_list_pluck( $series, 'title' ) );

		$cmb = new_cmb2_box( array(
			'id'           => 'cpl_series_data',
			'object_types' => [ cp_library()->setup->post_types->item->post_type ],
			'title'        => $this->single_label,
			'context'      => 'side',
			'show_names'   => true,
			'priority'     => 'high',
			'closed'       => false,
		) );

		if ( empty( $series ) ) {
			$cmb->add_field( [
				'desc' => sprintf( __( 'No %s have been created yet. <a href="%s">Create one here.</a>', 'cp-library' ), $this->plural_label, add_query_arg( [ 'post_type' => $this->post_type ], admin_url( 'post-new.php' ) )  ),
				'type' => 'title',
				'id' => 'cpl_no_series',
			] );

			return;
		}

		$cmb->add_field( [
			'name' => __( 'Add to', 'cp-library' ) . ' ' . $this->single_label,
			'id'   => 'cpl_series',
			'type' => 'multicheck',
			'select_all_button' => false,
			'options' => $series
		] );

	}

	public function register_metaboxes() {
		$this->sermon_series_metabox();
//		add_filter( 'cmb2_override_fonts_meta_value', '\StoryLoop\Views\Admin\Product::fonts_serialize_meta', 10, 2 );

		$cmb = new_cmb2_box( array(
			'id'           => 'cpl_series_items_data',
			'object_types' => [ $this->post_type ],
			'title'        => Item::get_instance()->plural_label,
			'context'      => 'normal',
			'show_names'   => true,
			'priority'     => 'low',
			'closed' => false,
		) );

		$group_field_id = $cmb->add_field( [
			'id'         => 'cpl_series_items',
			'type'       => 'group',
			'repeatable' => true,
			'options'    => [
				'group_title'    => Item::get_instance()->single_label . ' {#}',
				'add_button'     => __( 'Add Another', 'cp-library' ) . ' ' . Item::get_instance()->single_label,
				'remove_button'  => __( 'Remove', 'cp-library' ) . ' ' . Item::get_instance()->single_label,
			    'sortable'      => true,
				'remove_confirm' => sprintf( esc_html__( 'Are you sure you want to remove this %s?', 'cp-library' ), Item::get_instance()->single_label ),
				'closed' => true,
			],
		] );

		$cmb->add_group_field( $group_field_id, [
			'id'   => 'id',
			'type' => 'hidden',
		] );

		$cmb->add_group_field( $group_field_id, [
			'name' => 'Title',
			'id'   => 'title',
			'type' => 'text'
		] );

		$cmb->add_group_field( $group_field_id, [
			'name' => 'Thumbnail',
			'id'   => 'thumbnail',
			'type' => 'file',
			'options' => [
				'url' => false,
			],
			'query_args' => array(
				 'type' => array(
				 	'image/gif',
				 	'image/jpeg',
				 	'image/png',
				 ),
			),
			'preview_size' => 'medium',
		] );

		$cmb->add_group_field( $group_field_id, [
			'name' => 'Speaker',
			'id'   => 'speaker',
			'type' => 'text'
		] );

		$cmb->add_group_field( $group_field_id, [
			'name' => 'Date',
			'id'   => 'date',
			'type' => 'text'
		] );

		$cmb->add_group_field( $group_field_id, [
			'name' => __( 'Video URL', 'cp-library' ),
			'desc' => __( 'The URL of the video to show, leave blank to hide this field.', 'cp-library' ),
			'id'   => 'video_url',
			'type' => 'file',
		] );

		$cmb->add_group_field( $group_field_id, [
			'name' => __( 'Audio URL', 'cp-library' ),
			'desc' => __( 'The URL of the audio to show, leave blank to hide this field.', 'cp-library' ),
			'id'   => 'audio_url',
			'type' => 'file',
		] );

		$cmb->add_group_field( $group_field_id, [
			'name' => __( 'Facebook video permalink', 'cp-library' ),
			'id'   => 'video_id_facebook',
			'type' => 'text_medium',
		] );

		$cmb->add_group_field( $group_field_id, [
			'name' => __( 'Vimeo video id', 'cp-library' ),
			'id'   => 'video_id_vimeo',
			'type' => 'text_medium',
		] );

	}

	public function save_series_items( $object_id, $updated, $cmb ) {
		remove_action( 'cmb2_save_post_fields_cpl_series_items_data', [ $this, 'save_series_items' ], 10 );

		try {
			$type = Model::get_instance_from_origin( $object_id );

			$data = get_post_meta( $object_id, 'cpl_series_items', true );

			foreach ( $data as $index => $item_data ) {
				if ( empty( $item_data['id'] ) ) {
					$item_data['id'] = wp_insert_post( [
						'post_type'   => Item::get_instance()->post_type,
						'post_status' => 'publish',
						'post_title'  => $item_data['title'],
						'post_date'   => $item_data['date'],
					] );
				} else {
					wp_update_post( [
						'ID'         => $item_data['id'],
						'post_title' => $item_data['title'],
						'post_date'  => $item_data['date'],
					] );
				}

				if ( ! $item_data['id'] ) {
					throw new Exception( 'The item was not saved correctly.' );
				}

				if ( ! empty( $item_data[ 'thumbnail_id' ] ) ) {
					set_post_thumbnail( $item_data['id'], $item_data['thumbnail_id'] );
				} else {
					delete_post_thumbnail( $item_data['id'] );
				}

				$item = ItemModel::get_instance_from_origin( $item_data['id'] );

				$meta = [ 'video_url', 'audio_url', 'video_id_facebook', 'video_id_vimeo' ];
				foreach( $meta as $key ) {
					if ( empty( $item_data[ $key ] ) ) {
						$item->delete_meta( $key );
					} else {
						$item->update_meta_value( $key, $item_data[ $key ] );
					}
				}

				$item->add_type( $type->id );
				$item->update_type_order( $type->id, $index );
			}


		} catch ( Exception $e ) {
			error_log( $e );
		}

	}

	public function meta_get_override( $data, $object_id, $data_args, $field ) {

		if ( 'cpl_series_items' !== $data_args['field_id'] ) {
			return $data;
		}

		try {

			$series = Model::get_instance_from_origin( $object_id );
			$data   = [];

			foreach ( $series->get_items() as $i ) {
				$item = new ItemController( $i->origin_id );

				$item_data = [
					'id'    => $item->model->origin_id,
					'title' => $item->get_title(),
					'speaker' => '',
					'date' => $item->get_publish_date()->format('Y-m-d\TH:i:sP'),
				];

				$meta = [ 'video_url', 'audio_url', 'video_id_facebook', 'video_id_vimeo' ];
				foreach( $meta as $key ) {
					$item_data[ $key ] = $item->model->get_meta_value( $key );
				}

				if ( has_post_thumbnail( $item_data['id'] ) ) {
					$item_data['thumbnail_id'] = get_post_thumbnail_id( $item_data['id'] );
					$item_data['thumbnail']    = wp_get_attachment_image_url( $item_data['thumbnail_id'], 'medium' );
				}

				$data[] = $item_data;
			}
		} catch ( Exception $e ) {
			error_log( $e );
		}

		return $data;

	}

}
