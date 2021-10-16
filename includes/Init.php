<?php
namespace CP_Library;

use CP_Library\Controllers\Shortcode as Shortcode_Controller;

/**
 * Provides the global $cp_library object
 *
 * @author costmo
 */
class Init {

	/**
	 * @var
	 */
	protected static $_instance;

	/**
	 * @var Setup\Init
	 */
	public $setup;

	/**
	 * @var API\Init
	 */
	public $api;

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
	 * Class constructor: Add Hooks and Actions
	 *
	 */
	protected function __construct() {
		add_action( 'plugins_loaded', [ $this, 'maybe_setup' ], - 9999 );
		add_action( 'init', [ $this, 'maybe_init' ] );
	}

	/**
	 * Plugin setup entry hub
	 *
	 * @return void
	 */
	public function maybe_setup() {
		if ( ! $this->check_required_plugins() ) {
			return;
		}

		$this->includes();
		$this->actions();
		$this->app_init();

		$this->setup = Setup\Init::get_instance();
		$this->api   = API\Init::get_instance();

		include_once( CP_LIBRARY_INCLUDES . '/CLI/RE_Migrate.php' );
	}

	/**
	 * Actions that must run through the `init` hook
	 *
	 * @return void
	 * @author costmo
	 */
	public function maybe_init() {

		if ( ! $this->check_required_plugins() ) {
			return;
		}

	}

	/**
	 * Entry point for initializing the React component
	 *
	 * @return void
	 * @author costmo
	 */
	protected function app_init() {
		add_filter( 'script_loader_tag', [ $this, 'app_load_scripts' ], 10, 3 );
		add_action( 'wp_enqueue_scripts', [ $this, 'app_enqueue' ] );

		$shortcode = Shortcode_Controller::get_instance();
		$shortcode->add_shortcodes();
	}

	/**
	 * `script_loader_tag` filters for the app
	 *
	 * @param String $tag
	 * @param String $handle
	 * @param String $src
	 * @return String
	 * @author costmo
	 */
	public function app_load_scripts( $tag, $handle, $src ) {

		if( 1 !== preg_match( '/^' . CP_LIBRARY_UPREFIX . '-/', $handle ) ) {
			return $tag;
		}

		return str_replace( ' src', ' async defer src', $tag );
	}

	/**
	 * `wp_enqueue_scripts` actions for the app's compiled sources
	 *
	 * @return void
	 * @author costmo
	 */
	public function app_enqueue() {

		wp_enqueue_script( 'cpl_persistent_player', CP_LIBRARY_PLUGIN_URL . '/assets/js/main.js', ['jquery'] );

		$asset_manifest = json_decode( file_get_contents( CP_LIBRARY_ASSET_MANIFEST ), true );

		// TODO: Calls to `str_replace` need to be less specific

		// App CSS
		if( isset( $asset_manifest['files'][ 'main.css' ] ) ) {
			$path = CP_LIBRARY_PLUGIN_URL . str_replace( "/wp-content/plugins/cp-library/", "", $asset_manifest['files'][ 'main.css' ] );
			wp_enqueue_style( CP_LIBRARY_UPREFIX, $path );
		}

		// App runtime js
		if( isset( $asset_manifest['files'][ 'runtime-main.js' ] ) ) {
			$path = CP_LIBRARY_PLUGIN_URL . str_replace( "/wp-content/plugins/cp-library/", "", $asset_manifest['files'][ 'runtime-main.js' ] );
			wp_enqueue_script( CP_LIBRARY_UPREFIX . '-runtime', $path, [] );
		}

		wp_localize_script( CP_LIBRARY_UPREFIX . '-runtime', 'cplParams', [
			'logo' => get_stylesheet_directory_uri() . '/library/images/re-icon.svg',
		] );

		// App main js
		if( isset( $asset_manifest['files'][ 'main.js' ] ) ) {
			$path = CP_LIBRARY_PLUGIN_URL . str_replace( "/wp-content/plugins/cp-library/", "", $asset_manifest['files'][ 'main.js' ] );
			wp_enqueue_script( CP_LIBRARY_UPREFIX . '-main', $path, [] );
		}

		// App static js
		foreach( $asset_manifest['files'] as $key => $value ) {
			if( preg_match( '@static/js/(.*)\.chunk\.js$@', $key, $matches ) ) {

				if( $matches && is_array( $matches ) && count( $matches ) === 2 ) {
					$name = CP_LIBRARY_UPREFIX . "-" . preg_replace( '/[^A-Za-z0-9_]/', '-', $matches[1] );
					$path = CP_LIBRARY_PLUGIN_URL . str_replace( "/wp-content/plugins/cp-library/", "", $asset_manifest['files'][ $key ] );
					wp_enqueue_script( $name, $path, array( CP_LIBRARY_UPREFIX . '-main' ), null, true );
				}

			}
		}

	}

	/**
	 * Includes
	 *
	 * @return void
	 */
	protected function includes() {
		Admin\Init::get_instance();
	}

	/**
	 * Actions and Filters
	 *
	 * @return void
	 */
	protected function actions() {
		add_action( 'wp_head', [ $this, 'global_css_vars' ] );
	}

	/** Actions **************************************/

	public function global_css_vars() {
		?>
		<style>
			:root {
				--cpl-primary: #4D6CFA;
			}
		</style>
		<?php
	}

	/**
	 * Required Plugins notice
	 *
	 * @return void
	 */
	public function required_plugins() {
		printf( '<div class="error"><p>%s</p></div>', __( 'Your system does not meet the requirements for Church Plugins - Library', 'cp-library' ) );
	}

	/** Helper Methods **************************************/

	/**
	 * Make sure required plugins are active
	 *
	 * @return bool
	 */
	protected function check_required_plugins() {

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		// @todo check for requirements before loading
		if ( 1 ) {
			return true;
		}

		add_action( 'admin_notices', array( $this, 'required_plugins' ) );

		return false;
	}

	/**
	 * Gets the plugin support URL
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_support_url() {
		return 'https://churchplugins.com/support';
	}

	/**
	 * Returns the plugin name, localized
	 *
	 * @since 1.0.0
	 * @return string the plugin name
	 */
	public function get_plugin_name() {
		return __( 'Church Plugins - Library', 'cp-library' );
	}

	/**
	 * Provide a unique ID tag for the plugin
	 *
	 * @return string
	 */
	public function get_id() {
		return 'cp-library';
	}

	/**
	 * Get the API namespace to use
	 *
	 * @return string
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_api_namespace() {
		return $this->get_id() . '/v1';
	}

}
