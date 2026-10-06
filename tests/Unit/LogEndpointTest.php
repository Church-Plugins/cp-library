<?php
/**
 * Tests for the item log endpoint.
 *
 * A non-numeric id, an unknown item, and an unknown action are turned away
 * before a log row is written. Watch time is the payload's watched seconds
 * added to the stored total and capped by max duration. Query values are
 * passed as prepare() arguments. The visitor address is REMOTE_ADDR, and a
 * forwarded header is not used in its place.
 *
 * @package CP_Library
 */

namespace {
	if ( ! class_exists( 'WP_REST_Controller' ) ) {
		class WP_REST_Controller {}
	}

	if ( ! class_exists( 'WP_REST_Request' ) ) {
		class WP_REST_Request {
			private $params;
			private $headers;

			public function __construct( $params = [], $headers = [] ) {
				$this->params  = $params;
				$this->headers = $headers;
			}

			public function get_param( $key ) {
				return $this->params[ $key ] ?? null;
			}

			public function get_header( $key ) {
				return $this->headers[ $key ] ?? null;
			}
		}
	}

	if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
		define( 'MINUTE_IN_SECONDS', 60 );
	}
}

namespace CP_Library\Tests\Unit {

	use Brain\Monkey;
	use Brain\Monkey\Functions;
	use CP_Library\API\Items;
	use PHPUnit\Framework\TestCase;
	use ReflectionClass;

	/**
	 * Records query-building calls so tests can read the template and its arguments.
	 */
	class RecordingWpdb {
		public $prefix    = 'wp_';
		public $prepared  = [];
		public $updated   = [];
		public $inserted  = [];
		public $insert_id = 0;
		public $rows      = [];

		public function prepare( $query, ...$args ) {
			$this->prepared[] = [ $query, $args ];
			return $query;
		}

		public function get_row( $query ) {
			return array_shift( $this->rows );
		}

		public function update( $table, $data, $where, $formats = null ) {
			$this->updated[] = [ $table, $data, $where ];
			return 1;
		}

		public function insert( $table, $data, $formats = null ) {
			$this->inserted[] = [ $table, $data ];
			return 1;
		}
	}

	/**
	 * @covers \CP_Library\API\Items::log
	 * @covers \CP_Library\API\Items::handle_view_duration
	 * @covers \CP_Library\API\Items::get_log_ip
	 * @covers \CP_Library\API\Items::is_log_rate_limited
	 */
	class LogEndpointTest extends TestCase {

		/** @var Items */
		private $api;

		/** @var RecordingWpdb */
		private $wpdb;

		private $server;

		protected function setUp(): void {
			parent::setUp();
			Monkey\setUp();

			$this->server = $_SERVER;
			$_SERVER['REMOTE_ADDR'] = '203.0.113.7';

			$this->wpdb        = new RecordingWpdb();
			$GLOBALS['wpdb']   = $this->wpdb;

			Functions\when( 'wp_cache_get' )->justReturn( false );
			Functions\when( 'wp_cache_add' )->justReturn( true );
			Functions\when( 'wp_cache_set' )->justReturn( true );
			Functions\when( 'wp_cache_delete' )->justReturn( true );
			Functions\when( 'apply_filters' )->returnArg( 2 );
			Functions\when( 'do_action' )->justReturn( null );

			$this->api = ( new ReflectionClass( Items::class ) )->newInstanceWithoutConstructor();
		}

		protected function tearDown(): void {
			$_SERVER = $this->server;
			unset( $GLOBALS['wpdb'] );
			Monkey\tearDown();
			parent::tearDown();
		}

		private function request( $params, $headers = [] ) {
			return new \WP_REST_Request( $params, $headers );
		}

		private function payload( $watched = 25, $max = 300 ) {
			return [ 'watchedSeconds' => $watched, 'maxDuration' => $max ];
		}

		/**
		 * @return array Decoded data written by the duration update.
		 */
		private function run_duration( $existing_seconds, $payload, $headers = [] ) {
			$this->wpdb->rows = [
				(object) [
					'id'   => 3,
					'data' => wp_json_encode( [
						'user_ip'        => '203.0.113.7',
						'watch_duration' => $existing_seconds,
					] ),
				],
				(object) [
					'id'          => 3,
					'object_type' => 'item',
					'object_id'   => 5,
					'action'      => 'view_duration',
					'data'        => '',
				],
			];

			$this->api->handle_view_duration( $this->request(
				[ 'item_id' => '5', 'action' => 'view_duration', 'payload' => $payload ],
				$headers
			) );

			$this->assertCount( 1, $this->wpdb->updated );

			return json_decode( $this->wpdb->updated[0][1]['data'], true );
		}

		public function test_query_values_are_prepare_arguments() {
			$stored = $this->run_duration( 40, $this->payload(), [ 'x-forwarded-for' => "1.2.3.4'x" ] );

			list( $query, $args ) = $this->wpdb->prepared[0];

			$this->assertStringContainsString( 'object_id = %d', $query );
			$this->assertStringContainsString( 'action = %s', $query );
			$this->assertStringContainsString( "JSON_UNQUOTE(JSON_EXTRACT(data, '$.user_ip')) = %s", $query );
			$this->assertStringNotContainsString( "'x", $query );
			$this->assertSame( [ 5, 'view_duration', '203.0.113.7' ], $args );
			$this->assertSame( '203.0.113.7', $stored['user_ip'] );
		}

		public function test_watch_duration_adds_watched_seconds_capped_at_max_duration() {
			$stored = $this->run_duration(
				40,
				$this->payload( 25, 300 ),
				[ 'x-forwarded-for' => '198.51.100.2, 10.0.0.1' ]
			);

			$this->assertSame( 65, $stored['watch_duration'] );
			$this->assertSame( '203.0.113.7', $stored['user_ip'], 'a forwarded address is not stored' );
		}

		public function test_watch_duration_is_capped_at_max_duration() {
			$stored = $this->run_duration( 280, $this->payload( 50, 300 ) );

			$this->assertSame( 300, $stored['watch_duration'] );
		}

		public function test_non_numeric_item_id_is_rejected() {
			$result = $this->api->log( $this->request( [ 'item_id' => "5'x", 'action' => 'play' ] ) );

			$this->assertArrayHasKey( 'error', $result );
			$this->assertSame( [], $this->wpdb->prepared );
			$this->assertSame( [], $this->wpdb->inserted );
		}

		public function test_unknown_action_is_rejected() {
			$result = $this->api->log( $this->request( [ 'item_id' => '5', 'action' => 'unknown_action' ] ) );

			$this->assertArrayHasKey( 'error', $result );
			$this->assertSame( [], $this->wpdb->prepared );
			$this->assertSame( [], $this->wpdb->inserted );
		}

		public function test_unknown_item_is_rejected() {
			$result = $this->api->log( $this->request( [ 'item_id' => '999', 'action' => 'play' ] ) );

			$this->assertArrayHasKey( 'error', $result );
			$this->assertSame( [], $this->wpdb->inserted );
			$this->assertSame( [], $this->wpdb->updated );
		}

		public function test_rate_limit_stops_writes_after_the_limit() {
			Functions\when( 'get_transient' )->justReturn( 2000 );
			Functions\expect( 'set_transient' )->never();

			$this->assertTrue( $this->api->is_log_rate_limited( 5 ) );
		}

		public function test_rate_limit_counts_writes_under_the_limit() {
			Functions\when( 'get_transient' )->justReturn( false );
			Functions\expect( 'set_transient' )->once()->with( \Mockery::type( 'string' ), 1, 60 );

			$this->assertFalse( $this->api->is_log_rate_limited( 5 ) );
		}

		public function test_rate_limit_filter_is_honored() {
			Functions\when( 'apply_filters' )->alias( function ( $hook, $value ) {
				return 'cpl_log_rate_limit' === $hook ? 2 : $value;
			} );
			Functions\when( 'get_transient' )->justReturn( 2 );
			Functions\expect( 'set_transient' )->never();

			$this->assertTrue( $this->api->is_log_rate_limited( 5 ) );
		}

		public function test_rate_limit_skips_the_log_write() {
			Functions\when( 'get_transient' )->justReturn( 2000 );
			Functions\expect( 'set_transient' )->never();

			$this->wpdb->rows = [
				(object) [ 'id' => 5, 'origin_id' => 9 ],
			];

			$result = $this->api->log( $this->request( [ 'item_id' => '5', 'action' => 'play' ] ) );

			$this->assertNull( $result );
			$this->assertSame( [], $this->wpdb->inserted );
		}

		public function test_invalid_ip_header_is_ignored() {
			$stored = $this->run_duration( 10, $this->payload( 5, 100 ), [ 'x-forwarded-for' => 'not-an-ip' ] );

			$this->assertSame( [ 5, 'view_duration', '203.0.113.7' ], $this->wpdb->prepared[0][1] );
			$this->assertSame( '203.0.113.7', $stored['user_ip'] );
			$this->assertStringNotContainsString( 'not-an-ip', $this->wpdb->prepared[0][0] );

			$_SERVER['REMOTE_ADDR'] = 'not-an-ip';

			$this->assertFalse( $this->api->get_log_ip() );
			$this->assertTrue( $this->api->is_log_rate_limited( 5 ) );
		}
	}
}
