<?php
/**
 * PHPUnit bootstrap for integration tests.
 *
 * Unlike tests/bootstrap.php, this one boots a real WordPress with a real
 * database. It is the only way to test what the unit suite structurally cannot:
 * the raw SQL this plugin runs against its cpl_* custom tables. A unit test can
 * prove `diff_associations()` decided to delete two rows; only a database can
 * prove the DELETE removed the right two.
 *
 * Setup (once per machine):
 *
 *   bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]
 *
 * WARNING: the WordPress test suite DROPS EVERY TABLE in the database it is
 * given, on every run. Point it at a dedicated database — never at the one
 * behind a site you care about.
 *
 * Run with `composer test:integration`.
 *
 * @package CP_Library
 */

// Matches bin/install-wp-tests.sh, which hardcodes /tmp rather than using the
// per-user temp dir sys_get_temp_dir() returns on macOS.
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find the WordPress test suite at {$_tests_dir}." . PHP_EOL;
	echo 'Run bin/install-wp-tests.sh first, or set WP_TESTS_DIR.' . PHP_EOL;
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Load the plugin into the test WordPress.
 *
 * cp-library.php is the real entry point — it pulls in the ChurchPlugins
 * submodule and registers everything — so integration tests exercise the same
 * wiring a site does, not a hand-assembled subset.
 */
function _cpl_manually_load_plugin() {
	require dirname( __DIR__ ) . '/cp-library.php';
}
tests_add_filter( 'muplugins_loaded', '_cpl_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

// The cpl_* custom tables are normally created by ChurchPlugins on admin_init,
// which never fires here. Force the install once, now — creating them inside a
// test would issue DDL, and the implicit commit that comes with it would break
// the transaction WP_UnitTestCase uses to roll each test back.
//
// Errors are suppressed for the duration: on a fresh install the per-table
// maybe_update() routines replay ALTERs for columns dbDelta has already created,
// which is harmless but prints a wall of "duplicate column" noise that would
// bury a real failure.
global $wpdb;
$_cpl_suppress = $wpdb->suppress_errors( true );
\ChurchPlugins\Setup\Init::get_instance()->update_install( true );
$wpdb->suppress_errors( $_cpl_suppress );
