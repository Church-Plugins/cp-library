<?php
/**
 * Tests for the sermon CSV export action.
 *
 * The export runs only when the current user can manage options and the
 * request includes a valid nonce. Otherwise no export stream is opened and
 * no file is written.
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
 * @covers \CP_Library\Admin\Tools::export_data
 * @covers \CP_Library\Admin\ActionGuard::allows
 */
class ExportActionTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$_REQUEST['_wpnonce'] = 'export-nonce';
	}

	protected function tearDown(): void {
		unset( $_REQUEST['_wpnonce'] );
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_export_does_not_run_without_manage_options() {
		Functions\expect( 'wp_upload_dir' )->never();
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'wp_verify_nonce' )->justReturn( true );

		$tools = $this->tools();
		$tools->export_data();

		$this->assertSame( 0, $tools->opened );
	}

	public function test_export_does_not_run_with_a_bad_nonce() {
		Functions\expect( 'wp_upload_dir' )->never();
		Functions\when( 'current_user_can' )->alias(
			static function ( $cap ) {
				return 'manage_options' === $cap;
			}
		);
		Functions\when( 'wp_verify_nonce' )->justReturn( false );

		$tools = $this->tools();
		$tools->export_data();

		$this->assertSame( 0, $tools->opened );
	}

	/**
	 * @return ExportStreamProbe
	 */
	private function tools() {
		return ( new ReflectionClass( ExportStreamProbe::class ) )->newInstanceWithoutConstructor();
	}
}
