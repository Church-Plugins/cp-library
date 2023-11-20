<?php
namespace CP_Library\Setup\PostTypes;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

use ChurchPlugins\Helpers;
use ChurchPlugins\Setup\Tables\SourceMeta;
use CP_Library\Admin\Settings;
use ChurchPlugins\Exception;
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

		$item_type = Item::get_instance()->post_type;
		add_filter( "manage_{$item_type}_posts_columns", [ $this, 'speaker_column' ] );
		add_action( "manage_{$item_type}_posts_custom_column", [ $this, 'speaker_column_cb' ], 10, 2 );
		add_action( 'pre_get_posts', [ $this, 'speaker_query' ] );
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
		$id = Helpers::get_param( $_GET, 'post' );

		// if this item has variations, break early.
		if ( $id && get_post_meta( $id, '_cpl_has_variations', true ) && cp_library()->setup->variations->is_enabled() ) {
			return;
		}

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

			if ( ! is_array( $field->data_to_save[ $field->id( true ) ] ) || ! $speakers = array_map( 'absint', $field->data_to_save[ $field->id( true ) ] ) ) {
				$speakers = [];
			}

			$item->update_speakers( $speakers );

		} catch ( Exception $e ) {
			error_log( $e );
		}
	}

	/**
	 * @param $columns
	 *
	 * @return array
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function speaker_column( $columns ) {
		$new_columns = [];
		foreach( $columns as $key => $column ) {
			if ( 'date' === $key ) {
				$new_columns['speaker'] = $this->plural_label;
			}

			$new_columns[ $key ] = $column;
		}

		// in case date isn't set
		if ( ! isset( $columns['date'] ) ) {
			$new_columns['speaker'] = $this->plural_label;
		}

		return $new_columns;
	}

	public function speaker_column_cb( $column, $post_id ) {
		switch( $column ) {
			case 'speaker' :
				$item = new \CP_Library\Controllers\Item( $post_id );
				$speakers = $item->get_speakers();

				 if ( empty( $speakers ) ) {
					 _e( '—', 'cp-library' );
				 } else {
					 $url = add_query_arg( $_GET, 'edit.php' );
					 $list = [];
					 foreach ( $speakers as $speaker ) {
						 $list[] = sprintf( '<a href="%s">%s</a>', add_query_arg( 'speaker', $speaker['id'], $url ), $speaker['title'] );
					 }

					 echo implode( ', ', $list );
				 }

				break;
		}
	}

	public function speaker_query( $query ) {

		if ( empty( $_GET['speaker'] ) ) {
			return;
		}

		if ( ! $query->is_main_query() ) {
			return;
		}

		if ( ! in_array( $query->get('post_type'), [ Item::get_instance()->post_type ] ) ) {
			return;
		}

		$speakers = $_GET['speaker'];

		if ( ! is_array( $speakers ) ) {
			$speakers = [ $speakers ];
		}

		$post_in_orig = $query->get( 'post__in' );
		$post_in = [];

		foreach( $speakers as $speaker ) {
			$speaker = absint( $speaker );

			try {
				$speaker = Speaker_Model::get_instance( $speaker );
				$post_in = array_merge( $post_in, $speaker->get_all_items() );
			} catch ( Exception $e ) {
				error_log( $e );
			}

		}

		if ( ! empty( $post_in ) ) {
			if ( ! empty( $post_in_orig ) ) {
				$post_in = array_intersect( $post_in_orig, $post_in );
				$post_in[] = '-1';
			}
			$query->set( 'post__in', $post_in );
		}


	}
}
