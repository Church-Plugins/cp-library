<?php // phpcs:ignore
/**
 * Register custom modules for Beaver Builder
 *
 * @package CP_Library
 */

namespace CP_Library\Modules\Divi;

/**
 * Divi Init class
 */
class Init {

	/**
	 * The class instance
	 *
	 * @var Init
	 */
	protected static $instance;

	/**
	 * The Divi extension
	 *
	 * @var Extension
	 */
	public $extension;

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
	protected function __construct() {
		$this->actions();
	}

	/**
	 * Class actions
	 */
	public function actions() {
		add_action( 'divi_extensions_init', array( $this, 'init_extension' ) );
	}

	/**
	 * Initialize the extension
	 *
	 * @return void
	 */
	public function init_extension() {
		$this->extension = new Extension();
	}
}
