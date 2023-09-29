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
	 * Setup Service Type CPT
	 * @var ServiceType
	 */
	public $service_type;

	/**
	 * Setup Template CPT
	 * @var Template
	 */
	public $template;

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

	/**
	 * Returns an array of post types.
	 *
	 * @return array
	 */
	public function get_post_types() {
		return wp_list_pluck( $this->get_models(), 'post_type' );
	}

	/**
	 * Returns metadata for the various post types, used on the frontend.
	 *
	 * @return array {
	 *   The metadata for the post types.
	 *
	 *   @type string $postType    The post type slug.
	 *   @type string $singleLabel The singular label for the post type.
	 *   @type string $pluralLabel The plural label for the post type.
	 * }
	 * @since  1.3.0
	 */
	public function get_post_type_info() {
		$models = $this->get_models();
		$output = array();

		foreach ( $models as $model ) {
			$output[ $model->post_type ] = array(
				'postType'    => $model->post_type,
				'singleLabel' => $model->single_label,
				'pluralLabel' => $model->plural_label,
			);
		}

		return $output;
	}

	/**
	 * Returns CP Library models
	 *
	 * @return array
	 * @since  1.3.0
	 */
	public function get_models() {
		return array( $this->item, $this->speaker, $this->item_type, $this->service_type );
	}

	/**
	 * @param $type
	 * @param $id
	 *
	 * @return \CP_Library\Models\Item | \CP_Library\Models\ItemType | bool
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
			case $this->service_type->post_type:
				return \CP_Library\Models\ServiceType::get_instance_from_origin( $id );
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
		add_action( 'init', [ $this, 'register_post_types' ], 4 );
	}

	public function register_post_types() {

		$this->item         = Item::get_instance();
		$this->speaker      = Speaker::get_instance();
		$this->item_type    = ItemType::get_instance();
		$this->service_type = ServiceType::get_instance();
		$this->template     = Template::get_instance();

		$this->item->add_actions();

		if ( $this->speaker_enabled() ) {
			$this->speaker->add_actions();
		}

		if ( $this->item_type_enabled() ) {
			$this->item_type->add_actions();
		}

		if ( $this->service_type_enabled() ) {
			$this->service_type->add_actions();
		}

		$this->template->add_actions();

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
	 * If the service type post type is enabled
	 *
	 * @return mixed|void
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function service_type_enabled() {
		$enabled = (bool) Settings::get( 'service_type_enabled', false, 'cpl_advanced_options' );
		return apply_filters( 'cpl_enable_service_type', $enabled );
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
