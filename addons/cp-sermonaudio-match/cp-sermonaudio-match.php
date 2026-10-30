<?php
/**
 * Plugin Name: CP Sermons - SermonAudio Match Existing
 * Plugin URI: https://churchplugins.com
 * Description: When SermonAudio sync finds no sermon with that external ID, link it to the one existing sermon from the same calendar day whose title matches, instead of creating a duplicate.
 * Version: 1.0.0
 * Author: Church Plugins
 * Author URI: https://churchplugins.com
 * Text Domain: cp-sermonaudio-match
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: cp-library
 *
 * @package CP_SermonAudio_Match
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CP_SA_MATCH_VERSION', '1.0.0' );
define( 'CP_SA_MATCH_FILE', __FILE__ );
define( 'CP_SA_MATCH_DIR', plugin_dir_path( __FILE__ ) );

require_once CP_SA_MATCH_DIR . 'includes/TitleNormalizer.php';
require_once CP_SA_MATCH_DIR . 'includes/Matcher.php';
require_once CP_SA_MATCH_DIR . 'includes/Plugin.php';

CP_SermonAudio_Match\Plugin::boot();
