<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName
/**
 * Class for the Divi CP Library Template module.
 *
 * @package CP_Library
 */

namespace CP_Library\Modules\Divi;

/**
 * CP Library Divi Extension
 */
class Extension extends \DiviExtension {

	/**
	 * The gettext domain for the extension's translations.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $gettext_domain = 'cp-library';

	/**
	 * The extension's Plugin name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $name = 'cp-library';

	/**
	 * The extension's version
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $version = '1.0.0';

	/**
	 * Class constructor.
	 *
	 * @param string $name The extension's name.
	 * @param array  $args The extension's arguments.
	 */
	public function __construct( $name = 'cp-library', $args = array() ) {
		$this->plugin_dir     = plugin_dir_path( __FILE__ );
		$this->plugin_dir_url = plugin_dir_url( $this->plugin_dir );

		parent::__construct( $name, $args );
	}

	/**
	 * Initialization
	 */
	protected function _initialize() {
		\DiviExtensions::add( $this );

		$this->_set_debug_mode();
		$this->_set_bundle_dependencies();

		add_action( 'et_builder_ready', array( $this, 'hook_et_builder_ready' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Called when the Divi builder is ready
	 */
	public function hook_et_builder_ready() {
		$this->modules();
	}

	/**
	 * Initialize modules
	 */
	public function modules() {
		new Template\Module();
	}

	/**
	 * Register module assets
	 */
	public function enqueue_assets() {
		cp_library()->enqueue->enqueue( 'modules', 'divi', array( 'js_dep' => array( 'jquery' ) ) );
	}
}
