<?php

namespace CP_Library\Setup\PostTypes;


use CP_Library\Admin\Settings;
use CP_Library\Exception;
use CP_Library\Setup\Tables\ItemMeta;
use ChurchPlugins\Setup\Tables\SourceMeta;

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
	 * @return \CP_Library\Models\Item | \CP_Library\Models\ItemType
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
		add_filter( 'use_block_editor_for_post_type', [ $this, 'disable_gutenberg' ], 10, 2 );
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

		do_action( 'cp_register_post_types' );
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
		$enabled = (bool) Settings::get( 'item_type_enabled', true, 'cpl_advanced_options' );
		return apply_filters( 'cpl_enable_item_types', $enabled );
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
		$enabled = (bool) Settings::get( 'speaker_enabled', true, 'cpl_advanced_options' );
		return apply_filters( 'cpl_enable_speaker', $enabled );
	}

	public function disable_gutenberg( $status, $post_type ) {
		if ( $this->in_post_types( $post_type ) ) {
			return false;
		}

		return $status;
	}

}
