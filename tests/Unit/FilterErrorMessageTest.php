<?php
/**
 * Filter exceptions keep the message from get_error_response(). Any other
 * throwable gets the generic string from format_user_error(), and the
 * exception text stays in the server log only when WP_DEBUG is on.
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

	public function test_filter_exception_keeps_its_response_message() {
		$message = 'Invalid facet ID: speaker';

		$result = $this->handler->handle_exception(
			new FilterException(
				$message,
				ErrorCodes::FACET_NOT_FOUND,
				array( 'facet_id' => 'speaker' )
			),
			false
		);

		$this->assertSame( $message, $result['message'] );
		$this->assertIsString( $result['message'] );
		$this->assertSame( ErrorCodes::FACET_NOT_FOUND, $result['code'] );
		$this->assertSame( 404, $result['status'] );
		$this->assertSame( 'speaker', $result['facet_id'] );
	}

	public function test_unexpected_throwable_uses_format_user_error() {
		$formatted = $this->handler->format_user_error( ErrorCodes::GENERAL_ERROR );
		$detail    = 'type mismatch while reading options';

		$this->assertIsString( $formatted );

		$result = $this->handler->handle_exception( new \Error( $detail ), false );

		$this->assertSame( $formatted, $result['message'] );
		$this->assertIsString( $result['message'] );
		$this->assertSame( ErrorCodes::GENERAL_ERROR, $result['code'] );
		$this->assertSame( 500, $result['status'] );
		$this->assertStringNotContainsString( $detail, wp_json_encode( $result ) );
		$this->assertFalse( $this->logContains( $detail ) );
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
