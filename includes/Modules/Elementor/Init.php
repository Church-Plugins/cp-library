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
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_widget_categories' ) );
	}

	/**
	 * Register Elementor widgets
	 *
	 * @param object $widgets_manager Elementor widgets manager.
	 */
	public function register_widgets( $widgets_manager ) {
		$widgets_manager->register_widget_type( new Template\Module() );
	}

	/**
	 * Register Elementor widget categories
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
	 */
	public function register_widget_categories( $elements_manager ) {
		$elements_manager->add_category(
			'cp-library',
			array(
				'title' => __( 'CP Sermons', 'cp-library' ),
				'icon'  => 'fa fa-plug',
			)
		);
	}
}
