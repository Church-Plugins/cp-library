<?php
/**
 * Base case for integration tests, with fixtures for the custom-table models.
 *
 * WP_UnitTestCase wraps each test in a transaction and rolls it back. That
 * covers the cpl_* tables too — they are InnoDB on the same connection — so
 * fixtures here need no manual teardown. What it does NOT cover is DDL, which
 * commits implicitly: never create or alter a table from inside a test.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Integration;

use CP_Library\Models\Item as ItemModel;
use CP_Library\Models\ItemType as ItemTypeModel;
use CP_Library\Models\Speaker as SpeakerModel;
use WP_UnitTestCase;

abstract class TestCase extends WP_UnitTestCase {

	/**
	 * Create a sermon backed by a real post.
	 *
	 * @param string $title
	 * @return ItemModel
	 */
	protected function make_item( $title = 'Test Sermon' ) {
		$post_id = self::factory()->post->create(
			[
				'post_type'   => cp_library()->setup->post_types->item->post_type,
				'post_title'  => $title,
				'post_status' => 'publish',
			]
		);

		return ItemModel::get_instance_from_origin( $post_id );
	}

	/**
	 * Create a speaker backed by a real post.
	 *
	 * @param string $title
	 * @return SpeakerModel
	 */
	protected function make_speaker( $title = 'Test Speaker' ) {
		$post_id = self::factory()->post->create(
			[
				'post_type'   => cp_library()->setup->post_types->speaker->post_type,
				'post_title'  => $title,
				'post_status' => 'publish',
			]
		);

		return SpeakerModel::get_instance_from_origin( $post_id );
	}

	/**
	 * Create a series backed by a real post.
	 *
	 * @param string $title
	 * @return ItemTypeModel
	 */
	protected function make_series( $title = 'Test Series' ) {
		$post_id = self::factory()->post->create(
			[
				'post_type'   => cp_library()->setup->post_types->item_type->post_type,
				'post_title'  => $title,
				'post_status' => 'publish',
			]
		);

		return ItemTypeModel::get_instance_from_origin( $post_id );
	}

	/**
	 * Insert a raw speaker association row, bypassing the model.
	 *
	 * This is how the corrupt state under test is reproduced: duplicate rows for
	 * one speaker are exactly what the models refuse to create, so they have to
	 * be written directly.
	 *
	 * @param int $item_id
	 * @param int $speaker_id
	 * @param int $order
	 * @return int Inserted row id.
	 */
	protected function insert_speaker_row( $item_id, $speaker_id, $order = 0 ) {
		global $wpdb;

		$wpdb->insert(
			SpeakerModel::get_prop( 'meta_table_name' ),
			[
				'key'            => 'source_item',
				'source_id'      => $speaker_id,
				'source_type_id' => SpeakerModel::get_type_id(),
				'item_id'        => $item_id,
				'order'          => $order,
			]
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Every speaker association row for an item, oldest first.
	 *
	 * Returns rows rather than ids so tests can assert on which row survived, not
	 * merely how many did.
	 *
	 * @param int $item_id
	 * @return array
	 */
	protected function speaker_rows( $item_id ) {
		global $wpdb;

		$table = SpeakerModel::get_prop( 'meta_table_name' );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT `id`, `source_id`, `order` FROM {$table} WHERE `key` = 'source_item' AND `item_id` = %d ORDER BY `id` ASC",
				$item_id
			)
		);
	}

	/**
	 * Every series association row for an item, oldest first.
	 *
	 * @param int $item_id
	 * @return array
	 */
	protected function type_rows( $item_id ) {
		global $wpdb;

		$table = ItemModel::get_prop( 'meta_table_name' );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT `id`, `item_type_id`, `order` FROM {$table} WHERE `key` = 'item_type' AND `item_id` = %d ORDER BY `id` ASC",
				$item_id
			)
		);
	}
}
