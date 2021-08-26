<?php
namespace SC_Library;

define( 'CPL_APP_PATH', 		plugin_dir_path( dirname( __FILE__ ) ) . '/app' );
define( 'CPL_ASSET_MANIFEST', 	CPL_APP_PATH . '/build/asset-manifest.json' );
define( 'CPL_INCLUDES', 		plugin_dir_path( dirname( __FILE__ ) ) . '/includes' );
define( 'CPL_APP_PREFIX', 		'cpl' );

use SC_Library\Controllers\Shortcode as Shortcode_Controller;

class Init {

	/**
	 * @var
	 */
	protected static $_instance;

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
	 * Add Hooks and Actions
	 */
	protected function __construct() {
		add_action( 'plugins_loaded', array( $this, 'maybe_setup' ), - 9999 );
	}

	/**
	 * Setup the plugin
	 */
	public function maybe_setup() {
		if ( ! $this->check_required_plugins() ) {
			return;
		}

		$this->includes();
		$this->actions();
		$this->app_init();
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

		if( 1 !== preg_match( '/^' . CPL_APP_PREFIX . '-/', $handle ) ) {
			return $tag;
		}

		return str_replace( ' src', ' async defer src', $tag );
	}

	/**
	 * `wp_enqueue_scripts` actions for the app
	 *
	 * @return void
	 * @author costmo
	 */
	public function app_enqueue() {

		$asset_manifest = json_decode( file_get_contents( CPL_ASSET_MANIFEST ), true )['files'];

		if( isset( $asset_manifest[ 'main.css' ] ) ) {
			wp_enqueue_style( CPL_APP_PREFIX, get_site_url() . $asset_manifest[ 'main.css' ] );
		}

		// echo "<pre>" . var_export( $asset_manifest, true ) . "</pre>";

		wp_enqueue_script( CPL_APP_PREFIX . '-runtime', get_site_url() . $asset_manifest[ 'runtime-main.js' ], array(), null, true );

		wp_enqueue_script( CPL_APP_PREFIX . '-main', get_site_url() . $asset_manifest[ 'main.js' ], array( CPL_APP_PREFIX . '-runtime' ), null, true );

		foreach( $asset_manifest as $key => $value ) {
			if( preg_match( '@static/js/(.*)\.chunk\.js@', $key, $matches ) ) {
				if( $matches && is_array( $matches ) && count( $matches ) === 2 ) {
					$name = CPL_APP_PREFIX . "-" . preg_replace( '/[^A-Za-z0-9_]/', '-', $matches[1] );
					wp_enqueue_script( $name, get_site_url() . $value, array( CPL_APP_PREFIX . '-main' ), null, true );
				}
			}

			if( preg_match( '@static/css/(.*)\.chunk\.css@', $key, $matches ) ) {
				if( $matches && is_array( $matches ) && count( $matches ) == 2 ) {
					$name = CPL_APP_PREFIX . "-" . preg_replace( '/[^A-Za-z0-9_]/', '-', $matches[1] );
					wp_enqueue_style( $name, get_site_url() . $value, array( CPL_APP_PREFIX ), null );
				}
			}
		}

	}

	/**
	 * Includes
	 */
	protected function includes() {
		Admin\Init::get_instance();
	}

	/**
	 * Actions and Filters
	 */
	protected function actions() {}

	/** Actions **************************************/

	/**
	 * Required Plugins notice
	 */
	public function required_plugins() {
		printf( '<div class="error"><p>%s</p></div>', __( 'Your system does not meet the requirements for Church Plugins - Library', 'cp-library' ) );
	}

	/** Helper Methods **************************************/

	/**
	 * Make sure RCP is active
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

	public function get_id() {
		return 'cp-library';
	}

}
