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
