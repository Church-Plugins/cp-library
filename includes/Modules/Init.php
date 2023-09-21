<?php // phpcs:ignore
/**
 * Register custom modules for other page builders
 *
 * @package CP_Library
 */

namespace CP_Library\Modules;

/**
 * Init class
 */
class Init {

	/**
	 * Elementor instance
	 *
	 * @var Elementor\Init
	 */
	public $elementor;

	/**
	 * Beaver Builder instance
	 *
	 * @var BeaverBuilder\Init
	 */
	public $beaver_builder;

	/**
	 * Divi instance
	 *
	 * @var Divi\Init
	 */
	public $divi;

	/**
	 * The class instance
	 *
	 * @var Init
	 */
	protected static $instance;

	/**
	 * Get the class instance
	 *
	 * @return Init
	 */
	public static function get_instance() {
		if ( ! self::$instance instanceof Init ) {
			self::$instance = new Init();
		}

		return self::$instance;
	}

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initialize the modules
	 *
	 * @return void
	 */
	public function init() {
		$this->elementor      = Elementor\Init::get_instance();
		$this->beaver_builder = BeaverBuilder\Init::get_instance();
		$this->divi           = Divi\Init::get_instance();
	}
}
