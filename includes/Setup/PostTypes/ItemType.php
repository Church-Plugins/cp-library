<?php
namespace CP_Library\Setup\PostTypes;

use CP_Library\Admin\Settings;
use CP_Library\Exception;
use CP_Library\Models\ItemType as Model;
use CP_Library\Models\Item as ItemModel;
use CP_Library\Models\Speaker as Speaker_Model;
use CP_Library\Controllers\Item as ItemController;
use ChurchPlugins\Setup\Taxonomies\Taxonomy;
use ChurchPlugins\Setup\PostTypes\PostType;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Setup for custom post type: ItemType
 *
 * @author tanner moushey
 * @since 1.0
 */
class ItemType extends PostType  {

	protected static $_update_dates = [];

	/**
	 * Child class constructor. Punts to the parent.
	 *
	 * @author costmo
	 */
	protected function __construct() {
		$this->post_type = CP_LIBRARY_UPREFIX . "_item_type";

		$this->single_label = apply_filters( "cpl_single_{$this->post_type}_label", Settings::get_item_type( 'singular_label', 'Series' ) );
		$this->plural_label = apply_filters( "cpl_plural_{$this->post_type}_label", Settings::get_item_type( 'plural_label', 'Series' ) );

		parent::__construct( 'CP_Library' );
	}

	public function cpt_menu_position( $args, $class ) {
		$args['show_in_menu'] = 'edit.php?post_type=' . $this->post_type;

		return $args;
	}

	public function add_actions() {
		parent::add_actions();

		$item_type = Item::get_instance()->post_type;

		// give other code a chance to hook into sources
		add_action( 'save_post', function() {
			foreach ( $this->get_sources() as $key => $source ) {
				add_filter( 'cmb2_save_post_fields_cpl_series_items_data' . $key, [ $this, 'save_series_items' ], 10 );
			}
		}, 5);

		add_action( 'shutdown', [ $this, 'save_post_date'] );
		add_action( 'save_post', [ $this, 'post_date' ] );
		add_filter( 'cmb2_save_field_cpl_series', [ $this, 'save_item_series' ], 10, 3 );
		add_filter( "manage_{$item_type}_posts_columns", [ $this, 'item_type_column' ] );
		add_action( "manage_{$item_type}_posts_custom_column", [ $this, 'item_type_column_cb' ], 10, 2 );
		add_filter( 'pre_get_posts', [ $this, 'item_item_type_query' ] );

		if ( empty( $_GET['cpl-recovery'] ) ) {
			add_filter( 'cmb2_override_meta_value', [ $this, 'meta_get_override' ], 10, 4 );
		}

		if ( $this->show_in_menu() ) {
			$source_type = Speaker::get_instance()->post_type;

			add_filter( "{$item_type}_args", [ $this, 'cpt_menu_position' ], 10, 2 );
			add_filter( "{$source_type}_args", [ $this, 'cpt_menu_position' ], 10, 2 );
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
	public function item_type_column( $columns ) {
		$new_columns = [];
		foreach( $columns as $key => $column ) {
			if ( 'date' === $key ) {
				$new_columns['item_type'] = $this->plural_label;
			}

			$new_columns[ $key ] = $column;
		}

		// in case date isn't set
		if ( ! isset( $columns['date'] ) ) {
			$new_columns['item_type'] = $this->plural_label;
		}

		return $new_columns;
	}

	public function item_type_column_cb( $column, $post_id ) {
		switch( $column ) {
			case 'item_type' :
				 $item = new \CP_Library\Controllers\Item( $post_id );
				$types = $item->get_types();

				 if ( empty( $types ) ) {
					 _e( '—', 'cp-library' );
				 } else {
					 $url = add_query_arg( $_GET, 'edit.php' );
					 $list = [];
					 foreach ( $types as $type ) {
						 $list[] = sprintf( '<a href="%s">%s</a>', add_query_arg( 'type', $type['id'], $url ), $type['title'] );
					 }

					 echo implode( ', ', $list );
				 }

				break;
		}
	}

	public function item_item_type_query( $query ) {
		if ( empty( $_GET['type'] ) ) {
			return;
		}

		if ( ! is_admin() ) {
			return;
		}

		if ( ! $query->is_main_query() ) {
			return;
		}

		if ( ! in_array( $query->get('post_type'), [ Item::get_instance()->post_type ] ) ) {
			return;
		}

		$type = absint( $_GET['type'] );

		try {
			$type = Model::get_instance( $type );
			$items = wp_list_pluck( $type->get_items(), 'origin_id' );

			$query->set( 'post__in', $items );
		} catch ( Exception $e ) {
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
		$args              = parent::get_args();
		$args['menu_icon'] = apply_filters( "{$this->post_type}_icon", 'dashicons-list-view' );

		return $args;
	}

	protected function sermon_series_metabox() {

		$series = Model::get_all_types();
		$series = array_combine( wp_list_pluck( $series, 'id' ), wp_list_pluck( $series, 'title' ) );

		$cmb = new_cmb2_box( array(
			'id'           => 'cpl_series_data',
			'object_types' => [ cp_library()->setup->post_types->item->post_type ],
			'title'        => $this->single_label,
			'context'      => 'side',
			'show_names'   => false,
			'priority'     => 'default',
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

		$cmb->add_field( apply_filters( "{$this->post_type}_metabox_field_args", [
			'name' => __( 'Add to', 'cp-library' ) . ' ' . $this->single_label,
			'id'   => 'cpl_series',
			'desc' => sprintf( __( 'Create a new %s <a target="_blank" href="%s">here</a>.', 'cp-library' ), $this->plural_label, add_query_arg( [ 'post_type' => $this->post_type ], admin_url( 'post-new.php' ) )  ),
			'type' => 'pw_multiselect',
			'select_all_button' => false,
			'options' => $series,
			'attributes' => [
				'placeholder' => sprintf( __( 'Select a %s', 'cp-library' ), $this->single_label ),
			]
		], $this ) );

	}

	public function register_metaboxes() {
		$this->sermon_series_metabox();

		// allow for multiple sources for series (ie. locations)
		foreach( $this->get_sources() as $key => $source ) {
			$this->series_items( $key, $source );
		}
	}

	public function get_sources() {
		$sources = apply_filters( 'cpl_item_type_sources', [] );

		// save a default value
		if ( empty( $sources ) ) {
			$sources = [ '' => '' ];
		}

		return $sources;
	}

	public function series_items( $key, $source ) {

		$label = $source ? Item::get_instance()->plural_label . " ($source)" : Item::get_instance()->plural_label;
		$label = apply_filters( 'cpl_series_items_label', $label, $key, $source );

		$cmb = new_cmb2_box( array(
			'id'           => 'cpl_series_items_data' . $key,
			'object_types' => [ $this->post_type ],
			'title'        => $label,
			'context'      => 'normal',
			'show_names'   => true,
			'priority'     => 'low',
			'closed'       => false,
			'classes'      => 'cpl_series_items_data',
		) );

		$group_field_id = $cmb->add_field( [
			'id'         => 'cpl_series_items' . $key,
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
					'id'                => 'speakers',
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

		foreach( cp_library()->setup->taxonomies->get_objects() as $tax ) {
			/** @var $tax Taxonomy */
			$cmb->add_group_field( $group_field_id, [
				'name'              => $tax->plural_label,
				'id'                => $tax->taxonomy,
				'type'              => 'multicheck_inline',
				'select_all_button' => false,
				'options'           => $tax->get_terms_for_metabox(),
			] );
		}

	}

	/**
	 * Save item series to the item_meta table
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function save_item_series( $updated, $actions, $field ) {
		try {
			$item = ItemModel::get_instance_from_origin( $field->object_id );

			if ( ! $series = array_map( 'absint', $field->data_to_save[ $field->id( true ) ] ) ) {
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
		global $wp_current_filter;

		$current_filter = 'cmb2_save_post_fields_cpl_series_items_data';

		foreach( $wp_current_filter as $filter ) {
			if ( strstr( $filter, $current_filter ) ) {
				$current_filter = $filter;
			}
		}

		remove_action( $current_filter, [ $this, 'save_series_items' ] );
		$source = str_replace( 'cmb2_save_post_fields_cpl_series_items_data', '', $current_filter );

		try {
			$type = Model::get_instance_from_origin( $object_id );

			$data = get_post_meta( $object_id, 'cpl_series_items' . $source, true );

			if ( empty( $data ) || apply_filters( 'cpl_save_series_items_break', false, $object_id, $source ) ) {
				return;
			}

			foreach ( $data as $index => $item_data ) {

				if ( empty( $item_data['content'] ) ) {
					$item_data['content'] = '';
				}

				if ( empty( $item_data['id'] ) ) {
					$item_data['id'] = wp_insert_post( [
						'post_type'    => Item::get_instance()->post_type,
						'post_status'  => 'publish',
						'post_title'   => $item_data['title'],
						'post_date'    => date( 'Y-m-d H:i:s', $item_data['date'] ),
						'post_content' => $item_data['content'],
					] );
				} else {
					wp_update_post( [
						'ID'           => $item_data['id'],
						'post_title'   => $item_data['title'],
						'post_date'    => date( 'Y-m-d H:i:s', $item_data['date'] ),
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

				// save custom taxonomies
				foreach( cp_library()->setup->taxonomies->get_taxonomies() as $tax ) {
					if ( empty( $item_data[ $tax ] ) ) {
						wp_set_post_terms( $item_data['id'], [], $tax );
					} else {
						wp_set_post_terms( $item_data['id'], $item_data[ $tax ], $tax );
					}
				}

				$item = ItemModel::get_instance_from_origin( $item_data['id'] );

				if ( cp_library()->setup->post_types->speaker_enabled() ) {
					$item->update_speakers( $item_data['speakers'] );
				}

				$meta = [ 'video_url', 'audio_url', 'video_id_facebook', 'video_id_vimeo', 'video_id_youtube' ];
				foreach( $meta as $key ) {
					if ( empty( $item_data[ $key ] ) ) {
						$item->delete_meta( $key );
					} else {
						$item->update_meta_value( $key, $item_data[ $key ] );
					}
				}

				$item->add_type( $type->id );
				$item->update_type_order( $type->id, $index );

				do_action( 'cpl_save_series_items_item', $item, $object_id, $source );

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

		// look for a source suffix
		$source = str_replace( 'cpl_series_items', '', $data_args['field_id'] );

		// if a source was found, remove it from the field ID
		if ( $source !== $data_args['field_id'] ) {
			$data_args['field_id'] = str_replace( $source, '', $data_args['field_id'] );
		}

		try {
			switch ( $data_args['field_id'] ) {
				case 'cpl_series_items':
					return $this->get_series_items( $data, $object_id, $source );
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
	 * @param $source String
	 *
	 * @return array
	 * @throws Exception
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	protected function get_series_items( $data, $object_id, $source = '' ) {
		$series = Model::get_instance_from_origin( $object_id );
		$data   = [];

		foreach ( $series->get_items() as $i ) {

			// Allow custom sources to filter out items
			if ( ! apply_filters( 'cpl_item_type_get_items_use_item', true, $i, $source, $object_id, $data ) ) {
				continue;
			}

			$item = new ItemController( $i->origin_id );

			$item_data = [
				'id'      => $item->model->origin_id,
				'title'   => $item->get_title(),
				'content' => $item->get_content( true ),
				'speaker' => '',
				'date'    => date( 'Y-m-d\TH:i:sP', $item->get_publish_date() ),
			];

			$meta = [ 'video_url', 'audio_url', 'video_id_facebook', 'video_id_vimeo' ];
			foreach ( $meta as $key ) {
				$item_data[ $key ] = $item->model->get_meta_value( $key );
			}

			if ( cp_library()->setup->post_types->speaker_enabled() ) {
				$item_data['speakers'] = $item->model->get_speakers();
			}

			if ( has_post_thumbnail( $item_data['id'] ) ) {
				$item_data['thumbnail_id'] = get_post_thumbnail_id( $item_data['id'] );
				$item_data['thumbnail']    = wp_get_attachment_image_url( $item_data['thumbnail_id'], 'medium' );
			}

			foreach( cp_library()->setup->taxonomies->get_taxonomies() as $tax ) {
				$item_data[ $tax ] = wp_get_post_terms( $item_data['id'], $tax, [ 'fields' => 'names' ] );
			}

			$data[] = $item_data;
		}

		return $data;

	}

	/**
	 * Trigger post date calculation
	 *
	 * @param $object_id
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function post_date( $object_id ) {

		$post_type = get_post_type( $object_id );

		try {
			if ( $post_type === $this->post_type ) {
				$type = Model::get_instance_from_origin( $object_id );
				self::$_update_dates[] = $type->id;
			}

			if ( $post_type === Item::get_instance()->post_type ) {
				$item = ItemModel::get_instance_from_origin( $object_id );
				foreach ( $item->get_types() as $type_id ) {
					self::$_update_dates[] = $type_id;
				}
			}
		} catch ( Exception $e ) {
			error_log( $e );
		}

	}

	/**
	 * When saving a series, multiple items are saved, this allows us to only run the action once.
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function save_post_date() {
		remove_action( 'save_post', [ $this, 'post_date' ] );

		$types = array_unique( self::$_update_dates );

		try {
			foreach ( $types as $type_id ) {
				$type = Model::get_instance( $type_id );
				$type->update_dates();
			}
		} catch( Exception $e ) {
			error_log( $e );
		}
	}
}
