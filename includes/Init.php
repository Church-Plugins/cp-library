<?php
namespace CP_Library;

use CP_Library\Admin\Settings;

require_once __DIR__ . '/../includes/ChurchPlugins/Setup/Plugin.php';

/**
 * Provides the global $cp_library object
 *
 * @author costmo
 */
class Init extends \ChurchPlugins\Setup\Plugin {

	/**
	 * The single instance of the class.
	 *
	 * @var Init
	 */
	protected static $_instance;

	/**
	 * The Setup instance
	 *
	 * @var Setup\Init
	 */
	public $setup;

	/**
	 * The API instance
	 *
	 * @var API\Init
	 */
	public $api;

	/**
	 * The Admin class instance
	 *
	 * @var Admin\Init
	 */
	public $admin;

	/**
	 * @var Adapters\Init
	 *
	 * @since 1.3.0
	 */
	public $adapters;

	/**
	 * CP Sermons modules for page builders
	 *
	 * @var Modules\Init
	 */
	public $modules;

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
	 * Get plugin directory
	 *
	 * @return string
	 */
	public function get_plugin_dir() {
		return CP_LIBRARY_PLUGIN_DIR;
	}	

	/**
	 * Get plugin URL
	 *
	 * @return string
	 */
	public function get_plugin_url() {
		return CP_LIBRARY_PLUGIN_URL;
	}

	/**
	 * Class constructor: Add Hooks and Actions
	 */
	protected function __construct() {
		add_action( 'cp_core_loaded', array( $this, 'maybe_setup' ), - 9999 );
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

		$cp = \ChurchPlugins\Setup\Init::get_instance();

		Setup\Tables\Init::get_instance();

		if ( get_option( 'cp_library_install_tables' ) ) {
			$cp->update_install( true );
			delete_option( 'cp_library_install_tables' );
		}

		// make sure needed tables are installed.
		if ( ! $cp->is_installed() ) {
			return;
		}

		$this->includes();
		$this->actions();
		$this->app_init();

		$this->setup = Setup\Init::get_instance();
		$this->api   = API\Init::get_instance();

		$this->admin = Admin\Init::get_instance();
		$this->adapters = Adapters\Init::get_instance();

		Download::get_instance();
		Templates::init();

		include_once( CP_LIBRARY_INCLUDES . '/CLI/CP_Migrate.php' );
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
		add_action( 'enqueue_block_editor_assets', [ $this, 'block_editor_assets' ] );
		add_action( 'init', [ $this, 'rewrite_rules' ], 100 );
	}

	/**
	 * Entry point for initializing the Analytics dashboard React component
	 */
	public function analytics_init( $page_hook ) {
		add_action( "load-$page_hook", [ $this, 'enqueue_analytics_scripts' ] );
	}

	/**
	 * Custom rewrite rules for Series
	 */
	public function rewrite_rules() {
		if ( $this->setup->post_types->item_type_enabled() ) {
			$type = get_post_type_object( $this->setup->post_types->item_type->post_type )->rewrite['slug'];
			add_rewrite_tag( '%type-item%', '([^&]+)' );
			add_rewrite_rule( "^$type/([^/]*)/(?!feed)([^/]+)?", 'index.php?cpl_item_type=$matches[1]&type-item=$matches[2]', 'top' );
		}
	}

	/**
	 * `script_loader_tag` filters for the app
	 *
	 * @param String $tag The script tag.
	 * @param String $handle The script handle.
	 * @param String $src The script source.
	 * @return String
	 * @author costmo
	 */
	public function app_load_scripts( $tag, $handle, $src ) {
		if ( 1 !== preg_match( '/^' . CP_LIBRARY_UPREFIX . '-/', $handle ) ) {
			return $tag;
		}

		return str_replace( ' src', ' async defer src', $tag );
	}

	/**
	 * Enqueue scripts for analytics dashboard
	 */
	public function enqueue_analytics_scripts() {
		$this->enqueue_asset( 'admin-analytics', [ 'jquery' ], false, false, true );
		$this->enqueue_asset( 'admin-analytics', [], false, true, true );
	}

	/**
	 * Enqueue scripts on our admin pages
	 */
	public function admin_scripts() {
		wp_enqueue_style( 'material-icons' );
		$this->enqueue_asset( 'admin-scss', [], false, true );

		if ( ! $this->is_admin_page() ) {
			 return;
		}

		$script = $this->enqueue_asset( 'admin-main', [ 'jquery' ], false, false, true );

		wp_localize_script(
			$script['handle'],
			'cplAdmin',
			$this->cpl_vars(),
		);

		wp_enqueue_script( 'wp-api-fetch' );
		wp_enqueue_script( 'inline-edit-post' );
	}

	/**
	 * Enqueue block editor assets.
	 */
	public function block_editor_assets() {
		$this->enqueue_asset( 'scss', [], false, true );

		wp_enqueue_style( 'material-icons' );
		wp_enqueue_script( 'feather-icons' );

		$script = $this->enqueue_asset( 'admin-block-editor', [ 'jquery' ], false, false, true );
		wp_localize_script(
			$script['handle'],
			'cplAdmin',
			$this->cpl_vars(),
		);
	}

	/**
	 * Check if the current page is one of our admin pages.
	 */
	public function is_admin_page() {
		$post_type         = get_post_type();
		$screen            = get_current_screen();
		$primary_post_type = \CP_Library\Util\Convenience::get_primary_post_type();

		if ( isset( $_GET['page'] ) && false !== strpos( $_GET['page'], 'cpl' ) ) {
			return true;
		}

		if ( $screen && str_starts_with( $screen->id, $primary_post_type . '_page' ) ) {
			return true;
		}

		if ( ! $post_type && isset( $_GET['post_type'] ) ) {
			$post_type = $_GET['post_type']; // phpcs:ignore
		}

		if ( in_array( $post_type, $this->setup->post_types->get_post_types() ) ) {
			return true;
		}

		return false;
	}


	/**
	 * `wp_enqueue_scripts` actions for the app's compiled sources
	 *
	 * @return void
	 * @author costmo
	 */
	public function app_enqueue() {
		$scss_asset   = $this->enqueue_asset( 'scss', [], false, true );
		$facets_asset = $this->enqueue_asset( 'facets', [ 'jquery' ], false, false, true );
		$main_asset   = $this->enqueue_asset( 'main', [ 'jquery', $facets_asset['handle'] ], false, false, true );
		$app_asset    = $this->enqueue_asset( 'app', [ 'jquery' ], false, false, true );

		if ( ! empty( $main_asset ) ) {
			wp_add_inline_script(
				$main_asset['handle'],
				'jQuery(document).ready(function() {jQuery("body").append(\'<div id="cpl_persistent_player"></div>\');});',
				'after'
			);
			wp_localize_script( $main_asset['handle'], 'cplVars', $this->cpl_vars() );
		}

		wp_enqueue_style( 'material-icons' );
		wp_enqueue_script( 'feather-icons' );
	}

	/**
	 * Includes
	 *
	 * @return void
	 */
	protected function includes() {
		if ( function_exists( 'cp_locations' ) ) {
			Integrations\Locations::get_instance();
		}

		if ( function_exists( 'cp_resources' ) ) {
			Integrations\Resources::get_instance();
		}

		if ( defined( 'TRIBE_EVENTS_FILE' ) ) {
			Integrations\EventsCalendar::get_instance();
		}

		if ( defined( 'SEARCHWP_VERSION' ) ) {
			Integrations\SearchWP::get_instance();
		}

		Integrations\YouTube::get_instance();

		$this->modules = Modules\Init::get_instance();
	}

	/**
	 * Actions and Filters
	 *
	 * @return void
	 */
	protected function actions() {
		add_action( 'init', [ $this, 'maybe_migrate' ] );
		add_filter( 'query_vars', [ $this, 'query_vars' ] );
		add_action( 'wp_head', [ $this, 'global_css_vars' ] );
		add_action( 'cpl-load-analytics-page', [ $this, 'analytics_init' ] );
	}

	/** Actions **************************************/


	/**
	 * Handle migrations from previous versions
	 *
	 * @since  1.2.0
	 *
	 * @param mixed $version The current version.
	 *
	 * @author Tanner Moushey, 9/6/23
	 */
	public function maybe_migrate( $version = false ) {
		$current_version = get_option( 'cpl_version', false );

		if ( $current_version === $this->get_version() ) {
			return;
		}

		if ( ! $version ) {
			$version = $this->get_version();
		}

		flush_rewrite_rules();
		update_option( 'cpl_version', $this->get_version() );

		do_action( 'cpl_migrate', $current_version, $version );
	}

	/**
	 * Add global CSS variables
	 */
	public function global_css_vars() {
		?>
		<style>
			:root {
				--cpl-color--primary: <?php echo Settings::get( 'color_primary', '#333333' ); ?>;
			}
		</style>
		<?php
	}

	/**
	 * Returns an array to be set as a global JS object
	 */
	public function cpl_vars() {
		global $wp_query;

		return apply_filters(
			'cpl_app_vars',
			array(
				'site' => array(
					'title' => get_bloginfo( 'name', 'display' ),
					'thumb' => Settings::get( 'default_thumbnail', CP_LIBRARY_PLUGIN_URL . 'assets/images/cpl-logo.jpg' ),
					'logo'  => Settings::get( 'logo', CP_LIBRARY_PLUGIN_URL . 'assets/images/cpl-logo.jpg' ),
					'url'   => get_site_url(),
					'path'  => '',
				),
				'components' => array(
					'mobileTop' => '',
				),
				'i18n' => array(
					'playAudio' => Settings::get( 'label_play_audio', __( 'Listen', 'cp-library' ) ),
					'playVideo' => Settings::get( 'label_play_video', __( 'Watch', 'cp-library' ) ),
				),
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'_n'      => wp_create_nonce( 'cpl-admin' ),
				'query_vars' => $wp_query->query_vars,
				'postTypes' => $this->setup->post_types->get_post_type_info(),
				'item' => array(
					'labelSingular' => cp_library()->setup->post_types->item->single_label,
					'labelPlural'   => cp_library()->setup->post_types->item->plural_label,
					'slug'          => Settings::get( 'slug', 'sermons', 'cpl_item_options' )
				),
				'item_type' => array(
					'labelSingular' => cp_library()->setup->post_types->item_type->single_label,
					'labelPlural'   => cp_library()->setup->post_types->item_type->plural_label,
					'slug'          => Settings::get( 'slug', 'series', 'cpl_item_type_options' )
				),
			)
		);
	}

	/**
	 * Add custom query vars to the allowed list
	 *
	 * @param array $vars The current list of allowed query vars.
	 * @return array
	 */
	public function query_vars( $vars ) {
		$vars[] = 'cpl_page';
		return $vars;
	}

	/**
	 * Required Plugins notice
	 *
	 * @return void
	 */
	public function required_plugins() {
		printf( '<div class="error"><p>%s</p></div>', esc_html__( 'Your system does not meet the requirements for Church Plugins - Library', 'cp-library' ) );
	}

	/** Helper Methods **************************************/

	/**
	 * Get the default thumbnail for series and sermions
	 *
	 * @return string
	 */
	public function get_default_thumb() {
		return CP_LIBRARY_PLUGIN_URL . '/app/public/logo512.png';
	}

	/**
	 * Get the admin menu slug
	 *
	 * @since  1.3.0
	 *
	 * @return string|null
	 * @author Tanner Moushey, 10/21/23
	 */
	public function get_admin_menu_slug() {
		return Settings::get_advanced( 'default_menu_item', 'item_type' ) === 'item_type' ? cp_library()->setup->post_types->item_type->post_type : cp_library()->setup->post_types->item->post_type;
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
		return __( 'CP Sermons', 'cp-library' );
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
		return CP_LIBRARY_PLUGIN_VERSION;
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
