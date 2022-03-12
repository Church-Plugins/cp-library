<?php
namespace CP_Library;

use CP_Library\Admin\Settings;
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

	public $enqueue;

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
		$this->enqueue = new \WPackio\Enqueue( 'cpLibrary', 'dist', $this->get_version(), 'plugin', CP_LIBRARY_PLUGIN_FILE );
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

		Download::get_instance();
		Templates::init();

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
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
		add_action( 'init', [ $this, 'rewrite_rules' ], 100 );

		$shortcode = Shortcode_Controller::get_instance();
		$shortcode->add_shortcodes();
	}

	public function rewrite_rules() {

		if ( $this->setup->post_types->item_type_enabled() ) {
			$type = get_post_type_object( $this->setup->post_types->item_type->post_type )->rewrite['slug'];
			add_rewrite_tag( '%type-item%', '([^&]+)' );
			add_rewrite_rule("^$type/([^/]*)/([^/]*)?",'index.php?cpl_item_type=$matches[1]&type-item=$matches[2]','top');
		}

		$flush = '1';

		if ( get_option( '_cpl_needs_flush' ) != $flush ) {
			flush_rewrite_rules(true);
			update_option( '_cpl_needs_flush', $flush );
		}
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

	public function admin_scripts() {
		$this->enqueue->enqueue( 'styles', 'admin', [] );
		$this->enqueue->enqueue( 'scripts', 'admin', [] );
	}

	/**
	 * `wp_enqueue_scripts` actions for the app's compiled sources
	 *
	 * @return void
	 * @author costmo
	 */
	public function app_enqueue() {
		wp_enqueue_script( 'cpl_persistent_player', CP_LIBRARY_PLUGIN_URL . '/assets/js/main.js', ['jquery'] );

		$this->enqueue->enqueue( 'styles', 'main', [] );
		$scripts = $this->enqueue->enqueue( 'app', 'main', [ 'js_dep' => ['jquery'] ] );

		$cpl_vars = apply_filters( 'cpl_app_vars', [
			'site' => [
				'title' => get_bloginfo( 'name', 'display' ),
				'thumb' => Settings::get( 'default_thumbnail', CP_LIBRARY_PLUGIN_URL . 'assets/images/cpl-logo.jpg' ),
				'logo'  => Settings::get( 'logo', CP_LIBRARY_PLUGIN_URL . 'assets/images/cpl-logo.jpg' ),
				'url'   => get_site_url(),
				'path'  => '',
			],
			'components' => [
				'mobileTop' => ''
			],
			'i18n' => [
				'playAudio' => __( 'Play Audio', 'cp-library' ),
				'playVideo' => __( 'Play Video', 'cp-library' ),
			],
		] );

		if ( isset( $scripts['js'], $scripts['js'][0], $scripts['js'][0]['handle'] ) ) {
			wp_localize_script( $scripts['js'][0]['handle'], 'cplVars', $cpl_vars );
		}

		return;

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

		$cpl_vars = apply_filters( 'cpl_app_vars', [
			'site' => [
				'title' => get_bloginfo( 'name', 'display' ),
				'thumb' => Settings::get( 'default_thumbnail', CP_LIBRARY_PLUGIN_URL . 'assets/images/cpl-logo.jpg' ),
				'url'   => get_site_url(),
				'path'  => '',
			],
			'components' => [
				'mobileTop' => ''
			],
		] );

		wp_localize_script( CP_LIBRARY_UPREFIX . '-runtime', 'cplVars', $cpl_vars );

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
				--cpl-primary: <?php echo Settings::get( 'color_primary', '#333333' ); ?>;
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

	public function get_default_thumb() {
		return CP_LIBRARY_PLUGIN_URL . '/app/public/logo512.png';
	}

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
	 * Returns the plugin name, localized
	 *
	 * @since 1.0.0
	 * @return string the plugin name
	 */
	public function get_plugin_path() {
		return CP_LIBRARY_PLUGIN_DIR;
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
	 * Provide a unique ID tag for the plugin
	 *
	 * @return string
	 */
	public function get_version() {
		return '0.0.1';
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
