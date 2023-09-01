<?php
/**
 * Initialize adapters
 *
 * @since 1.1.0
 * @package CP_Library
 */

namespace CP_Library\Adapters;

class Init {
	
	/**
	 * @var Init
	 */
	protected static $_instance;

	/**
	 * @var Adapter[]
	 */
	protected static $adapters = [];

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
	 * Adapter init includes
	 *
	 * @return void
	 */
	protected function includes() {
		self::$adapters = array(
			'sermon_audio' => new SermonAudio(),
		);
	}

	/**
	 * Adapter init actions
	 *
	 * @return void
	 */
	protected function actions() {}

	/**
	 * Get all adapters
	 * 
	 * @return Adapter[]
	 * @since 1.1.0
	 */
	public function get_adapters() {
		return self::$adapters;
	}	
}
