<?php
/**
 * Unit bootstrap for the SermonAudio match-existing add-on.
 *
 * Loads the comparison classes only. WordPress is not booted. The decision
 * about which sermon to link is pure and is tested here; the query and the
 * resync go through the integration suite.
 *
 * @package CP_SermonAudio_Match
 */

ini_set( 'error_log', sys_get_temp_dir() . '/cp-sermonaudio-match-tests.log' );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require dirname( __DIR__ ) . '/includes/TitleNormalizer.php';
require dirname( __DIR__ ) . '/includes/Matcher.php';
