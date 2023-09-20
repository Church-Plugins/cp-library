<?php // phpcs:ignore
/**
 * Register custom modules for Beaver Builder
 *
 * @package CP_Library
 */

namespace CP_Library\Modules\BeaverBuilder;

/**
 * Beaver Builder class
 */
class Init {

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
		if ( defined( 'FL_BUILDER_VERSION' ) ) {
			$this->actions();
		}
	}

	/**
	 * Class actions
	 */
	public function actions() {
		add_action( 'init', array( $this, 'modules' ) );
	}

	/**
	 * Include the module files
	 *
	 * @return void
	 */
	public function modules() {
		require_once dirname( __FILE__ ) . '/Template/Template.php';
	}
}
