<?php
/**
 * Plugin Name: CP Sermon Library
 * Plugin URL: https://churchplugins.com
 * Description: Church library plugin for sermons, talks, and other media
 * Version: 1.1.0
 * Author: Church Plugins
 * Author URI: https://churchplugins.com
 * Text Domain: cp-library
 * Domain Path: languages
 */

if( !defined( 'CP_LIBRARY_PLUGIN_VERSION' ) ) {
	 define ( 'CP_LIBRARY_PLUGIN_VERSION',
	 	'1.1.0'
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
