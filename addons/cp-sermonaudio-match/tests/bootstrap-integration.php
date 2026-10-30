<?php
/**
 * Integration bootstrap for the SermonAudio match-existing add-on.
 *
 * Uses CP Library's WordPress test harness, then loads this add-on. The
 * harness returns after plugins_loaded, and Plugin::boot() attaches immediately
 * in that case.
 *
 * @package CP_SermonAudio_Match
 */

ini_set( 'error_log', sys_get_temp_dir() . '/cp-sermonaudio-match-tests.log' );

require dirname( __DIR__, 3 ) . '/tests/bootstrap-integration.php';
require dirname( __DIR__ ) . '/cp-sermonaudio-match.php';
