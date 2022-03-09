<?php
namespace CP_Library\Setup\PostTypes;

use CP_Library\Admin\Settings;
use CP_Library\Exception;
use CP_Library\Models\ItemType as Model;
use CP_Library\Models\Item as ItemModel;
use CP_Library\Models\Speaker as Speaker_Model;
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

		$this->single_label = apply_filters( "cpl_single_{$this->post_type}_label", Settings::get_item_type( 'singular_label', 'Series' ) );
		$this->plural_label = apply_filters( "cpl_plural_{$this->post_type}_label", Settings::get_item_type( 'plural_label', 'Series' ) );

		parent::__construct();
	}

	public function cpt_menu_position( $args, $class ) {
		$args['show_in_menu'] = 'edit.php?post_type=' . $this->post_type;

		return $args;
	}

	public function add_actions() {
		parent::add_actions();

		add_filter( 'cmb2_save_post_fields_cpl_series_items_data', [ $this, 'save_series_items' ], 10 );
		add_filter( 'cmb2_save_post_fields_cpl_series_data', [ $this, 'save_item_series' ], 10 );

		if ( empty( $_GET['cpl-recovery'] ) ) {
			add_filter( 'cmb2_override_meta_value', [ $this, 'meta_get_override' ], 10, 4 );
		}

		$item_type   = Item::get_instance()->post_type;
		$source_type = Speaker::get_instance()->post_type;

		add_filter( "{$item_type}_args", [ $this, 'cpt_menu_position' ], 10, 2 );
		add_filter( "{$source_type}_args", [ $this, 'cpt_menu_position' ], 10 , 2 );
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
		$slug   = apply_filters( "cpl_{$this->post_type}_slug", strtolower( sanitize_title( $plural ) ) );

		$args = [
			'public'       => true,
			'menu_icon'    => $icon,
			'show_in_menu' => true,
			'show_in_rest' => true,
			'has_archive'  => $slug,
			'hierarchical' => true,
			'label'        => $single,
			'rewrite'      => [
				'slug' => $slug
			],
			'supports'     => [ 'title', 'editor', 'thumbnail' ],
			'labels'       => [
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
			'desc' => sprintf( __( 'Create a new %s <a target="_blank" href="%s">here</a>.', 'cp-library' ), $this->plural_label, add_query_arg( [ 'post_type' => $this->post_type ], admin_url( 'post-new.php' ) )  ),
			'type' => 'multicheck',
			'select_all_button' => false,
			'options' => $series
		] );

	}

	public function register_metaboxes() {
		$this->sermon_series_metabox();

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
			'type' => 'text',
			'attributes' => [
				'placeholder' => Item::get_instance()->single_label . ' Title',
			]
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

		if ( cp_library()->setup->post_types->speaker_enabled() ) {

			$speakers = Speaker_Model::get_all_speakers();

			if ( empty( $speakers ) ) {
				$cmb->add_group_field( $group_field_id, [
					'desc' => sprintf( __( 'No %s have been created yet. <a href="%s">Create one here.</a>', 'cp-library' ), Speaker::get_instance()->plural_label, add_query_arg( [ 'post_type' => Speaker::get_instance()->post_type ], admin_url( 'post-new.php' ) ) ),
					'id'   => 'cpl_no_speakers',
					'type' => 'title'
				] );
			} else {
				$speakers = array_combine( wp_list_pluck( $speakers, 'id' ), wp_list_pluck( $speakers, 'title' ) );

				$cmb->add_group_field( $group_field_id, [
					'name'              => Speaker::get_instance()->single_label,
					'id'                => 'speaker',
					'type'              => 'multicheck_inline',
					'select_all_button' => false,
					'options'           => $speakers,
					'desc' => sprintf( __( '<br />Create a new %s <a href="%s">here</a>.', 'cp-library' ), Speaker::get_instance()->plural_label, add_query_arg( [ 'post_type' => Speaker::get_instance()->post_type ], admin_url( 'post-new.php' ) ) ),
				] );
			}

		}


		$cmb->add_group_field( $group_field_id, [
			'name' => 'Date',
			'id'   => 'date',
			'type' => 'text_datetime_timestamp'
		] );

		$cmb->add_group_field( $group_field_id, [
			'name' => __( 'Content', 'cp-library' ),
			'desc' => __( 'The content to display alongside with this item, leave blank to hide this field.', 'cp-library' ),
			'id'   => 'content',
			'type' => 'wysiwyg',
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
			'name' => __( 'Youtube video permalink', 'cp-library' ),
			'id'   => 'video_id_youtube',
			'type' => 'text_medium',
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

	/**
	 * Save item series to the item_meta table
	 *
	 * @param $object_id
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function save_item_series( $object_id ) {
		remove_filter( 'cmb2_save_post_fields_cpl_series_data', [ $this, 'save_item_series' ] );
		try {
			$item = ItemModel::get_instance_from_origin( $object_id );

			if ( ! $series = get_post_meta( $object_id, 'cpl_series', true ) ) {
				$series = [];
			}

			$item->update_types( $series );

		} catch ( Exception $e ) {
			error_log( $e );
		}
	}

	/**
	 * Save the items repeater field in the Series CPT
	 *
	 * @param $object_id
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function save_series_items( $object_id ) {
		remove_action( 'cmb2_save_post_fields_cpl_series_items_data', [ $this, 'save_series_items' ] );

		try {
			$type = Model::get_instance_from_origin( $object_id );

			$data = get_post_meta( $object_id, 'cpl_series_items', true );

			foreach ( $data as $index => $item_data ) {
				if ( empty( $item_data['id'] ) ) {
					$item_data['id'] = wp_insert_post( [
						'post_type'    => Item::get_instance()->post_type,
						'post_status'  => 'publish',
						'post_title'   => $item_data['title'],
						'post_date'    => $item_data['date'],
						'post_content' => $item_data['content'],
					] );
				} else {
					wp_update_post( [
						'ID'           => $item_data['id'],
						'post_title'   => $item_data['title'],
						'post_date'    => $item_data['date'],
						'post_content' => $item_data['content'],
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

	/**
	 * Meta override
	 *
	 * @param $data
	 * @param $object_id
	 * @param $data_args
	 * @param $field
	 *
	 * @return array|null
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function meta_get_override( $data, $object_id, $data_args, $field ) {

		try {
			switch ( $data_args['field_id'] ) {
				case 'cpl_series_items':
					return $this->get_series_items( $data, $object_id );
				case 'cpl_series':
					$item = ItemModel::get_instance_from_origin( $object_id );
					return $item->get_types();
			}
		} catch ( Exception $e ) {
			error_log( $e );
		}

		return $data;
	}

	/**
	 * @param $data
	 * @param $object_id
	 *
	 * @return array
	 * @throws Exception
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	protected function get_series_items( $data, $object_id ) {
		$series = Model::get_instance_from_origin( $object_id );
		$data   = [];

		foreach ( $series->get_items() as $i ) {
			$item = new ItemController( $i->origin_id );

			$item_data = [
				'id'      => $item->model->origin_id,
				'title'   => $item->get_title(),
				'content' => $item->get_content( true ),
				'speaker' => '',
				'date'    => $item->get_publish_date()->format( 'Y-m-d\TH:i:sP' ),
			];

			$meta = [ 'video_url', 'audio_url', 'video_id_facebook', 'video_id_vimeo' ];
			foreach ( $meta as $key ) {
				$item_data[ $key ] = $item->model->get_meta_value( $key );
			}

			if ( cp_library()->setup->post_types->speaker_enabled() ) {
				$item_data['speaker'] = $item->model->get_speakers();
			}

			if ( has_post_thumbnail( $item_data['id'] ) ) {
				$item_data['thumbnail_id'] = get_post_thumbnail_id( $item_data['id'] );
				$item_data['thumbnail']    = wp_get_attachment_image_url( $item_data['thumbnail_id'], 'medium' );
			}

			$data[] = $item_data;
		}

		return $data;

	}

}
