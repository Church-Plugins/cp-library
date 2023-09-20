<?php // phpcs:ignore
/**
 * Register custom modules for Elementor
 *
 * @package CP_Library
 */

namespace CP_Library\Modules\Elementor;

/**
 * Init class
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
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			$this->actions();
		}
	}

	/**
	 * Class actions
	 */
	public function actions() {
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
	}

	/**
	 * Register Elementor widgets
	 *
	 * @param object $widgets_manager Elementor widgets manager.
	 */
	public function register_widgets( $widgets_manager ) {
		$widgets_manager->register_widget_type( new Template\Module() );
	}
}
