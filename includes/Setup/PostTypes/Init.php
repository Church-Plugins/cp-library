<?php

namespace CP_Library\Setup\PostTypes;


use CP_Library\Exception;
use CP_Library\Setup\Tables\ItemMeta;
use CP_Library\Setup\Tables\SourceMeta;

/**
 * Setup plugin initialization for CPTs
 */
class Init {

	/**
	 * @var Init
	 */
	protected static $_instance;

	/**
	 * Setup Item CPT
	 *
	 * @var Item
	 */
	public $item;

	/**
	 * Setup Speaker CPT
	 *
	 * @var Speaker
	 * @author costmo
	 */
	public $speaker;

	/**
	 * Setup Series CPT
	 * @var ItemType
	 */
	public $item_type;

	/**
	 * Only make one instance of Init
	 *
	 * @return Init
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof Init ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor
	 *
	 * Run includes and actions on instantiation
	 *
	 */
	protected function __construct() {
		$this->includes();
		$this->actions();
	}

	/**
	 * Plugin init includes
	 *
	 * @return void
	 */
	protected function includes() {}

	public function in_post_types( $type ) {
		return in_array( $type, $this->get_post_types() );
	}

	public function get_post_types() {
		return [ $this->item->post_type, $this->speaker->post_type, $this->item_type->post_type ];
	}

	/**
	 * @param $type
	 * @param $id
	 *
	 * @return \CP_Library\Models\Item | \CP_Library\Models\Source | \CP_Library\Models\ItemType
	 * @throws Exception
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_type_model( $type, $id ) {
		switch( $type ) {
			case $this->item->post_type:
				return \CP_Library\Models\Item::get_instance_from_origin( $id );
			case $this->speaker->post_type:
				return \CP_Library\Models\Speaker::get_instance_from_origin( $id );
			case $this->item_type->post_type:
				return \CP_Library\Models\ItemType::get_instance_from_origin( $id );
		}
	}

	/**
	 * Plugin init actions
	 *
	 * @return void
	 * @author costmo
	 */
	protected function actions() {
		add_filter( 'cmb2_override_meta_save', [ $this, 'meta_save_override' ], 10, 4 );
		add_filter( 'cmb2_override_meta_remove', [ $this, 'meta_save_override' ], 10, 4 );
		add_filter( 'cmb2_override_meta_value', [ $this, 'meta_get_override' ], 10, 4 );

		add_filter( 'use_block_editor_for_post_type', [ $this, 'disable_gutenberg' ], 10, 2 );

		add_action( 'init', function() {
			register_taxonomy_for_object_type( 'talk_categories', 'cpl_item' );
		}, 20 );

		add_action( 'init', [ $this, 'register_post_types' ] );
	}

	public function register_post_types() {

		$this->item = Item::get_instance();
		$this->speaker = Speaker::get_instance();
		$this->item_type = ItemType::get_instance();


		$this->item->add_actions();

		if ( $this->speaker_enabled() ) {
			$this->speaker->add_actions();
		}

		if ( $this->item_type_enabled() ) {
			$this->item_type->add_actions();
		}

		do_action( 'cpl_register_post_types' );
	}

	/**
	 * If the item type post type is enabled
	 *
	 * @return mixed|void
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function item_type_enabled() {
		return apply_filters( 'cpl_enable_item_types', true );
	}

	/**
	 * If the item type post type is enabled
	 *
	 * @return mixed|void
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function speaker_enabled() {
		return apply_filters( 'cpl_enable_speaker', true );
	}

	/**
	 * Hijack the meta save filter to save to our tables
	 *
	 * Currently will also save to postmeta
	 *
	 * @param $return
	 * @param $data_args
	 * @param $field_args
	 * @param $field
	 *
	 * @return mixed
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function meta_save_override( $return, $data_args, $field_args, $field ) {

		$post_id = $data_args['id'];
		$type = get_post_type( $data_args['id'] );

		// break early if this is not our  post type
		if ( ! $this->in_post_types( $type ) ) {
			return $return;
		}

		try {
			$model = $this->get_type_model( $type, $post_id );

			$keys = 'item' == $model::get_prop( 'type' ) ? ItemMeta::get_keys() : SourceMeta::get_keys();

			// only hijack meta keys that we control
			if ( ! in_array( $data_args['field_id'], $keys ) ) {
				return $return;
			}

			// @todo at some point update the return value to prevent saving in meta table and our table
			// for now, we'll save to both places
			if ( isset( $data_args['value'] ) ) {
//				$return = '';
				$model->update_meta_value( $data_args['field_id'], $data_args['value'] );
			} else {
//				$return = '';
				$model->delete_meta( $data_args['field_id'] );
			}
		} catch ( Exception $e ) {
			error_log( $e->getMessage() );
		}

		return $return;
	}

	public function meta_get_override( $data, $object_id, $data_args, $field ) {

		$type = get_post_type( $object_id );

		// break early if this is not our  post type
		if  ( ! $this->in_post_types( get_post_type( $object_id ) ) ) {
			return $data;
		}

		try {
			$model = $this->get_type_model( $type, $object_id );

			$keys = 'item' == $model::get_prop( 'type' ) ? ItemMeta::get_keys() : SourceMeta::get_keys();

			// only hijack meta keys that we control
			if ( ! in_array( $data_args['field_id'], $keys ) ) {
				return $data;
			}

			$data = $model->get_meta_value( $data_args['field_id'] );
		} catch ( Exception $e ) {
			error_log( $e->getMessage() );
		}

		return $data;

	}

	public function disable_gutenberg( $status, $post_type ) {
		if ( $this->in_post_types( $post_type ) ) {
			return false;
		}

		return $status;
	}

}
