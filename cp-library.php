<?php
/**
 * Plugin Name: CP Sermon Library
 * Plugin URL: https://churchplugins.com
 * Description: Church library plugin for sermons, talks, and other media
 * Version: 1.4.4
 * Author: Church Plugins
 * Author URI: https://churchplugins.com
 * Text Domain: cp-library
 * Domain Path: languages
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Don't allow multiple versions to be active.
if ( function_exists( 'cp_library' ) ) {

	if ( ! function_exists( 'cp_library_pro_just_activated' ) ) {
		/**
		 * When we activate a Pro version, we need to do additional operations:
		 * 1) deactivate a Lite version;
		 * 2) register an option so we know when Pro was activated.
		 *
		 * @since 3.1.1
		 */
		function cp_library_pro_just_activated() {
			if ( ! get_option( 'cp_library_pro_activation_date', false ) ) {
				update_option( 'cp_library_pro_activation_date', time() );
			}
			cp_library_deactivate();
		}
	}
	add_action( 'activate_cp-library-pro/cp-library.php', 'cp_library_pro_just_activated' );

	if ( ! function_exists( 'cp_library_lite_just_activated' ) ) {
		/**
		 * Store temporarily that the Lite version of the plugin was activated.
		 * This is needed because WP does a redirect after activation and
		 * we need to preserve this state to know whether user activated Lite or not.
		 *
		 * @since 1.5.8
		 */
		function cp_library_lite_just_activated() {
			set_transient( 'cp_library_lite_just_activated', true );
		}
	}
	add_action( 'activate_cp-library/cp-library.php', 'cp_library_lite_just_activated' );

	if ( ! function_exists( 'cp_library_lite_just_deactivated' ) ) {
		/**
		 * Store temporarily that Lite plugin was deactivated.
		 * Convert temporary "activated" value to a global variable,
		 * so it is available through the request. Remove from the storage.
		 *
		 * @since 1.5.8
		 */
		function cp_library_lite_just_deactivated() {

			global $cp_library_lite_just_activated, $cp_library_lite_just_deactivated;

			$cp_library_lite_just_activated   = (bool) get_transient( 'cp_library_lite_just_activated' );
			$cp_library_lite_just_deactivated = true;

			delete_transient( 'cp_library_lite_just_activated' );
		}
	}
	add_action( 'deactivate_cp-library/cp-library.php', 'cp_library_lite_just_deactivated' );

	if ( ! function_exists( 'cp_library_deactivate' ) ) {
		/**
		 * Deactivate Lite if EDD Pro already activated.
		 *
		 * @since 1.0.0
		 */
		function cp_library_deactivate() {

			$plugin = 'cp-library/cp-library.php';

			deactivate_plugins( $plugin );

			do_action( 'cp_library_plugin_deactivated', $plugin );
		}
	}
	add_action( 'admin_init', 'cp_library_deactivate' );

	if ( ! function_exists( 'cp_library_lite_notice' ) ) {
		/**
		 * Display the notice after deactivation when Pro is still active
		 * and user wanted to activate the Lite version of the plugin.
		 *
		 * @since 1.0.0
		 */
		function cp_library_lite_notice() {

			global $cp_library_lite_just_activated, $cp_library_lite_just_deactivated;

			if (
				empty( $cp_library_lite_just_activated ) ||
				empty( $cp_library_lite_just_deactivated )
			) {
				return;
			}

			// Currently tried to activate Lite with Pro still active, so display the message.
			printf(
				'<div class="notice notice-warning">
					<p>%1$s</p>
					<p>%2$s</p>
				</div>',
				esc_html__( 'Heads up!', 'cp-library' ),
				esc_html__( 'Your site already has Easy Digital Downloads (Pro) activated. If you want to switch to Easy Digital Downloads, please first go to Plugins → Installed Plugins and deactivate Easy Digital Downloads (Pro). Then, you can activate Easy Digital Downloads.', 'cp-library' )
			);

			if ( isset( $_GET['activate'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				unset( $_GET['activate'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}

			unset( $cp_library_lite_just_activated, $cp_library_lite_just_deactivated );
		}
	}
	add_action( 'admin_notices', 'cp_library_lite_notice' );

	// Do not process the plugin code further.
	return;
}

if( !defined( 'CP_LIBRARY_PLUGIN_VERSION' ) ) {
	 define ( 'CP_LIBRARY_PLUGIN_VERSION',
	 	'1.4.4'
	);
}

require_once( dirname( __FILE__ ) . "/includes/Constants.php" );

require_once( CP_LIBRARY_PLUGIN_DIR . "/includes/ChurchPlugins/init.php" );
require_once( CP_LIBRARY_PLUGIN_DIR . 'vendor/autoload.php' );

use CP_Library\Init as CP_Init;

/**
 * @var CP_Library\Init
 */
global $cp_library;
$cp_library = cp_library();

/**
 * @return CP_Library\Init
 */
function cp_library() {
	return CP_Init::get_instance();
}

/**
 * Load plugin text domain for translations.
 *
 * @return void
 */
function cp_library_load_textdomain() {

	// Traditional WordPress plugin locale filter
	$get_locale = get_user_locale();

	/**
	 * Defines the plugin language locale used in RCP.
	 *
	 * @var string $get_locale The locale to use. Uses get_user_locale()` in WordPress 4.7 or greater,
	 *                  otherwise uses `get_locale()`.
	 */
	$locale        = apply_filters( 'plugin_locale',  $get_locale, 'cp-library' );
	$mofile        = sprintf( '%1$s-%2$s.mo', 'cp-library', $locale );

	// Setup paths to current locale file
	$mofile_global = WP_LANG_DIR . '/cp-library/' . $mofile;

	if ( file_exists( $mofile_global ) ) {
		// Look in global /wp-content/languages/cp-library folder
		load_textdomain( 'cp-library', $mofile_global );
	}

}
add_action( 'init', 'cp_library_load_textdomain' );


/**
 * Runs on plugin activation.
 *
 * @return void
 */
function cpl_activation() {
	update_option( 'cp_library_activated', 1 );
	update_option( 'cp_library_install_tables', 1 );
}

/**
 * Calls an action once when the plugin is activated.
 *
 * @return void
 */
function cpl_after_activation() {
	if ( is_admin() && ! wp_doing_ajax() && get_option( 'cp_library_activated' ) == 1 ) {
		update_option( 'cp_library_activated', time() );
		do_action( 'cpl_after_activation' );
	}
}

/**
 * Runs on plugin deactivation.
 */
function cpl_deactivation() {
	delete_option( 'cp_library_activated' );
	do_action( 'cpl_deactivation' );
}

register_activation_hook( __FILE__, 'cpl_activation' );
register_deactivation_hook( __FILE__, 'cpl_deactivation' );
add_action( 'admin_init', 'cpl_after_activation' );
