<?php
/**
 * Tests for the item log endpoint.
 *
 * A non-numeric id, an unknown item, and an unknown action are turned away
 * before a log row is written. Watch time is the payload's watched seconds
 * added to the stored total and capped by max duration. Query values are
 * passed as prepare() arguments. The rate limit uses REMOTE_ADDR. A
 * view-duration row uses the first valid forwarded address, or REMOTE_ADDR
 * when that value is not an IP.
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
	 * @covers \CP_Library\API\Items::get_log_viewer_ip
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
			$this->assertSame( '198.51.100.2', $stored['user_ip'] );
			$this->assertSame( [ 5, 'view_duration', '198.51.100.2' ], $this->wpdb->prepared[0][1] );
		}

		public function test_valid_forwarded_address_is_the_viewer_key() {
			$stored = $this->run_duration( 10, $this->payload( 5, 100 ), [
				'x-forwarded-for' => ' 2001:db8::1 , 203.0.113.8',
			] );

			$this->assertSame( '2001:db8::1', $stored['user_ip'] );
			$this->assertSame( [ 5, 'view_duration', '2001:db8::1' ], $this->wpdb->prepared[0][1] );
			$this->assertStringNotContainsString( '203.0.113.8', $this->wpdb->prepared[0][0] );
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

		/**
		 * @dataProvider invalid_forwarded_addresses
		 */
		public function test_invalid_forwarded_address_falls_back_to_remote_addr( $header ) {
			$stored = $this->run_duration( 10, $this->payload( 5, 100 ), [ 'x-forwarded-for' => $header ] );

			list( $query, $args ) = $this->wpdb->prepared[0];

			$this->assertSame( [ 5, 'view_duration', '203.0.113.7' ], $args );
			$this->assertSame( '203.0.113.7', $stored['user_ip'] );
			if ( '' !== $header ) {
				$this->assertStringNotContainsString( $header, $query );
				$this->assertNotContains( $header, $args );
			}
		}

		public function invalid_forwarded_addresses() {
			return [
				'extra text' => [ "1.2.3.4' OR 1=1--" ],
				'not an ip'  => [ 'not-an-ip' ],
				'empty'      => [ '' ],
			];
		}

		public function test_invalid_remote_addr_blocks_the_rate_limit() {
			$_SERVER['REMOTE_ADDR'] = 'not-an-ip';

			$this->assertFalse( $this->api->get_log_ip() );
			$this->assertTrue( $this->api->is_log_rate_limited( 5 ) );
		}

		public function test_changing_forwarded_address_keeps_the_same_rate_limit_key() {
			$store = [];

			Functions\when( 'get_transient' )->alias( function ( $key ) use ( &$store ) {
				return $store[ $key ] ?? false;
			} );
			Functions\when( 'set_transient' )->alias( function ( $key, $value ) use ( &$store ) {
				$store[ $key ] = $value;
				return true;
			} );

			$this->wpdb->rows = array_fill( 0, 10, (object) [ 'id' => 5, 'origin_id' => 9 ] );

			for ( $i = 1; $i <= 10; $i++ ) {
				$this->api->log( $this->request(
					[ 'item_id' => '5', 'action' => 'view_duration', 'payload' => null ],
					[ 'x-forwarded-for' => '198.51.100.' . $i ]
				) );
			}

			$expected = 'cpl_log_' . md5( '203.0.113.7|5' );

			$this->assertSame( [ $expected => 10 ], $store );
		}
	}
}
