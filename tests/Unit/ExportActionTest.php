<?php
/**
 * Tests for the sermon CSV export action.
 *
 * The export runs only when the current user can manage options and the
 * request includes a valid nonce. A missing capability or a request-action
 * nonce that does not match ends the request with status 403. The tools form
 * nonce is still required. Otherwise no export stream is opened and no file
 * is written.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Library\Admin\Tools;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tools stand-in that records whether the export stream was opened.
 */
class ExportStreamProbe extends Tools {

	/** @var int */
	public $opened = 0;

	public function __construct() {
	}

	protected function open_export_stream() {
		$this->opened++;

		return false;
	}
}

/**
 * Marks an export that ended before the CSV was built.
 */
class ExportStopped extends \RuntimeException {}

/**
 * @covers \CP_Library\Admin\Tools::export_data
 * @covers \CP_Library\Admin\Tools::export_request_is_allowed
 * @covers \CP_Library\Admin\Request::allowed
 */
class ExportActionTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$_REQUEST['_wpnonce'] = 'export-nonce';
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();
	}

	protected function tearDown(): void {
		unset( $_REQUEST['_wpnonce'], $_REQUEST['cp_action_nonce'] );
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_export_does_not_run_without_manage_options() {
		Functions\expect( 'wp_upload_dir' )->never();
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'wp_verify_nonce' )->justReturn( true );

		$tools  = $this->tools();
		$status = $this->run_until_stopped( $tools );

		$this->assertSame( 403, $status );
		$this->assertSame( 0, $tools->opened );
	}

	public function test_export_does_not_run_with_a_bad_nonce() {
		Functions\expect( 'wp_upload_dir' )->never();
		Functions\when( 'current_user_can' )->alias(
			static function ( $cap ) {
				return 'manage_options' === $cap;
			}
		);
		$_REQUEST['cp_action_nonce'] = 'not-the-export-nonce';
		Functions\when( 'wp_verify_nonce' )->justReturn( false );

		$tools  = $this->tools();
		$status = $this->run_until_stopped( $tools );

		$this->assertSame( 403, $status );
		$this->assertSame( 0, $tools->opened );
	}

	public function test_export_does_not_run_without_the_tools_nonce() {
		Functions\expect( 'wp_upload_dir' )->never();
		Functions\expect( 'wp_die' )->never();
		Functions\when( 'current_user_can' )->alias(
			static function ( $cap ) {
				return 'manage_options' === $cap;
			}
		);
		$_REQUEST['cp_action_nonce'] = 'export-action-nonce';
		Functions\when( 'wp_verify_nonce' )->alias(
			static function ( $nonce, $action ) {
				return 'cp_action_cp_export_items' === $action && 'export-action-nonce' === $nonce;
			}
		);

		$tools = $this->tools();
		$tools->export_data();

		$this->assertSame( 0, $tools->opened );
	}

	/**
	 * @param ExportStreamProbe $tools Export runner.
	 * @return int Status passed to wp_die().
	 */
	private function run_until_stopped( ExportStreamProbe $tools ) {
		$status = 0;
		Functions\when( 'wp_die' )->alias(
			static function ( $message = '', $title = '', $args = array() ) use ( &$status ) {
				$status = is_array( $args ) && isset( $args['response'] ) ? (int) $args['response'] : 0;
				throw new ExportStopped();
			}
		);

		try {
			$tools->export_data();
		} catch ( ExportStopped $stopped ) {
			return $status;
		}

		$this->fail( 'Export was not refused.' );
	}

	/**
	 * @return ExportStreamProbe
	 */
	private function tools() {
		return ( new ReflectionClass( ExportStreamProbe::class ) )->newInstanceWithoutConstructor();
	}
}
