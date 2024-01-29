<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName
/**
 * Init class for migration
 *
 * @package CP_Library
 * @since 1.3.0
 */

namespace CP_Library\Admin\Migrate;

/**
 * Init class for migration
 *
 * @since 1.3.0
 */
class Init {

	/**
	 * The single instance of the class.
	 *
	 * @var Init
	 */
	protected static $_instance; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * SetupWizard instance
	 *
	 * @var SetupWizard
	 */
	public $wizard;

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
	 */
	protected function __construct() {
		add_action( 'cpl_after_activation', array( $this, 'on_activation' ) );
		add_action( 'cpl_deactivation', array( $this, 'on_deactivation' ) );
		$this->wizard = SetupWizard::get_instance();
	}

	/**
	 * Runs once after the plugin is activated.
	 */
	public function on_activation() {
		if ( $this->wizard->migration_exists() ) {
			$this->wizard->launch();
		}
	}

	/**
	 * Runs when the plugin is deactivated.
	 */
	public function on_deactivation() {
		$this->wizard->cleanup();
	}
}
