<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName
/**
 * Register custom modules for Beaver Builder
 *
 * @package CP_Library
 */

namespace CP_Library\Integrations;

/**
 * Beaver Builder class
 */
class BeaverBuilder {

	/**
	 * The class instance
	 *
	 * @var BeaverBuilder
	 */
	protected static $instance;

	/**
	 * Get the class instance
	 *
	 * @return BeaverBuilder
	 */
	public static function get_instance() {
		if ( ! self::$instance instanceof BeaverBuilder ) {
			self::$instance = new BeaverBuilder();
		}

		return self::$instance;
	}

	/**
	 * Class constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'modules' ) );
	}

	/**
	 * Include the module files
	 *
	 * @return void
	 */
	public function modules() {
		if ( class_exists( 'FLBuilder' ) ) {
			require_once CP_LIBRARY_FL_MODULES_DIR . 'cpl-template/cpl-template.php';
		}
	}
}
