<?php
/**
 * Admin request actions require a matching capability and nonce.
 *
 * Logged-out requests, subscriber requests, and requests without a nonce
 * leave data unchanged. An administrator with a valid nonce can run the action.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Integration;

use CP_Library\Admin\Tools;
use CP_Library\Integrations\YouTube;

/**
 * Marks a JSON response that ended the request.
 */
class RequestEnded extends \RuntimeException {}

/**
 * @covers \CP_Library\Admin\Request::allowed
 * @covers \CP_Library\Admin\Tools::export_data
 * @covers \CP_Library\Adapters\Adapter::do_full_import
 * @covers \CP_Library\Adapters\Adapter::update_check
 * @covers \CP_Library\Integrations\YouTube::handle_import_request
 */
class AdminRequestActionsTest extends TestCase {

	/**
	 * @var array
	 */
	private $previous_request;

	public function set_up() {
		parent::set_up();

		$this->previous_request = $_REQUEST;
		$this->enable_json_end();
		add_filter( 'cpl_export_items_output', '__return_false' );
		add_filter( 'pre_http_request', [ $this, 'http_response' ], 10, 3 );
		add_filter( 'wp_die_ajax_handler', [ $this, 'ajax_die_handler' ] );
	}

	public function tear_down() {
		remove_filter( 'wp_doing_cron', '__return_true' );
		$_REQUEST = $this->previous_request;
		wp_set_current_user( 0 );

		$path = $this->export_path();
		if ( file_exists( $path ) ) {
			unlink( $path );
		}

		parent::tear_down();
	}

	public function test_logged_out_export_does_nothing() {
		wp_set_current_user( 0 );
		$this->assert_export_skipped();
	}

	public function test_subscriber_export_does_nothing() {
		$this->set_role( 'subscriber' );
		$this->assert_export_skipped();
	}

	public function test_admin_export_without_nonce_does_nothing() {
		$this->set_role( 'administrator' );
		$this->assert_export_skipped();
	}

	public function test_admin_export_with_nonce_writes_a_file() {
		$this->make_item();
		$this->set_role( 'administrator' );
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'cp_export_items' );

		$this->call_quietly( function () {
			Tools::get_instance()->export_data();
		} );

		$path = $this->export_path();
		$this->assertFileExists( $path );
		$this->assertGreaterThan( 0, filesize( $path ) );
	}

	public function test_logged_out_import_deletes_nothing() {
		wp_set_current_user( 0 );
		$this->assert_import_skipped();
	}

	public function test_subscriber_import_deletes_nothing() {
		$this->set_role( 'subscriber' );
		$this->assert_import_skipped();
	}

	public function test_admin_import_without_nonce_deletes_nothing() {
		$this->set_role( 'administrator' );
		$this->assert_import_skipped();
	}

	public function test_admin_import_with_nonce_starts() {
		$this->set_role( 'administrator' );
		$this->seed_store_option();
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'cpl_adapter_import_sermon_audio' );

		$finished = $this->call_quietly( function () {
			$this->adapter()->do_full_import();
		} );

		$this->assertTrue( $finished );
		$this->assertNull( $this->store_value() );
		$this->assertNotEmpty( get_option( 'cpl_sermon_audio_adapter_import_in_progress' ) );
	}

	public function test_logged_out_pull_does_nothing() {
		wp_set_current_user( 0 );
		$this->assert_pull_skipped();
	}

	public function test_subscriber_pull_does_nothing() {
		$this->set_role( 'subscriber' );
		$this->assert_pull_skipped();
	}

	public function test_admin_pull_without_nonce_does_nothing() {
		$this->set_role( 'administrator' );
		$this->assert_pull_skipped();
	}

	public function test_admin_pull_with_nonce_runs() {
		$this->set_role( 'administrator' );
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'cpl_adapter_pull_sermon_audio' );
		$before = cp_library()->logging->get_file_contents();

		$this->adapter()->update_check();

		$after = cp_library()->logging->get_file_contents();
		$this->assertStringContainsString( 'Invalid API key', substr( (string) $after, strlen( (string) $before ) ) );
	}

	/**
	 * A request can turn the cron flag on and still dispatch the settings action.
	 * That dispatch is not the scheduled event.
	 */
	public function test_request_pull_with_cron_flag_does_nothing() {
		wp_set_current_user( 0 );
		$this->ensure_adapter_hooks();
		add_filter( 'wp_doing_cron', '__return_true' );

		$this->assertNotFalse( has_action( 'cpl_adapter_pull_sermon_audio', [ $this->adapter(), 'update_check' ] ) );
		$this->assert_dispatched_pull_skipped( 'cpl_adapter_pull_sermon_audio' );
	}

	/**
	 * The request dispatcher runs on init. Firing the scheduled hook from that
	 * dispatch is not a scheduled run.
	 */
	public function test_init_dispatch_of_cron_hook_does_nothing() {
		wp_set_current_user( 0 );
		$this->ensure_adapter_hooks();

		$before = cp_library()->logging->get_file_contents();
		$this->dispatch_during_init( 'cpl_adapter_cron_sermon_audio' );

		$this->assertSame( $before, cp_library()->logging->get_file_contents() );
	}

	/**
	 * The scheduled adapter hook runs the pull without a signed-in user.
	 */
	public function test_scheduled_hook_runs_the_pull() {
		wp_set_current_user( 0 );
		$this->ensure_adapter_hooks();
		add_filter( 'wp_doing_cron', '__return_true' );
		$this->assertNotFalse( has_action( 'cpl_adapter_cron_sermon_audio', [ $this->adapter(), 'update_check' ] ) );

		$before = cp_library()->logging->get_file_contents();

		do_action( 'cpl_adapter_cron_sermon_audio' );

		$after = cp_library()->logging->get_file_contents();
		$this->assertStringContainsString( 'Invalid API key', substr( (string) $after, strlen( (string) $before ) ) );
	}

	public function test_logged_out_transcript_import_does_nothing() {
		wp_set_current_user( 0 );
		$this->assert_transcript_skipped();
	}

	public function test_subscriber_transcript_import_does_nothing() {
		$this->set_role( 'subscriber' );
		$this->assert_transcript_skipped();
	}

	public function test_admin_transcript_import_without_nonce_does_nothing() {
		$this->set_role( 'administrator' );
		$this->assert_transcript_skipped();
	}

	public function test_admin_transcript_import_with_nonce_saves_text() {
		$post_id = $this->sermon_with_video();
		$this->set_role( 'administrator' );
		$_REQUEST['post_id']  = $post_id;
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'cpl_import_transcript' );

		$finished = $this->call_quietly( function () {
			YouTube::get_instance()->handle_import_request();
		} );

		$this->assertTrue( $finished );
		$this->assertStringContainsString( 'Hello there', (string) get_post_meta( $post_id, 'transcript', true ) );
	}

	/**
	 * @param string $role Role name.
	 */
	private function set_role( $role ) {
		$user_id = self::factory()->user->create( [ 'role' => $role ] );
		wp_set_current_user( $user_id );
	}

	private function assert_export_skipped() {
		$path = $this->export_path();
		if ( file_exists( $path ) ) {
			unlink( $path );
		}

		$finished = $this->call_quietly( function () {
			Tools::get_instance()->export_data();
		} );

		$this->assertFalse( $finished );
		$this->assertFileDoesNotExist( $path );
	}

	private function assert_import_skipped() {
		$this->seed_store_option();

		$finished = $this->call_quietly( function () {
			$this->adapter()->do_full_import();
		} );

		$this->assertFalse( $finished );
		$this->assertSame( 'kept', $this->store_value() );
		$this->assertFalse( get_option( 'cpl_sermon_audio_adapter_import_in_progress' ) );
	}

	private function assert_pull_skipped() {
		$before = cp_library()->logging->get_file_contents();

		$this->adapter()->update_check();

		$this->assertSame( $before, cp_library()->logging->get_file_contents() );
	}

	/**
	 * Dispatch one admin request the way the framework does on init.
	 *
	 * @param string $action Request action name.
	 */
	private function assert_dispatched_pull_skipped( $action ) {
		$before = cp_library()->logging->get_file_contents();

		$_GET['cp_action']     = $action;
		$_REQUEST['cp_action'] = $action;
		unset( $_REQUEST['_wpnonce'], $_GET['_wpnonce'], $_POST['_wpnonce'] );
		\ChurchPlugins\Admin\_Init::get_instance()->request_actions();

		$this->assertSame( $before, cp_library()->logging->get_file_contents() );
	}

	/**
	 * Run the request dispatcher while `init` is the current action.
	 *
	 * Other init callbacks are set aside for this call and restored afterward.
	 *
	 * @param string $action Request action name.
	 */
	private function dispatch_during_init( $action ) {
		global $wp_filter;

		$saved             = isset( $wp_filter['init'] ) ? $wp_filter['init'] : null;
		$wp_filter['init'] = new \WP_Hook();
		$previous_get      = $_GET;

		add_action(
			'init',
			function () use ( $action ) {
				$_GET['cp_action']     = $action;
				$_REQUEST['cp_action'] = $action;
				unset( $_REQUEST['_wpnonce'], $_GET['_wpnonce'], $_POST['_wpnonce'] );
				\ChurchPlugins\Admin\_Init::get_instance()->request_actions();
			}
		);

		try {
			do_action( 'init' );
		} finally {
			$_GET = $previous_get;
			if ( null === $saved ) {
				unset( $wp_filter['init'] );
			} else {
				$wp_filter['init'] = $saved;
			}
		}
	}

	/**
	 * Attach the adapter hooks when the adapter is not enabled in settings.
	 */
	private function ensure_adapter_hooks() {
		$adapter = $this->adapter();

		if ( false === has_action( 'cpl_adapter_pull_sermon_audio', [ $adapter, 'update_check' ] ) ) {
			$adapter->actions();
		}
	}

	private function assert_transcript_skipped() {
		$post_id = $this->sermon_with_video();
		$_REQUEST['post_id'] = $post_id;

		$finished = $this->call_quietly( function () {
			YouTube::get_instance()->handle_import_request();
		} );

		$this->assertFalse( $finished );
		$this->assertSame( '', (string) get_post_meta( $post_id, 'transcript', true ) );
	}

	/**
	 * @return int Post id.
	 */
	private function sermon_with_video() {
		$item = $this->make_item();
		$item->update_meta_value( 'video_url', 'https://www.youtube.com/watch?v=abc' );

		return (int) $item->origin_id;
	}

	private function seed_store_option() {
		update_option( 'cpl_sermon_audio_adapter_store_sample', 'kept' );
	}

	/**
	 * @return string|null
	 */
	private function store_value() {
		global $wpdb;

		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
				'cpl_sermon_audio_adapter_store_sample'
			)
		);

		return null === $value ? null : (string) $value;
	}

	/**
	 * @return \CP_Library\Adapters\SermonAudio
	 */
	private function adapter() {
		return cp_library()->adapters->get_adapters()['sermon_audio'];
	}

	/**
	 * @return string
	 */
	private function export_path() {
		$upload   = wp_upload_dir();
		$filename = sanitize_file_name(
			sprintf(
				'%s_%s.csv',
				cp_library()->setup->post_types->item->plural_label,
				date( 'Y-m-d' )
			)
		);

		return trailingslashit( $upload['path'] ) . $filename;
	}

	/**
	 * Make a JSON response end through an exception so the test process can continue.
	 */
	private function enable_json_end() {
		if ( ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}
	}

	/**
	 * @param callable $handler Current handler.
	 * @return callable
	 */
	public function ajax_die_handler( $handler ) {
		return [ $this, 'end_json_request' ];
	}

	/**
	 * @param mixed $message Message.
	 * @param mixed $title   Title.
	 * @param mixed $args    Arguments.
	 */
	public function end_json_request( $message = '', $title = '', $args = [] ) {
		throw new RequestEnded();
	}

	/**
	 * @param callable $callback Callback.
	 * @return bool Whether the callback ended the response.
	 */
	private function call_quietly( $callback ) {
		ob_start();

		try {
			$callback();
		} catch ( RequestEnded $e ) {
			ob_end_clean();
			return true;
		}

		ob_end_clean();
		return false;
	}

	/**
	 * @param mixed  $pre  Short-circuit response.
	 * @param array  $args Request arguments.
	 * @param string $url  Request URL.
	 * @return array|\WP_Error
	 */
	public function http_response( $pre, $args, $url ) {
		if ( is_string( $url ) && false !== strpos( $url, 'youtube.com/watch' ) ) {
			$captions = wp_json_encode(
				[
					'playerCaptionsTracklistRenderer' => [
						'captionTracks' => [
							[ 'baseUrl' => 'https://example.test/transcript' ],
						],
					],
				]
			);

			return $this->http_ok( '"captions":' . $captions . ',"videoDetails":{}' );
		}

		if ( is_string( $url ) && false !== strpos( $url, 'example.test/transcript' ) ) {
			return $this->http_ok( '<text start="1" dur="2">Hello there</text>' );
		}

		return new \WP_Error( 'http_request_failed', 'blocked' );
	}

	/**
	 * @param string $body Response body.
	 * @return array
	 */
	private function http_ok( $body ) {
		return [
			'headers'  => [],
			'body'     => $body,
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'cookies'  => [],
			'filename' => null,
		];
	}
}
