<?php
/**
 * The filter options request used to wrap unexpected errors in a filter
 * exception, which sent the original text to the client. Those errors, and
 * a TypeError such as query_vars that is not an array, are passed through
 * as themselves so the client gets the generic message.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Library\Filters\AbstractFilterManager;
use CP_Library\Filters\ErrorHandler;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Concrete manager whose option lookup can throw on demand.
 */
class ThrowableFilterManager extends AbstractFilterManager {

	/** @var \Throwable|null */
	public $to_throw;

	public function __construct() {
		// Deliberately does not call parent::__construct().
	}

	/**
	 * @param ErrorHandler $handler
	 */
	public function prepare( ErrorHandler $handler ) {
		$this->post_type     = 'cpl_item';
		$this->error_handler = $handler;
		$this->facets        = array(
			'cpl_topic' => array(
				'label'      => 'Topic',
				'param'      => 'facet-cpl_topic',
				'query_var'  => 'cpl_topic',
				'type'       => 'taxonomy',
				'public'     => true,
				'taxonomy'   => 'cpl_topic',
				'post_types' => array( 'cpl_item' ),
			),
		);
	}

	protected function register_default_contexts() {
	}

	protected function register_default_facets() {
	}

	public function get_filter_options( $facet_id, $context = 'archive', $args = array() ) {
		if ( $this->to_throw ) {
			throw $this->to_throw;
		}

		return array();
	}
}

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @covers \CP_Library\Filters\AbstractFilterManager::ajax_get_filter_options
 */
class FilterOptionsThrowableTest extends TestCase {

	/** @var array|null */
	private $sent;

	/** @var ThrowableFilterManager */
	private $manager;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( '__' )->returnArg();
		Functions\when( '_sanitize_text_fields' )->returnArg();
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'wp_parse_args' )->alias(
			static function ( $args, $defaults = array() ) {
				if ( ! is_array( $args ) ) {
					$args = array();
				}

				return array_merge( $defaults, $args );
			}
		);

		$this->sent = null;
		$test       = $this;
		Functions\when( 'wp_send_json_error' )->alias(
			static function ( $data ) use ( $test ) {
				$test->sent = $data;
			}
		);
		Functions\when( 'wp_send_json_success' )->alias(
			static function () {
				throw new \RuntimeException( 'success response was not expected' );
			}
		);

		$handler = ( new ReflectionClass( ErrorHandler::class ) )->newInstanceWithoutConstructor();

		$this->manager = new ThrowableFilterManager();
		$this->manager->prepare( $handler );

		$_POST = array(
			'filter_type' => 'cpl_topic',
			'post_type'   => 'cpl_item',
		);
	}

	protected function tearDown(): void {
		$_POST = array();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_unexpected_exception_sends_the_generic_message() {
		$detail                  = 'raw connector detail token cpl-opt-4e91';
		$this->manager->to_throw = new \Exception( $detail );

		$this->manager->ajax_get_filter_options();

		$this->assertIsArray( $this->sent );
		$this->assertSame( 'An error occurred in the filter system.', $this->sent['message'] );
		$this->assertStringNotContainsString( $detail, wp_json_encode( $this->sent ) );
		$this->assertArrayNotHasKey( 'exception', $this->sent );
	}

	public function test_query_vars_string_sends_the_generic_message() {
		$_POST['query_vars'] = 'notarray';

		$this->manager->ajax_get_filter_options();

		$this->assertIsArray( $this->sent );
		$this->assertSame( 'An error occurred in the filter system.', $this->sent['message'] );
		$this->assertStringNotContainsString( 'notarray', wp_json_encode( $this->sent ) );
		$this->assertStringNotContainsString( 'TypeError', wp_json_encode( $this->sent ) );
		$this->assertArrayNotHasKey( 'exception', $this->sent );
	}
}
