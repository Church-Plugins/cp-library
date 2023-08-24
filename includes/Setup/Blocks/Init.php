<?php

namespace CP_Library\Setup\Blocks;

use CP_Library\Admin\Settings;

/**
 * Setup plugin initialization for CPTs
 */
class Init {

	/**
	 * @var Init
	 */
	protected static $_instance;

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
	protected function includes() {
		new Query();
		new ItemTitle();
		new SermonTemplate();
		new ItemGraphic();
		new SermonSpeaker();
		new Pagination();
		new SermonActions();
		new SermonSeries();
		new SermonTopics();
		new SermonScripture();
		new ItemDate();
		new ItemDescription();
	}

	/**
	 * Plugin init actions
	 *
	 * @return void
	 */
	protected function actions() {
		add_filter( 'default_post_metadata', [ $this, 'default_thumbnail' ], 10, 5 );
	}

	public function default_thumbnail( $value, $object_id, $meta_key, $single, $meta_type ) {
		if( $value ) {
			return $value;
		}
		if( $meta_key === '_thumbnail_id' && $meta_type === 'post' ) {
			$post_type = get_post_type( $object_id );
			if( $post_type === cp_library()->setup->post_types->item->post_type || $post_type === cp_library()->setup->post_types->item_type->post_type ) {
				$image = Settings::get( 'default_thumbnail', false );
				if( $image ) {
					$image = attachment_url_to_postid( $image );
				}
				return $image ? $image : $value;
			}
		}
		return $value;
	}
}
