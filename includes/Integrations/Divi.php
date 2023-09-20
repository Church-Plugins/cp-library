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
class Divi {

	/**
	 * The class instance
	 *
	 * @var Divi
	 */
	protected static $instance;

	/**
	 * The Divi extension
	 *
	 * @var \CP_Library_Divi_Extension
	 */
	public $extension;

	/**
	 * Get the class instance
	 *
	 * @return Divi
	 */
	public static function get_instance() {
		if ( ! self::$instance instanceof Divi ) {
			self::$instance = new Divi();
		}

		return self::$instance;
	}

	/**
	 * Class constructor
	 */
	protected function __construct() {
		add_action( 'divi_extensions_init', array( $this, 'init_extension' ) );
	}

	/**
	 * Initialize the extension
	 *
	 * @return void
	 */
	public function init_extension() {
		require_once CP_LIBRARY_PLUGIN_DIR . 'divi-modules/includes/DiviExtension.php';
		$this->extension = new \CP_Library_Divi_Extension();
	}
}
