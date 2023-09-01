<?php
namespace CP_Library\API;

use CP_Library\API\Items as Items_API;
use CP_Library\API\ItemTypes as ItemTypes_API;
use CP_Library\API\Sources as Sources_API;

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

	public $items;
	public $item_types;
	public $sources;

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
		$this->items = new Items_API();
		$this->item_types = new ItemTypes_API();
		$this->sources = new Sources_API();
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
		$api_instance = [
			$this->items,
			$this->item_types,
			$this->sources
		];

		foreach( $api_instance as $api ) {
			$api->register_routes();
		}
	}

	/** Helper Methods **************************************/


}
