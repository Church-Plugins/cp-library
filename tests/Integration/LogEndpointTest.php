<?php
/**
 * Tests the item log route against the log table.
 *
 * A published variation is recorded under its own item id. Each duration
 * flush adds a row. The stored viewer value is a hash, and the average of
 * those rows is the average of the flushed durations.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Integration;

use CP_Library\Admin\Analytics\Init as Analytics;
use CP_Library\API\Items;
use CP_Library\Models\Item as ItemModel;

/**
 * @covers \CP_Library\API\Items::log
 * @covers \CP_Library\API\Items::handle_view_duration
 */
class LogEndpointTest extends TestCase {

	/** @var string */
	private $remote;

	public function set_up() {
		parent::set_up();
		$this->remote = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
	}

	public function tear_down() {
		$_SERVER['REMOTE_ADDR'] = $this->remote;
		parent::tear_down();
	}

	public function test_a_variation_log_is_recorded_on_the_variation_item() {
		$parent    = $this->make_item( 'Parent sermon' );
		$child_id  = self::factory()->post->create(
			[
				'post_type'   => cp_library()->setup->post_types->item->post_type,
				'post_title'  => 'Morning variation',
				'post_status' => 'publish',
				'post_parent' => $parent->origin_id,
			]
		);
		$variation = ItemModel::get_instance_from_origin( $child_id );
		$service   = $this->make_service_type();

		$api = new Items();
		$api->log( $this->duration_request( $variation->id, 12, 90, '198.51.100.20' ) );

		$rows = $this->log_rows( $variation->id );

		$this->assertCount( 1, $rows );
		$this->assertSame( (string) $variation->id, (string) $rows[0]->object_id );
		$this->assertSame( [], $this->log_rows( $parent->id ) );

		$rejected = $api->log( $this->duration_request( $service->origin_id, 12, 90 ) );
		$this->assertIsArray( $rejected );
		$this->assertArrayHasKey( 'error', $rejected );
		$this->assertSame( [], $this->log_rows( $service->origin_id ) );
	}

	public function test_unpublished_item_is_rejected() {
		$post_id = self::factory()->post->create(
			[
				'post_type'   => cp_library()->setup->post_types->item->post_type,
				'post_title'  => 'Draft sermon',
				'post_status' => 'draft',
			]
		);
		$item = ItemModel::get_instance_from_origin( $post_id );

		$result = ( new Items() )->log( $this->duration_request( $item->id, 8, 40 ) );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( [], $this->log_rows( $item->id ) );
	}

	public function test_each_flush_is_its_own_row_and_the_viewer_is_hashed() {
		$item = $this->make_item();
		$api  = new Items();

		$api->log( $this->duration_request( $item->id, 10, 400, '198.51.100.20' ) );
		$api->log( $this->duration_request( $item->id, 30, 400, '198.51.100.20' ) );

		$rows = $this->log_rows( $item->id );

		$this->assertCount( 2, $rows );

		$first  = json_decode( $rows[0]->data, true );
		$second = json_decode( $rows[1]->data, true );
		$hash   = wp_hash( '198.51.100.20' );

		$this->assertSame( 10, (int) $first['watch_duration'] );
		$this->assertSame( 30, (int) $second['watch_duration'] );
		$this->assertSame( $hash, $first['user_ip'] );
		$this->assertSame( $hash, $second['user_ip'] );
		$this->assertSame( 32, strlen( $hash ) );
		$this->assertStringNotContainsString( '198.51.100.20', $rows[0]->data );

		global $wpdb;
		$average = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(JSON_EXTRACT(data, '$.watch_duration')) FROM {$wpdb->prefix}cp_log WHERE action = %s AND object_id = %d",
				'view_duration',
				$item->id
			)
		);

		$this->assertEquals( 20, (float) $average );

		$analytics = Analytics::get_instance();
		$listed    = $analytics->get_analytics_since( '1990-01-01 00:00:00', 0 );
		$match     = null;

		foreach ( $listed as $row ) {
			if ( (int) $row->id === (int) $item->id ) {
				$match = $row;
			}
		}

		$this->assertNotNull( $match );
		$this->assertEquals( 20, (float) $match->view_duration );
		$this->assertEquals( 20, (float) $analytics->get_average_watch_time_since( '1990-01-01 00:00:00' ) );
	}

	public function test_watched_seconds_are_capped_at_the_known_media_length() {
		$item          = $this->make_item();
		$attachment_id = wp_insert_attachment(
			[
				'post_mime_type' => 'audio/mpeg',
				'post_title'     => 'audio',
				'post_status'    => 'inherit',
			],
			'sermon.mp3',
			$item->origin_id
		);
		wp_update_attachment_metadata( $attachment_id, [ 'length_formatted' => '1:00' ] );
		update_post_meta( $item->origin_id, 'audio_url_id', $attachment_id );

		( new Items() )->log( $this->duration_request( $item->id, 5000, 5000 ) );

		$rows = $this->log_rows( $item->id );
		$data = json_decode( $rows[0]->data, true );

		$this->assertSame( 60, (int) $data['watch_duration'] );
	}

	public function test_watched_seconds_fall_back_to_six_hours() {
		$item = $this->make_item();

		( new Items() )->log( $this->duration_request( $item->id, 100000, 100000 ) );

		$rows = $this->log_rows( $item->id );
		$data = json_decode( $rows[0]->data, true );

		$this->assertSame( 6 * HOUR_IN_SECONDS, (int) $data['watch_duration'] );
	}

	public function test_page_count_returns_a_number() {
		$this->make_item();

		$pages = Analytics::get_instance()->get_num_pages();

		$this->assertGreaterThan( 0, $pages );
	}

	/**
	 * @param int         $item_id Item id.
	 * @param int         $watched Watched seconds.
	 * @param int         $max     Client total.
	 * @param string|null $forwarded Forwarded address.
	 * @return \WP_REST_Request
	 */
	private function duration_request( $item_id, $watched, $max, $forwarded = null ) {
		$request = new \WP_REST_Request( 'POST', '/cpl/v1/items/' . $item_id . '/log' );
		$request->set_body_params(
			[
				'item_id' => '999999',
				'action'  => 'view_duration',
				'payload' => [
					'watchedSeconds' => $watched,
					'maxDuration'    => $max,
				],
			]
		);
		$request->set_url_params( [ 'item_id' => (string) $item_id ] );

		if ( null !== $forwarded ) {
			$request->set_header( 'X-Forwarded-For', $forwarded );
		}

		return $request;
	}

	/**
	 * @param int $item_id Item id.
	 * @return array
	 */
	private function log_rows( $item_id ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT object_id, action, data FROM {$wpdb->prefix}cp_log WHERE object_id = %d AND action = %s ORDER BY id ASC",
				$item_id,
				'view_duration'
			)
		);
	}
}
