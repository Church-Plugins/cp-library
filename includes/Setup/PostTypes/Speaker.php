<?php
namespace CP_Library\Setup\PostTypes;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

use ChurchPlugins\Setup\Tables\SourceMeta;
use CP_Library\Admin\Settings;
use CP_Library\Exception;
use CP_Library\Models\Item as ItemModel;
use CP_Library\Models\Speaker as Speaker_Model;

use ChurchPlugins\Setup\PostTypes\PostType;

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

		$this->single_label = apply_filters( "cpl_single_{$this->post_type}_label", Settings::get_speaker( 'singular_label', 'Speaker' ) );
		$this->plural_label = apply_filters( "cpl_plural_{$this->post_type}_label", Settings::get_speaker( 'plural_label', 'Speakers' ) );

		parent::__construct( 'CP_Library' );
	}

	public function add_actions() {
		parent::add_actions();

		add_filter( 'cmb2_save_field_cpl_speaker', [ $this, 'save_item_speaker' ], 10, 3 );
		add_filter( 'cmb2_override_meta_value', [ $this, 'meta_get_override' ], 10, 4 );
	}

	/**
	 * Return custom meta keys
	 *
	 * @return array|mixed|void
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function meta_keys() {
		return SourceMeta::get_keys();
	}

	/**
	 * Setup arguments for this CPT
	 *
	 * @return array
	 * @author costmo
	 */
	public function get_args() {
		$args                 = parent::get_args();
		$args['menu_icon']    = apply_filters( "{$this->post_type}_icon", 'dashicons-group' );
		$args['show_in_menu'] = 'edit.php?post_type=' . cp_library()->setup->post_types->item->post_type;

		return $args;
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
			'show_names'   => false,
			'priority'     => 'default',
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
		$cmb->add_field( apply_filters( "{$this->post_type}_metabox_field_args", [
			'name' => __( 'Assign', 'cp-library' ) . ' ' . $this->single_label,
			'desc' => sprintf( __( 'Create a new %s <a target="_blank" href="%s">here</a>.', 'cp-library' ), $this->plural_label, add_query_arg( [ 'post_type' => $this->post_type ], admin_url( 'post-new.php' ) )  ),
			'id'   => 'cpl_speaker',
			'type' => 'pw_multiselect',
			'select_all_button' => false,
			'options' => $speakers,
			'attributes' => [
				'placeholder' => sprintf( __( 'Select a %s', 'cp-library' ), $this->single_label ),
			]
		], $this ) );
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
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function save_item_speaker( $updated, $action, $field ) {
		try {
			$item = ItemModel::get_instance_from_origin( $field->object_id );

			if ( ! $speakers = array_map( 'absint', $field->data_to_save[ $field->id( true ) ] ) ) {
				$speakers = [];
			}

			$item->update_speakers( $speakers );

		} catch ( Exception $e ) {
			error_log( $e );
		}
	}

}
