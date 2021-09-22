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
	protected function __construct() {

		$this->post_type = CP_LIBRARY_UPREFIX . "_sources";

		$this->single_label = apply_filters( "cpl_single_{$this->post_type}_label", __( 'Source', 'cp_library' ) );
		$this->plural_label = apply_filters( "cpl_plural_{$this->post_type}_label", __( 'Sources', 'cp_library' ) );

		parent::__construct();
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

		return apply_filters( "cpl_{$this->post_type}_args", $args, $this );

	}

	/**
	 * Add metaboxes for this CPT
	 *
	 * @return void
	 * @author costmo
	 */
	public function register_metaboxes() {

		$admin_view = new Source_Admin_View();

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

		parent::add_actions();
		return;
	}





}
