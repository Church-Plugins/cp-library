<?php
/**
 * PHPUnit bootstrap for fast, WordPress-free unit tests.
 *
 * These tests do NOT boot WordPress. They exercise pure logic directly and use
 * Brain Monkey to stub any WordPress function a unit under test happens to
 * call. That keeps the suite sub-second and runnable anywhere — no database, no
 * wp-tests-lib, no network.
 *
 * When you need to test a method that calls WP functions, in your test case:
 *
 *   use Brain\Monkey;
 *
 *   protected function setUp(): void {
 *       parent::setUp();
 *       Monkey\setUp();
 *   }
 *   protected function tearDown(): void {
 *       Monkey\tearDown();
 *       parent::tearDown();
 *   }
 *
 * ...then stub with Monkey\Functions\when('get_option')->justReturn( ... ).
 *
 * What this bootstrap CANNOT reach: anything that talks to $wpdb. Much of this
 * plugin's relationship logic is raw SQL against the cpl_* custom tables, so
 * prefer extracting the decision from the query — see
 * Models\Item::surplus_associations() for the shape — and unit test the
 * decision. The query itself belongs in an integration test.
 *
 * @package CP_Library
 */

// Several units log to error_log() on the paths worth testing (unresolved
// speaker names, model exceptions). That is stderr by default, which would spray
// expected messages through the suite output; send it to a file instead so the
// run stays readable and the messages stay inspectable.
ini_set( 'error_log', sys_get_temp_dir() . '/cp-library-tests.log' );

// The plugin's files guard themselves with `defined( 'ABSPATH' ) || exit;`, so
// autoloading any of them without this would silently end the test process.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/tests/fixtures/fake-wp/' );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Real definitions for the pure WP helpers (absint, trailingslashit, ...). See
// that file for the rule on what may go in it — everything with behavior gets
// stubbed per test instead.
require_once __DIR__ . '/wp-polyfills.php';

// Minimal WP_Error stand-in for units that construct/return WP_Error without a
// running WordPress. Pair it in tests with:
//   Brain\Monkey\Functions\when('is_wp_error')->alias(fn($t) => $t instanceof \WP_Error);
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		/** @var string */ public $code;
		/** @var string */ public $message;
		/** @var mixed */  public $data;

		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}
	}
}
