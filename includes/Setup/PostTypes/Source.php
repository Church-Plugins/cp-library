<?php
namespace CP_Library\Setup\PostTypes;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

use CP_Library\Views\Admin\Source as Source_Admin_View;
use CP_Library\Views\Admin\Item as Item_Admin_View;
use CP_Library\Util\Convenience as Convenience;

use CP_Library\Models\Source as Source_Model;

/**
 * Setup for custom post type: Source
 *
 * @author costmo
 * @since 1.0
 */
class Source extends PostType {

	/**
	 * Child class constructor. Punts to the parent.
	 *
	 * @author costmo
	 */
	public function __construct() {

		$this->post_type = CP_LIBRARY_UPREFIX . "_sources";
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
			self::$_instance->cpt_args = self::$_instance->get_args();
			self::$_instance->metaboxes = self::$_instance->add_metaboxes();
		}

		return self::$_instance;
	}

	/**
	 * Setup arguments for this CPT
	 *
	 * @return array
	 * @author costmo
	 */
	private function get_args() {

		$plural = __( 'Sources', 'cp_library' );
		$single = __( 'Source', 'cp_library' );

		return [
			'public'        => true,
			'menu_icon'     => 'dashicons-groups',
			'show_in_menu'  => true,
			'show_in_rest'  => true,
			'has_archive'   => CP_LIBRARY_UPREFIX . '-' . $single . '-archive',
			'hierarchical'  => true,
			'label'         => $single,
			'rewrite'       => [
				'slug' 		=> strtolower( $single )
			],
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

	/**
	 * Add metaboxes for this CPT
	 *
	 * @return void
	 * @author costmo
	 */
	public function register_metaboxes() {

		$admin_view = new Source_Admin_View();

		add_meta_box(
			CP_LIBRARY_UPREFIX . '-parent-source',
			__( 'Parent Source', 'cp_library' ),
			[ $admin_view, 'render_parent_source_metabox' ],
			$this->post_type,
			'side',
			'high'
		);
	}

	/**
	 * Add actions for this CPT to WP
	 *
	 * @return void
	 * @author costmo
	 */
	public function add_actions() {

		$model = new Source_Model();

		add_action(
			'save_post_' . CP_LIBRARY_UPREFIX . '_sources',
			[$model, 'save_post'],
			20, 2
		);

		return;
	}





}