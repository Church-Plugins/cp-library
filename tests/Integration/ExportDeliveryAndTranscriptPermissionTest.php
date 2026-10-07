<?php
/**
 * The tools export sends the CSV in the response, and transcript import runs
 * only for a user who can edit that sermon.
 *
 * An earlier export wrote a dated CSV into the uploads directory and left it
 * there. Transcript import ran for whatever post id it was given.
 *
 * The tools forms include the request-action nonce field (`cp_action_nonce`,
 * action `cp_action_{name}`). A ChurchPlugins copy that does not read the
 * field leaves it unused, and the forms still post as before.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Integration;

use CP_Library\Admin\Tools;
use CP_Library\Integrations\YouTube;

/**
 * Marks the end of a streamed export so the test process can continue.
 */
class ExportFinished extends \RuntimeException {}

/**
 * Marks a JSON response that ended the request.
 */
class JsonEnded extends \RuntimeException {}

/**
 * Runs export_data() without ending the PHP process.
 */
class QuietExport extends Tools {

	/**
	 * @var string[]
	 */
	public $download_headers = array();

	public function __construct() {
		// Skip hook registration.
	}

	public function filename() {
		return $this->export_filename();
	}

	protected function send_export_headers( $filename ) {
		$this->download_headers = $this->export_download_headers( $filename );

		if ( ! headers_sent() ) {
			parent::send_export_headers( $filename );
		}
	}

	protected function end_export() {
		throw new ExportFinished();
	}
}

/**
 * @covers \CP_Library\Admin\Tools::export_data
 * @covers \CP_Library\Admin\Tools::request_action_nonce_field
 * @covers \CP_Library\Integrations\YouTube::handle_import_request
 */
class ExportDeliveryAndTranscriptPermissionTest extends TestCase {

	/**
	 * @var array
	 */
	private $previous_request;

	/**
	 * @var bool
	 */
	private $http_called = false;

	public function set_up() {
		parent::set_up();

		$this->previous_request = $_REQUEST;
		$this->http_called      = false;

		add_filter( 'pre_http_request', array( $this, 'http_response' ), 10, 3 );
		add_filter( 'wp_die_ajax_handler', array( $this, 'ajax_die_handler' ) );
	}

	public function tear_down() {
		$_REQUEST = $this->previous_request;
		wp_set_current_user( 0 );

		remove_filter( 'pre_http_request', array( $this, 'http_response' ), 10 );
		remove_filter( 'wp_die_ajax_handler', array( $this, 'ajax_die_handler' ) );

		$tools    = new QuietExport();
		$filename = $tools->filename();
		$fallback = dirname( __DIR__, 2 ) . '/includes/Admin/' . $filename;

		if ( is_file( $fallback ) ) {
			unlink( $fallback );
		}

		parent::tear_down();
	}

	public function test_admin_export_sends_csv_and_leaves_no_upload_file() {
		$title = 'Harbor Export Sermon';
		$this->make_item( $title );
		$this->set_role( 'administrator' );

		$tools    = new QuietExport();
		$filename = $tools->filename();
		$upload   = wp_upload_dir();
		$today    = trailingslashit( $upload['path'] ) . $filename;
		$older    = trailingslashit( $upload['path'] ) . preg_replace( '/\d{4}-\d{2}-\d{2}\.csv$/', '2001-02-03.csv', $filename );
		$fallback = dirname( __DIR__, 2 ) . '/includes/Admin/' . $filename;

		wp_mkdir_p( $upload['path'] );
		file_put_contents( $today, "old,copy\n" );
		file_put_contents( $older, "old,copy\n" );
		file_put_contents( $fallback, "old,copy\n" );

		$csv = $this->capture_export( $tools );

		$this->assertStringContainsString( 'Title', $csv );
		$this->assertStringContainsString( $title, $csv );
		$this->assertFileDoesNotExist( $today );
		$this->assertFileDoesNotExist( $older );
		$this->assertFileDoesNotExist( $fallback );
		$this->assertSame( array(), $this->csv_files( $upload['path'] ) );
		$this->assertSame( array(), $this->csv_files( $upload['basedir'] ) );

		$headers = implode( "\n", $tools->download_headers );
		$this->assertStringContainsString( 'Content-Type: text/csv; charset=utf-8', $headers );
		$this->assertStringContainsString( 'Content-Disposition: attachment; filename="' . $filename . '"', $headers );
	}

	public function test_tools_forms_include_the_request_action_nonce() {
		$this->set_role( 'administrator' );

		ob_start();
		Tools::get_instance()->import_export_display();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'cp_action=cp_export_items', $html );
		$this->assertStringContainsString( 'action=cpl_import_file_sermons', $html );
		$this->assertStringContainsString( 'name="cp_ajax_import_file"', $html );
		$this->assert_nonce_present( $html, 'cp_action_cp_export_items' );
		$this->assert_nonce_present( $html, 'cp_action_cp_upload_import_file' );
	}

	public function test_transcript_import_is_refused_when_the_user_cannot_edit_the_post() {
		$post_id = $this->sermon_with_video();
		$this->set_role( 'subscriber' );
		$_REQUEST['post_id'] = $post_id;

		$response = $this->call_json(
			function () {
				YouTube::get_instance()->handle_import_request();
			}
		);

		$this->assertFalse( $response['success'] );
		$this->assertFalse( $this->http_called );
		$this->assertSame( '', (string) get_post_meta( $post_id, 'transcript', true ) );
	}

	public function test_transcript_import_is_refused_when_the_post_id_is_missing() {
		$this->set_role( 'administrator' );
		unset( $_REQUEST['post_id'] );

		$response = $this->call_json(
			function () {
				YouTube::get_instance()->handle_import_request();
			}
		);

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'No post ID provided', $response['data'] );
		$this->assertFalse( $this->http_called );
	}

	public function test_transcript_import_is_refused_for_an_unknown_post() {
		$this->set_role( 'administrator' );
		$_REQUEST['post_id'] = 999999;

		$response = $this->call_json(
			function () {
				YouTube::get_instance()->handle_import_request();
			}
		);

		$this->assertFalse( $response['success'] );
		$this->assertFalse( $this->http_called );
	}

	public function test_transcript_import_runs_when_the_user_can_edit_the_post() {
		$post_id = $this->sermon_with_video();
		$this->set_role( 'administrator' );
		$_REQUEST['post_id'] = $post_id;

		$response = $this->call_json(
			function () {
				YouTube::get_instance()->handle_import_request();
			}
		);

		$this->assertTrue( $response['success'] );
		$this->assertStringContainsString( 'Hello there', (string) get_post_meta( $post_id, 'transcript', true ) );
	}

	/**
	 * @param string $role Role name.
	 */
	private function set_role( $role ) {
		$user_id = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $user_id );
	}

	/**
	 * @param QuietExport $tools Export runner.
	 * @return string CSV body.
	 */
	private function capture_export( QuietExport $tools ) {
		ob_start();

		try {
			$tools->export_data();
		} catch ( ExportFinished $finished ) {
			return ob_get_clean();
		}

		ob_end_clean();
		$this->fail( 'Export did not finish the download.' );
	}

	/**
	 * @param string $directory Directory to scan.
	 * @return string[]
	 */
	private function csv_files( $directory ) {
		$matches = glob( trailingslashit( $directory ) . '*.csv' );

		return is_array( $matches ) ? $matches : array();
	}

	/**
	 * @param string $html   Rendered tools markup.
	 * @param string $action Nonce action.
	 */
	private function assert_nonce_present( $html, $action ) {
		preg_match_all( '/name="cp_action_nonce" value="([^"]+)"/', $html, $matches );

		foreach ( $matches[1] as $value ) {
			if ( wp_verify_nonce( $value, $action ) ) {
				$this->assertTrue( true );
				return;
			}
		}

		$this->fail( 'Missing nonce for ' . $action );
	}

	/**
	 * @return int Post id.
	 */
	private function sermon_with_video() {
		$item = $this->make_item( 'Transcript Sermon' );
		$item->update_meta_value( 'video_url', 'https://www.youtube.com/watch?v=abc' );

		return (int) $item->origin_id;
	}

	/**
	 * @param callable $callback Callback.
	 * @return array
	 */
	private function call_json( $callback ) {
		if ( ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}

		ob_start();

		try {
			$callback();
			$body = ob_get_clean();
			$this->fail( 'Import request did not end. Body: ' . $body );
		} catch ( JsonEnded $ended ) {
			$body = ob_get_clean();
		}

		$decoded = json_decode( $body, true );
		$this->assertIsArray( $decoded );

		return $decoded;
	}

	/**
	 * @param callable $handler Current handler.
	 * @return callable
	 */
	public function ajax_die_handler( $handler ) {
		return array( $this, 'end_json' );
	}

	/**
	 * @param mixed $message Message.
	 * @param mixed $title   Title.
	 * @param mixed $args    Arguments.
	 */
	public function end_json( $message = '', $title = '', $args = array() ) {
		throw new JsonEnded();
	}

	/**
	 * @param mixed  $pre  Short-circuit response.
	 * @param array  $args Request arguments.
	 * @param string $url  Request URL.
	 * @return array|\WP_Error
	 */
	public function http_response( $pre, $args, $url ) {
		$this->http_called = true;

		if ( is_string( $url ) && false !== strpos( $url, 'youtube.com/watch' ) ) {
			$captions = wp_json_encode(
				array(
					'playerCaptionsTracklistRenderer' => array(
						'captionTracks' => array(
							array( 'baseUrl' => 'https://example.test/transcript' ),
						),
					),
				)
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
		return array(
			'headers'  => array(),
			'body'     => $body,
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}
}
