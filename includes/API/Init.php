<?php
namespace CP_Library\API;

use CP_Library\API\Items;

/**
 * Provides the global $cp_library object
 *
 * @author costmo
 */
class Init {

	/**
	 * @var
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
	 * Class constructor: Add Hooks and Actions
	 *
	 */
	protected function __construct() {
		add_action( 'rest_api_init', [ $this, 'load_api_routes' ] );
	}

	/** Actions **************************************/

	/**
	 * Loads the APIs that are not loaded automatically
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function load_api_routes() {
		$controller = new Items();
		$controller->register_routes();
	}

	/** Helper Methods **************************************/


}
