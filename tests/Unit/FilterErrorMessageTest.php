<?php
/**
 * AJAX responses from filter exception handling must use the generic
 * user-facing message. The exception text used to be copied into that
 * response. It now stays in the server log, and that log write still
 * happens only when WP_DEBUG is on.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Library\Filters\ErrorCodes;
use CP_Library\Filters\ErrorHandler;
use CP_Library\Filters\FilterException;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \CP_Library\Filters\ErrorHandler::handle_exception
 */
class FilterErrorMessageTest extends TestCase {

	/** @var ErrorHandler */
	private $handler;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( '__' )->alias(
			function ( $text ) {
				return $text;
			}
		);

		$this->handler = ( new ReflectionClass( ErrorHandler::class ) )->newInstanceWithoutConstructor();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_exception_text_is_replaced_with_the_generic_message() {
		$detail = 'database connector failed for host db.internal';

		$result = $this->handler->handle_exception( new \Exception( $detail ), false );

		$this->assertSame( 'An error occurred in the filter system.', $result['message'] );
		$this->assertSame( ErrorCodes::GENERAL_ERROR, $result['code'] );
		$this->assertSame( 500, $result['status'] );
		$this->assertStringNotContainsString( $detail, wp_json_encode( $result ) );
		$this->assertFalse( $this->logContains( $detail ) );
	}

	public function test_filter_exception_keeps_status_and_extra_fields() {
		$detail = 'Invalid facet ID: speaker<script>';

		$result = $this->handler->handle_exception(
			new FilterException(
				$detail,
				ErrorCodes::FACET_NOT_FOUND,
				array( 'facet_id' => 'speaker' )
			),
			false
		);

		$this->assertSame( 'Filter facet not found.', $result['message'] );
		$this->assertSame( ErrorCodes::FACET_NOT_FOUND, $result['code'] );
		$this->assertSame( 404, $result['status'] );
		$this->assertSame( 'speaker', $result['facet_id'] );
		$this->assertStringNotContainsString( $detail, $result['message'] );
	}

	public function test_query_errors_use_the_shorter_user_message() {
		$detail = 'syntax error near users table';

		$result = $this->handler->handle_exception(
			new FilterException( $detail, ErrorCodes::QUERY_ERROR ),
			false
		);

		$this->assertSame( 'An error occurred. Please try again later.', $result['message'] );
		$this->assertStringNotContainsString( $detail, wp_json_encode( $result ) );
	}

	public function test_json_response_carries_the_generic_message() {
		$detail = 'could not open /var/www/config.php';

		Functions\expect( 'wp_send_json_error' )
			->once()
			->with(
				Mockery::on(
					function ( $payload ) use ( $detail ) {
						return is_array( $payload )
							&& 'An error occurred in the filter system.' === $payload['message']
							&& false === strpos( wp_json_encode( $payload ), $detail );
					}
				)
			);

		$result = $this->handler->handle_exception( new \Exception( $detail ), true );

		$this->assertSame( 'An error occurred in the filter system.', $result['message'] );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_debug_logging_keeps_the_exception_text_on_the_server() {
		if ( ! defined( 'WP_DEBUG' ) ) {
			define( 'WP_DEBUG', true );
		}

		$detail = 'debug-only detail token cpl-filter-9f3a2c';
		$result = $this->handler->handle_exception( new \Exception( $detail ), false );

		$this->assertSame( 'An error occurred in the filter system. [general_error]', $result['message'] );
		$this->assertStringNotContainsString( $detail, $result['message'] );
		$this->assertTrue( $this->logContains( $detail ) );
	}

	/**
	 * @param string $needle
	 * @return bool
	 */
	private function logContains( $needle ) {
		$path = sys_get_temp_dir() . '/cp-library-tests.log';
		if ( ! is_readable( $path ) ) {
			return false;
		}

		return false !== strpos( (string) file_get_contents( $path ), $needle );
	}
}
