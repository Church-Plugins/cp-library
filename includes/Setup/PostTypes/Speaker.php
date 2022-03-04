<?php
namespace CP_Library\Setup\PostTypes;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

use CP_Library\Exception;
use CP_Library\Models\Item as ItemModel;
use CP_Library\Views\Admin\Source as Source_Admin_View;
use CP_Library\Views\Admin\Item as Item_Admin_View;
use CP_Library\Util\Convenience as Convenience;

use CP_Library\Models\Speaker as Speaker_Model;

/**
 * Setup for custom post type: Speaker
 *
 * @author costmo
 * @since 1.0
 */
class Speaker extends PostType {

	/**
	 * Child class constructor. Punts to the parent.
	 *
	 * @author costmo
	 */
	protected function __construct() {

		$this->post_type = CP_LIBRARY_UPREFIX . "_speaker";

		$this->single_label = apply_filters( "cpl_single_{$this->post_type}_label", __( 'Speaker', 'cp_library' ) );
		$this->plural_label = apply_filters( "cpl_plural_{$this->post_type}_label", __( 'Speakers', 'cp_library' ) );

		parent::__construct();
	}

	public function add_actions() {
		parent::add_actions();

		add_filter( 'cmb2_save_post_fields_cpl_speaker_data', [ $this, 'save_item_speaker' ], 10 );
		add_filter( 'cmb2_override_meta_value', [ $this, 'meta_get_override' ], 10, 4 );
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
		$icon   = apply_filters( "cpl_{$this->post_type}_icon", 'dashicons-groups' );

		$args = [
			'public'        => true,
			'menu_icon'     => $icon,
			'show_in_menu'  => 'edit.php?post_type=' . Item::get_instance()->post_type,
			'show_in_rest'  => true,
			'has_archive'   => CP_LIBRARY_UPREFIX . '-' . strtolower( $single ) . '-archive',
			'hierarchical'  => true,
			'label'         => $single,
			'rewrite'       => [
				'slug' 		=> strtolower( $single )
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
		$this->item_speaker();
	}

	protected function item_speaker() {

		$speakers = Speaker_Model::get_all_speakers();

		$cmb = new_cmb2_box( array(
			'id'           => 'cpl_speaker_data',
			'object_types' => [ cp_library()->setup->post_types->item->post_type ],
			'title'        => $this->single_label,
			'context'      => 'side',
			'show_names'   => true,
			'priority'     => 'high',
			'closed'       => false,
		) );

		if ( empty( $speakers ) ) {
			$cmb->add_field( [
				'desc' => sprintf( __( 'No %s have been created yet. <a href="%s">Create one here.</a>', 'cp-library' ), $this->plural_label, add_query_arg( [ 'post_type' => $this->post_type ], admin_url( 'post-new.php' ) )  ),
				'type' => 'title',
				'id' => 'cpl_no_speakers',
			] );

			return;
		}

		$speakers = array_combine( wp_list_pluck( $speakers, 'id' ), wp_list_pluck( $speakers, 'title' ) );
		$cmb->add_field( [
			'name' => __( 'Assign', 'cp-library' ) . ' ' . $this->single_label,
			'desc' => sprintf( __( 'Create a new %s <a target="_blank" href="%s">here</a>.', 'cp-library' ), $this->plural_label, add_query_arg( [ 'post_type' => $this->post_type ], admin_url( 'post-new.php' ) )  ),
			'id'   => 'cpl_speaker',
			'type' => 'multicheck',
			'select_all_button' => false,
			'options' => $speakers
		] );
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
				case 'cpl_speaker':
					$item = ItemModel::get_instance_from_origin( $object_id );
					return $item->get_speakers();
			}
		} catch ( Exception $e ) {
			error_log( $e );
		}

		return $data;
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
	public function save_item_speaker( $object_id ) {
		remove_filter( 'cmb2_save_post_fields_cpl_speaker_data', [ $this, 'save_item_speaker' ] );
		try {
			$item = ItemModel::get_instance_from_origin( $object_id );

			if ( ! $speakers = get_post_meta( $object_id, 'cpl_speaker', true ) ) {
				$speakers = [];
			}

			$item->update_speakers( $speakers );

		} catch ( Exception $e ) {
			error_log( $e );
		}
	}

}
