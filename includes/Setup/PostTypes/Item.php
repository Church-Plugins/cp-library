<?php
namespace CP_Library\Setup\PostTypes;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Setup for custom post type: Item
 *
 * @author costmo
 * @since 1.0
 */
class Item extends PostType  {

	/**
	 * Child class constructor. Punts to the parent.
	 *
	 * @author costmo
	 */
	public function __construct() {

		$this->post_type = CP_LIBRARY_UPREFIX . "_items";
		parent::__construct();
	}

	/**
	 * Only make one instance
	 *
	 * @return self
	 */
	public static function get_instance() {
		$class = get_called_class();

		if ( !self::$_instance instanceof $class ) {
			self::$_instance = new $class();
			self::$_instance->cpt_args = self::get_args();
		}

		return self::$_instance;
	}

	/**
	 * Setup arguments for this CPT
	 *
	 * @return array
	 * @author costmo
	 */
	private static function get_args() {

		$plural = __( 'Items', 'cp_library' );
		$single = __( 'Item', 'cp_library' );

		return [
			'public'        => true,
			'menu_icon'     => 'dashicons-album',
			'show_in_menu'  => true,
			'show_in_rest'  => true,
			'has_archive'   => CP_LIBRARY_UPREFIX . '-' . $single . '-archive',
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

	}

}