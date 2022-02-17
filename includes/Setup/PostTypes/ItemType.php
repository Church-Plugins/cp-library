<?php
namespace CP_Library\Setup\PostTypes;

use CP_Library\Exception;
use CP_Library\Models\ItemType as Model;
use CP_Library\Models\Item as ItemModel;

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
	}

	public function add_actions() {
		parent::add_actions();

		add_filter( 'cmb2_override_meta_save', [ $this, 'meta_save_override' ], 10, 4 );
		add_filter( 'cmb2_override_meta_remove', [ $this, 'meta_save_override' ], 10, 4 );
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
		$icon   = apply_filters( "cpl_{$this->post_type}_icon", 'dashicons-video-alt3' );

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
		return;
//		add_filter( 'cmb2_override_fonts_meta_value', '\StoryLoop\Views\Admin\Product::fonts_serialize_meta', 10, 2 );




		$cmb = new_cmb2_box( array(
			'id'           => 'cpl_series_data',
			'object_types' => [ $this->post_type ],
			'title'        => __( 'Sermons', 'cp-library' ),
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
			],
		] );

		$cmb->add_group_field( $group_field_id, [
			'name' => 'Title',
			'id'   => 'title',
			'type' => 'text'
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

	public static function save_meta_fields( $object_id, $updated, $cmb ) {
		remove_action( 'cmb2_save_post_fields_product_editable_files', 'StoryLoop\Controllers\EditableFiles::save_meta_fields', 10 );

		$currentProduct = new Product( $object_id );

		if ( 'on' !== $currentProduct->get_meta( 'pef_enable' ) ) {
			return;
		}

		$editableFilesID = $currentProduct->get_editable_file_product_id();
		$editableFilesProduct = new EDD_Download( $editableFilesID );

		if ( 0 == $editableFilesProduct->ID ) {
			$editableFilesProduct->create( [
				'post_status' => 'publish',
				'post_title'  => $currentProduct->get_meta( 'pef_label', $currentProduct->get_name() . ' [' . __( 'Project Files', 'cp-library' ) . ']' ),
				'post_parent' => $object_id,
			] );
		} else {
			wp_update_post( [
				'ID'          => $editableFilesProduct->ID,
				'post_title'  => $currentProduct->get_meta( 'pef_label', $currentProduct->get_name() . ' [' . __( 'Project Files', 'cp-library' ) . ']' ),
				'post_parent' => $object_id,
			] );
		}

		$pricing = [
			[
				'name' => __( 'Full Price', 'cp-library' ),
				'amount' => $currentProduct->get_meta( 'pef_price_full', 0 ),
			],
			[
				'name' => __( 'All Access Price', 'cp-library' ),
				'amount' => $currentProduct->get_meta( 'pef_price_aap', 0 ),
			]
		];

		$downloadFiles = [
			1 => [
				'index'     => 1,
				'file'      => esc_url( $currentProduct->get_meta( 'pef_download_url' ) ),
				'name'      => basename( $currentProduct->get_meta( 'pef_download_url' ) ),
				'condition' => 'all'
			]
		];

		update_post_meta( $editableFilesProduct->ID, 'edd_download_files', $downloadFiles );
		update_post_meta( $editableFilesProduct->ID, '_variable_pricing', 1 );
		update_post_meta( $editableFilesProduct->ID, 'edd_variable_prices', $pricing );
		update_post_meta( $editableFilesProduct->ID, '_edd_all_access_exclude', 1 );
		update_post_meta( $editableFilesProduct->ID, '_is_editable_file', 1 );

		set_post_thumbnail( $editableFilesProduct->ID, get_post_thumbnail_id( $currentProduct->ID ) );

		update_post_meta( $currentProduct->ID, 'editable_file_product', $editableFilesProduct->ID );

	}

	public function meta_save_override( $return, $data_args, $field_args, $field ) {

		if ( 'cpl_series' !== $data_args['field_id'] ) {
			return $return;
		}

		try {
			$item = ItemModel::get_instance_from_origin( $data_args['id'] );

			if ( isset( $data_args['value'] ) ) {
				$return = $item->update_types( $data_args['value'] );
			} else {
				$return = $item->update_types( [] );
			}
		} catch ( Exception $e ) {
			error_log( $e );
		}

		return $return;
	}

	public function meta_get_override( $data, $object_id, $data_args, $field ) {

		if ( 'cpl_series' !== $data_args['field_id'] ) {
			return $data;
		}

		try {
			$item = ItemModel::get_instance_from_origin( $object_id );
			$data = $item->get_types();
		} catch( Exception $e ) {
			error_log( $e );
		}

		return $data;

	}

}
