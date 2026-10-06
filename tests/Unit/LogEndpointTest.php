<?php
/**
 * Tests for the item log endpoint.
 *
 * The route accepts a whole-number id for a published item and an action the
 * player sends. The item id is read from the route. Each view-duration request
 * adds one row, capped by the known media length or six hours, and stores a
 * hash of the viewer address. The rate limit counts writes inside the current
 * minute for REMOTE_ADDR.
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
			private $url_params;

			public function __construct( $params = [], $headers = [], $url_params = null ) {
				$this->params     = $params;
				$this->headers    = $headers;
				$this->url_params = null === $url_params ? $params : $url_params;
			}

			public function get_param( $key ) {
				return $this->params[ $key ] ?? null;
			}

			public function get_header( $key ) {
				return $this->headers[ $key ] ?? null;
			}

			public function get_url_params() {
				return $this->url_params;
			}
		}
	}

	if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
		define( 'MINUTE_IN_SECONDS', 60 );
	}

	if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
		define( 'HOUR_IN_SECONDS', 3600 );
	}
}

namespace CP_Library\Tests\Unit {

	use Brain\Monkey;
	use Brain\Monkey\Functions;
	use CP_Library\API\Items;
	use PHPUnit\Framework\TestCase;
	use ReflectionClass;

	/**
	 * Records query-building calls so tests can read inserted rows.
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
			if ( ! empty( $this->rows ) ) {
				return array_shift( $this->rows );
			}

			if ( $this->insert_id ) {
				return (object) [
					'id'          => $this->insert_id,
					'origin_id'   => 9,
					'object_type' => 'item',
					'object_id'   => 5,
					'action'      => 'view_duration',
					'data'        => '',
				];
			}

			return null;
		}

		public function get_results( $query ) {
			return [];
		}

		public function update( $table, $data, $where, $formats = null ) {
			$this->updated[] = [ $table, $data, $where ];
			return 1;
		}

		public function insert( $table, $data, $formats = null ) {
			$this->inserted[] = [ $table, $data ];
			$this->insert_id  = count( $this->inserted );
			return 1;
		}

		public function strip_invalid_text_for_column( $table, $column, $value ) {
			return $value;
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

			$this->wpdb      = new RecordingWpdb();
			$GLOBALS['wpdb'] = $this->wpdb;

			Functions\when( 'wp_cache_get' )->justReturn( false );
			Functions\when( 'wp_cache_add' )->justReturn( true );
			Functions\when( 'wp_cache_set' )->justReturn( true );
			Functions\when( 'wp_cache_delete' )->justReturn( true );
			Functions\when( 'wp_cache_incr' )->justReturn( false );
			Functions\when( 'wp_using_ext_object_cache' )->justReturn( false );
			Functions\when( 'apply_filters' )->returnArg( 2 );
			Functions\when( 'do_action' )->justReturn( null );
			Functions\when( 'get_post_status' )->justReturn( 'publish' );
			Functions\when( 'wp_parse_args' )->alias( function ( $args, $defaults = [] ) {
				return array_merge( $defaults, $args );
			} );
			Functions\when( 'wp_hash' )->alias( function ( $data ) {
				return hash_hmac( 'md5', (string) $data, 'cpl-test' );
			} );
			Functions\when( 'sanitize_text_field' )->returnArg( 1 );

			$this->api = ( new ReflectionClass( Items::class ) )->newInstanceWithoutConstructor();
		}

		protected function tearDown(): void {
			$_SERVER = $this->server;
			unset( $GLOBALS['wpdb'] );
			Monkey\tearDown();
			parent::tearDown();
		}

		private function request( $params, $headers = [], $url_params = null ) {
			return new \WP_REST_Request( $params, $headers, $url_params );
		}

		private function payload( $watched = 25, $max = 300 ) {
			return [ 'watchedSeconds' => $watched, 'maxDuration' => $max ];
		}

		private function hash_of( $ip ) {
			return hash_hmac( 'md5', $ip, 'cpl-test' );
		}

		/**
		 * @return array Decoded data from the inserted duration row.
		 */
		private function run_duration( $payload, $headers = [], $params = null ) {
			$params = null === $params ? [ 'item_id' => '5', 'action' => 'view_duration', 'payload' => $payload ] : $params;

			$this->api->handle_view_duration( $this->request( $params, $headers ) );

			$this->assertCount( 1, $this->wpdb->inserted );
			$this->assertSame( [], $this->wpdb->updated );

			return json_decode( $this->wpdb->inserted[0][1]['data'], true );
		}

		public function test_each_duration_flush_inserts_one_row_with_a_viewer_hash() {
			$stored = $this->run_duration(
				$this->payload( 25, 300 ),
				[ 'x-forwarded-for' => '198.51.100.2, 10.0.0.1' ]
			);

			$this->assertSame( 25, $stored['watch_duration'] );
			$this->assertSame( $this->hash_of( '198.51.100.2' ), $stored['user_ip'] );
			$this->assertSame( 32, strlen( $stored['user_ip'] ) );
			$this->assertNotSame( '198.51.100.2', $stored['user_ip'] );
			$this->assertStringNotContainsString( '198.51.100.2', $this->wpdb->inserted[0][1]['data'] );
		}

		public function test_a_second_flush_inserts_another_row() {
			$this->api->handle_view_duration( $this->request( [
				'item_id' => '5',
				'action'  => 'view_duration',
				'payload' => $this->payload( 10, 300 ),
			] ) );
			$this->api->handle_view_duration( $this->request( [
				'item_id' => '5',
				'action'  => 'view_duration',
				'payload' => $this->payload( 15, 300 ),
			] ) );

			$this->assertCount( 2, $this->wpdb->inserted );
			$this->assertSame( [], $this->wpdb->updated );

			$first  = json_decode( $this->wpdb->inserted[0][1]['data'], true );
			$second = json_decode( $this->wpdb->inserted[1][1]['data'], true );

			$this->assertSame( 10, $first['watch_duration'] );
			$this->assertSame( 15, $second['watch_duration'] );
		}

		public function test_valid_forwarded_address_is_hashed() {
			$stored = $this->run_duration( $this->payload( 5, 100 ), [
				'x-forwarded-for' => ' 2001:db8::1 , 203.0.113.8',
			] );

			$this->assertSame( $this->hash_of( '2001:db8::1' ), $stored['user_ip'] );
			$this->assertStringNotContainsString( '2001:db8::1', $this->wpdb->inserted[0][1]['data'] );
			$this->assertStringNotContainsString( '203.0.113.8', $this->wpdb->inserted[0][1]['data'] );
		}

		public function test_watch_duration_is_capped_at_the_client_total_when_it_is_smaller() {
			$stored = $this->run_duration( $this->payload( 50, 30 ) );

			$this->assertSame( 30, $stored['watch_duration'] );
		}

		public function test_watch_duration_is_capped_at_six_hours_without_a_known_length() {
			$stored = $this->run_duration( $this->payload( 100000, 100000 ) );

			$this->assertSame( 6 * HOUR_IN_SECONDS, $stored['watch_duration'] );
		}

		public function test_watch_duration_is_capped_at_the_known_media_length() {
			$this->wpdb->rows = [
				(object) [ 'id' => 5, 'origin_id' => 9 ],
			];

			Functions\when( 'get_post_meta' )->alias( function ( $post_id, $key ) {
				return 'audio_url_id' === $key ? 44 : '';
			} );
			Functions\when( 'wp_get_attachment_metadata' )->justReturn( [ 'length_formatted' => '1:30' ] );

			$stored = $this->run_duration( $this->payload( 500, 500 ) );

			$this->assertSame( 90, $stored['watch_duration'] );
		}

		public function test_max_watch_seconds_filter_is_honored() {
			Functions\when( 'apply_filters' )->alias( function ( $hook, $value ) {
				return 'cpl_log_max_watch_seconds' === $hook ? 120 : $value;
			} );

			$stored = $this->run_duration( $this->payload( 500, 500 ) );

			$this->assertSame( 120, $stored['watch_duration'] );
		}

		public function test_missing_viewer_address_stores_null() {
			Functions\when( 'apply_filters' )->alias( function ( $hook, $value ) {
				return 'cpl_log_viewer_ip' === $hook ? 'abc' : $value;
			} );

			$stored = $this->run_duration( $this->payload( 5, 100 ) );

			$this->assertNull( $stored['user_ip'] );
			$this->assertSame( 5, $stored['watch_duration'] );
		}

		/**
		 * @dataProvider non_numeric_item_ids
		 */
		public function test_non_numeric_item_id_is_rejected( $item_id ) {
			$result = $this->api->log( $this->request( [ 'item_id' => $item_id, 'action' => 'play' ] ) );

			$this->assertArrayHasKey( 'error', $result );
			$this->assertSame( [], $this->wpdb->prepared );
			$this->assertSame( [], $this->wpdb->inserted );
		}

		public function non_numeric_item_ids() {
			return [
				'letters' => [ 'abc' ],
				'mixed'   => [ '12abc' ],
			];
		}

		public function test_body_item_id_cannot_override_the_route() {
			Functions\when( 'get_transient' )->justReturn( false );
			Functions\when( 'set_transient' )->justReturn( true );

			$this->wpdb->rows = [
				(object) [ 'id' => 5, 'origin_id' => 9 ],
			];

			$result = $this->api->log( $this->request(
				[ 'item_id' => '999999', 'action' => 'play' ],
				[],
				[ 'item_id' => '5' ]
			) );

			$this->assertIsObject( $result );
			$this->assertSame( 5, $this->wpdb->inserted[0][1]['object_id'] );
		}

		public function test_route_item_id_must_still_be_numeric_when_the_body_is_numeric() {
			$result = $this->api->log( $this->request(
				[ 'item_id' => '5', 'action' => 'play' ],
				[],
				[ 'item_id' => 'abc' ]
			) );

			$this->assertArrayHasKey( 'error', $result );
			$this->assertSame( [], $this->wpdb->inserted );
		}

		public function test_unknown_action_is_rejected() {
			$result = $this->api->log( $this->request( [ 'item_id' => '5', 'action' => 'unknown_action' ] ) );

			$this->assertArrayHasKey( 'error', $result );
			$this->assertSame( [], $this->wpdb->prepared );
			$this->assertSame( [], $this->wpdb->inserted );
		}

		public function test_unknown_item_is_rejected() {
			$result = $this->api->log( $this->request( [ 'item_id' => '999999', 'action' => 'play' ] ) );

			$this->assertArrayHasKey( 'error', $result );
			$this->assertSame( [], $this->wpdb->inserted );
			$this->assertSame( [], $this->wpdb->updated );
		}

		public function test_unpublished_item_is_rejected() {
			$this->wpdb->rows = [
				(object) [ 'id' => 5, 'origin_id' => 9 ],
			];
			Functions\when( 'get_post_status' )->justReturn( 'draft' );

			$result = $this->api->log( $this->request( [ 'item_id' => '5', 'action' => 'play' ] ) );

			$this->assertArrayHasKey( 'error', $result );
			$this->assertSame( [], $this->wpdb->inserted );
		}

		public function test_rate_limit_stops_writes_after_the_limit() {
			Functions\when( 'get_transient' )->justReturn( 10000 );
			Functions\expect( 'set_transient' )->never();

			$this->assertTrue( $this->api->is_log_rate_limited( 5 ) );
		}

		public function test_rate_limit_counts_writes_under_the_limit() {
			Functions\when( 'get_transient' )->justReturn( false );
			Functions\expect( 'set_transient' )->once()->with(
				\Mockery::on( function ( $key ) {
					$window   = (int) floor( time() / MINUTE_IN_SECONDS );
					$expected = 'cpl_log_' . md5( '203.0.113.7|5|' . $window );
					return $key === $expected;
				} ),
				1,
				\Mockery::on( function ( $ttl ) {
					return is_int( $ttl ) && $ttl >= 1 && $ttl <= 60;
				} )
			);

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

		public function test_rate_limit_key_filter_is_honored() {
			Functions\when( 'apply_filters' )->alias( function ( $hook, $value ) {
				return 'cpl_log_rate_limit_key' === $hook ? 'custom_bucket' : $value;
			} );
			Functions\when( 'get_transient' )->justReturn( false );
			Functions\expect( 'set_transient' )->once()->with( 'custom_bucket', 1, \Mockery::type( 'int' ) );

			$this->assertFalse( $this->api->is_log_rate_limited( 5 ) );
		}

		public function test_rate_limit_resets_on_the_next_window() {
			$store = [];
			$ttls  = [];

			Functions\when( 'apply_filters' )->alias( function ( $hook, $value ) {
				return 'cpl_log_rate_limit' === $hook ? 1 : $value;
			} );
			Functions\when( 'get_transient' )->alias( function ( $key ) use ( &$store ) {
				return array_key_exists( $key, $store ) ? $store[ $key ] : false;
			} );
			Functions\when( 'set_transient' )->alias( function ( $key, $value, $ttl ) use ( &$store, &$ttls ) {
				$store[ $key ] = $value;
				$ttls[ $key ]  = $ttl;
				return true;
			} );

			$now = 1700000045;

			$this->assertFalse( $this->api->is_log_rate_limited( 5, $now ) );
			$this->assertTrue( $this->api->is_log_rate_limited( 5, $now + 10 ) );
			$this->assertFalse( $this->api->is_log_rate_limited( 5, $now + 60 ) );

			$first  = 'cpl_log_' . md5( '203.0.113.7|5|' . (int) floor( $now / 60 ) );
			$second = 'cpl_log_' . md5( '203.0.113.7|5|' . (int) floor( ( $now + 60 ) / 60 ) );

			$this->assertNotSame( $first, $second );
			$this->assertSame( [ $first => 1, $second => 1 ], $store );
			$this->assertSame( 55, $ttls[ $first ] );
			$this->assertSame( 55, $ttls[ $second ] );
		}

		public function test_object_cache_rate_limit_uses_a_fixed_window() {
			$cache = [];

			Functions\when( 'wp_using_ext_object_cache' )->justReturn( true );
			Functions\when( 'apply_filters' )->alias( function ( $hook, $value ) {
				return 'cpl_log_rate_limit' === $hook ? 1 : $value;
			} );
			Functions\when( 'wp_cache_incr' )->alias( function ( $key, $offset = 1, $group = '' ) use ( &$cache ) {
				$id = $group . '|' . $key;
				if ( ! isset( $cache[ $id ] ) ) {
					return false;
				}
				$cache[ $id ] += $offset;
				return $cache[ $id ];
			} );
			Functions\when( 'wp_cache_add' )->alias( function ( $key, $value, $group = '', $ttl = 0 ) use ( &$cache ) {
				$id = $group . '|' . $key;
				if ( isset( $cache[ $id ] ) ) {
					return false;
				}
				$cache[ $id ] = $value;
				return true;
			} );
			Functions\expect( 'set_transient' )->never();

			$now = 1700000045;

			$this->assertFalse( $this->api->is_log_rate_limited( 5, $now ) );
			$this->assertTrue( $this->api->is_log_rate_limited( 5, $now + 5 ) );
			$this->assertFalse( $this->api->is_log_rate_limited( 5, $now + 60 ) );
			$this->assertCount( 2, $cache );
		}

		public function test_rate_limit_skips_the_log_write() {
			Functions\when( 'get_transient' )->justReturn( 10000 );
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
			$stored = $this->run_duration( $this->payload( 5, 100 ), [ 'x-forwarded-for' => $header ] );

			$this->assertSame( $this->hash_of( '203.0.113.7' ), $stored['user_ip'] );
			$this->assertStringNotContainsString( '203.0.113.7', $this->wpdb->inserted[0][1]['data'] );
			if ( '' !== $header ) {
				$this->assertStringNotContainsString( $header, $this->wpdb->inserted[0][1]['data'] );
			}
		}

		public function invalid_forwarded_addresses() {
			return [
				'not an ip' => [ 'not-an-ip' ],
				'letters'   => [ 'abc' ],
				'mixed'     => [ '12abc' ],
				'empty'     => [ '' ],
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
				return array_key_exists( $key, $store ) ? $store[ $key ] : false;
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

			$window   = (int) floor( time() / MINUTE_IN_SECONDS );
			$expected = 'cpl_log_' . md5( '203.0.113.7|5|' . $window );

			$this->assertSame( [ $expected => 10 ], $store );
		}
	}
}
