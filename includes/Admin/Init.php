<?php

namespace CP_Library\Admin;

/**
 * Admin-only plugin initialization
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
	 */
	protected function __construct() {
		$this->includes();
		$this->actions();
	}

	/**
	 * Admin init includes
	 *
	 * @return void
	 */
	protected function includes() {
		Settings::get_instance();
		Tools::get_instance();
	}

	/**
	 * Admin init actions
	 *
	 * @return void
	 */
	protected function actions() {
	}

	/** Actions ***************************************************/

	/** Helpers ***************************************************/
	public function get_admin_base_url() {
		$post_type = cp_library()->setup->post_types->item_type_enabled() ? cp_library()->setup->post_types->item_type->post_type : cp_library()->setup->post_types->item->post_type;

		// Default args
		$args = array(
			'post_type' => $post_type
		);

		// Default URL
		$admin_url = admin_url( 'edit.php' );

		// Get the base admin URL
		$url = add_query_arg( $args, $admin_url );

		// Filter & return
		return apply_filters( 'cp_library_get_admin_base_url', $url, $args, $admin_url );
	}

	public function get_admin_url( $args ) {
		return add_query_arg( $args, $this->get_admin_base_url() );
	}

}
