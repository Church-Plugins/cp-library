<?php
/**
 * Tests for analytics, template preview, and adapter cron entry points.
 *
 * Analytics requests require manage_options and a valid nonce, matching the
 * analytics screen. Template preview requires edit_posts and a valid nonce.
 * Adapter cron and dispatcher completion run during cron, or for a user who
 * can manage options.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Library\Adapters\Adapter;
use CP_Library\Admin\Analytics\Init as Analytics;
use CP_Library\Setup\PostTypes\Template;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Adapter stand-in that records whether a pull looked up recent items.
 */
class AdapterWorkProbe extends Adapter {

	/** @var bool */
	public $ran = false;

	public function __construct() {
	}

	public function format_and_process( $items ) {
	}

	public function get_next_batch( $batch ) {
		return false;
	}

	public function get_recent_items( $amount ) {
		$this->ran = true;

		return array();
	}

	public function get_model_from_key( $key ) {
	}

	public function add_attachment( $item, $attachment, $attachment_key ) {
	}

	public function process_cpl_data( $item, $cpl_data, $post_type ) {
	}
}

/**
 * @covers \CP_Library\Admin\Analytics\Init::load_items
 * @covers \CP_Library\Admin\Analytics\Init::get_overview
 * @covers \CP_Library\Setup\PostTypes\Template::render_ajax_content
 * @covers \CP_Library\Adapters\Adapter::update_check
 * @covers \CP_Library\Adapters\Adapter::fetch_complete
 */
class RequestGuardTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_analytics_does_not_run_without_manage_options() {
		$sent = 0;

		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'check_ajax_referer' )->justReturn( true );
		Functions\when( 'wp_send_json_error' )->justReturn( null );
		Functions\when( 'wp_send_json' )->alias(
			static function () use ( &$sent ) {
				$sent++;
			}
		);

		$analytics = $this->analytics();
		$analytics->load_items();
		$analytics->get_overview();

		$this->assertSame( 0, $sent );
		$this->assertFalse( $analytics->analytics_request_is_allowed() );
	}

	public function test_analytics_does_not_run_with_a_bad_nonce() {
		$sent   = 0;
		$action = null;

		Functions\when( 'current_user_can' )->alias(
			static function ( $cap ) {
				return 'manage_options' === $cap;
			}
		);
		Functions\when( 'check_ajax_referer' )->alias(
			static function ( $nonce_action, $arg, $stop ) use ( &$action ) {
				$action = array( $nonce_action, $arg, $stop );

				return false;
			}
		);
		Functions\when( 'wp_send_json_error' )->justReturn( null );
		Functions\when( 'wp_send_json' )->alias(
			static function () use ( &$sent ) {
				$sent++;
			}
		);

		$this->analytics()->load_items();

		$this->assertSame( 0, $sent );
		$this->assertSame( array( 'cpl-analytics', 'nonce', false ), $action );
	}

	public function test_analytics_allows_a_manager_with_a_valid_nonce() {
		Functions\when( 'current_user_can' )->alias(
			static function ( $cap ) {
				return 'manage_options' === $cap;
			}
		);
		Functions\when( 'check_ajax_referer' )->alias(
			static function ( $nonce_action, $arg, $stop ) {
				return 'cpl-analytics' === $nonce_action && 'nonce' === $arg && false === $stop;
			}
		);

		$this->assertTrue( $this->analytics()->analytics_request_is_allowed() );
	}

	public function test_template_preview_does_not_render_without_edit_posts() {
		$lookups = 0;
		$died    = 0;

		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'check_ajax_referer' )->justReturn( true );
		Functions\when( 'get_post' )->alias(
			static function () use ( &$lookups ) {
				$lookups++;

				return null;
			}
		);
		Functions\when( 'wp_die' )->alias(
			static function () use ( &$died ) {
				$died++;
			}
		);

		$this->template()->render_ajax_content();

		$this->assertSame( 0, $lookups );
		$this->assertSame( 1, $died );
	}

	public function test_template_preview_does_not_render_with_a_bad_nonce() {
		$lookups = 0;
		$action  = null;

		Functions\when( 'current_user_can' )->alias(
			static function ( $cap ) {
				return 'edit_posts' === $cap;
			}
		);
		Functions\when( 'check_ajax_referer' )->alias(
			static function ( $nonce_action, $arg, $stop ) use ( &$action ) {
				$action = array( $nonce_action, $arg, $stop );

				return false;
			}
		);
		Functions\when( 'get_post' )->alias(
			static function () use ( &$lookups ) {
				$lookups++;

				return null;
			}
		);
		Functions\when( 'wp_die' )->justReturn( null );

		$this->template()->render_ajax_content();

		$this->assertSame( 0, $lookups );
		$this->assertSame( array( 'cpl_render_template', 'nonce', false ), $action );
	}

	public function test_template_preview_allows_an_editor_with_a_valid_nonce() {
		Functions\when( 'current_user_can' )->alias(
			static function ( $cap ) {
				return 'edit_posts' === $cap;
			}
		);
		Functions\when( 'check_ajax_referer' )->alias(
			static function ( $nonce_action, $arg, $stop ) {
				return 'cpl_render_template' === $nonce_action && 'nonce' === $arg && false === $stop;
			}
		);

		$this->assertTrue( $this->template()->template_ajax_is_allowed() );
	}

	public function test_adapter_work_does_not_run_outside_cron_without_manage_options() {
		Functions\when( 'wp_doing_cron' )->justReturn( false );
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\expect( 'update_option' )->never();

		$adapter       = $this->adapter();
		$adapter->type = 'probe';
		$adapter->update_check();
		$adapter->fetch_complete();

		$this->assertFalse( $adapter->ran );
	}

	public function test_adapter_work_runs_during_cron() {
		$updated = array();

		Functions\when( 'wp_doing_cron' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'apply_filters' )->alias(
			static function ( $tag, $value ) {
				return $value;
			}
		);
		Functions\when( 'update_option' )->alias(
			static function ( $key, $value ) use ( &$updated ) {
				$updated[ $key ] = $value;
			}
		);

		$adapter       = $this->adapter();
		$adapter->type = 'probe';
		$adapter->update_check();
		$adapter->fetch_complete();

		$this->assertTrue( $adapter->ran );
		$this->assertTrue( $updated['cpl_probe_adapter_import_complete'] );
		$this->assertFalse( $updated['cpl_probe_adapter_import_in_progress'] );
	}

	public function test_adapter_work_runs_for_a_manager_outside_cron() {
		Functions\when( 'wp_doing_cron' )->justReturn( false );
		Functions\when( 'current_user_can' )->alias(
			static function ( $cap ) {
				return 'manage_options' === $cap;
			}
		);
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'apply_filters' )->alias(
			static function ( $tag, $value ) {
				return $value;
			}
		);

		$adapter       = $this->adapter();
		$adapter->type = 'probe';
		$adapter->update_check();

		$this->assertTrue( $adapter->ran );
	}

	/**
	 * @return Analytics
	 */
	private function analytics() {
		return ( new ReflectionClass( Analytics::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * @return Template
	 */
	private function template() {
		return ( new ReflectionClass( Template::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * @return AdapterWorkProbe
	 */
	private function adapter() {
		return ( new ReflectionClass( AdapterWorkProbe::class ) )->newInstanceWithoutConstructor();
	}
}
