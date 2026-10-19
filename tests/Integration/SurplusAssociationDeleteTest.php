<?php
/**
 * Tests the SQL that removes surplus speaker / series association rows.
 *
 * DiffAssociationsTest already proves the reconcile decides to delete the right
 * *number* of rows. This proves the DELETE removes the right *rows* — which no
 * unit test can reach, because it lives entirely in
 *
 *   DELETE ... WHERE ... AND `source_id` = %d ORDER BY `id` DESC LIMIT %d
 *
 * Two things have to hold. Deleting by value alone (the original bug) wiped
 * every row for a speaker, including the one the save meant to keep, so the
 * speaker silently vanished from the sermon once the object cache was evicted.
 * And because duplicates can carry different `order` values, the row that
 * survives has to be the oldest one — the original — not an arbitrary one.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Integration;

use CP_Library\Models\Speaker as SpeakerModel;

/**
 * @covers \CP_Library\Models\Item::remove_stale_sources
 * @covers \CP_Library\Models\Item::update_speakers
 * @covers \CP_Library\Models\Item::update_types
 */
class SurplusAssociationDeleteTest extends TestCase {

	public function test_resaving_a_speaker_with_a_duplicate_row_keeps_the_speaker() {
		$item    = $this->make_item();
		$speaker = $this->make_speaker();

		// The corrupt state the 1.6.3 migration cleans up: two rows, one speaker.
		$this->insert_speaker_row( $item->id, $speaker->id );
		$this->insert_speaker_row( $item->id, $speaker->id );

		$this->assertCount( 2, $this->speaker_rows( $item->id ), 'fixture: two rows to start' );

		// The user re-saves the sermon with the speaker still selected.
		$item->update_speakers( [ $speaker->id ] );

		$rows = $this->speaker_rows( $item->id );

		$this->assertCount( 1, $rows, 'the duplicate goes, the association stays' );
		$this->assertEquals( $speaker->id, $rows[0]->source_id );

		// And it must still be there on a fresh read, not just in the cached model
		// the save left behind — that cache is what masked the original bug.
		wp_cache_flush();
		$reloaded = \CP_Library\Models\Item::get_instance( $item->id );

		$this->assertEqualsCanonicalizing(
			[ $speaker->id ],
			array_map( 'absint', $reloaded->get_speakers() ),
			'the speaker must survive a cache eviction'
		);
	}

	public function test_the_oldest_row_survives_so_its_order_is_kept() {
		$item    = $this->make_item();
		$speaker = $this->make_speaker();

		$original  = $this->insert_speaker_row( $item->id, $speaker->id, 3 );
		$duplicate = $this->insert_speaker_row( $item->id, $speaker->id, 99 );

		$item->update_speakers( [ $speaker->id ] );

		$rows = $this->speaker_rows( $item->id );

		$this->assertCount( 1, $rows );
		$this->assertEquals( $original, $rows[0]->id, 'ORDER BY id DESC must delete the newer row' );
		$this->assertEquals( 3, $rows[0]->order, 'the original order is preserved' );
		$this->assertNotEquals( $duplicate, $rows[0]->id );
	}

	public function test_triplicate_rows_collapse_to_one() {
		$item    = $this->make_item();
		$speaker = $this->make_speaker();

		$original = $this->insert_speaker_row( $item->id, $speaker->id );
		$this->insert_speaker_row( $item->id, $speaker->id );
		$this->insert_speaker_row( $item->id, $speaker->id );

		$item->update_speakers( [ $speaker->id ] );

		$rows = $this->speaker_rows( $item->id );

		$this->assertCount( 1, $rows );
		$this->assertEquals( $original, $rows[0]->id );
	}

	public function test_deselecting_a_duplicated_speaker_removes_every_row() {
		$item    = $this->make_item();
		$speaker = $this->make_speaker();

		$this->insert_speaker_row( $item->id, $speaker->id );
		$this->insert_speaker_row( $item->id, $speaker->id );

		$item->update_speakers( [] );

		$this->assertCount( 0, $this->speaker_rows( $item->id ), 'nothing is kept, so both rows go' );
	}

	public function test_other_speakers_on_the_same_sermon_are_untouched() {
		$item  = $this->make_item();
		$kept  = $this->make_speaker( 'Kept Speaker' );
		$other = $this->make_speaker( 'Other Speaker' );

		$this->insert_speaker_row( $item->id, $kept->id );
		$this->insert_speaker_row( $item->id, $kept->id );
		$other_row = $this->insert_speaker_row( $item->id, $other->id );

		$item->update_speakers( [ $kept->id, $other->id ] );

		$rows = $this->speaker_rows( $item->id );
		$ids  = array_map( 'absint', wp_list_pluck( $rows, 'source_id' ) );

		$this->assertCount( 2, $rows, 'one row each' );
		$this->assertEqualsCanonicalizing( [ $kept->id, $other->id ], $ids );
		$this->assertContains( $other_row, array_map( 'absint', wp_list_pluck( $rows, 'id' ) ), 'the untouched row is the same row' );
	}

	public function test_other_sermons_sharing_the_speaker_are_untouched() {
		$speaker = $this->make_speaker();
		$mine    = $this->make_item( 'Mine' );
		$theirs  = $this->make_item( 'Theirs' );

		$this->insert_speaker_row( $mine->id, $speaker->id );
		$this->insert_speaker_row( $mine->id, $speaker->id );
		$this->insert_speaker_row( $theirs->id, $speaker->id );

		$mine->update_speakers( [ $speaker->id ] );

		$this->assertCount( 1, $this->speaker_rows( $mine->id ) );
		$this->assertCount( 1, $this->speaker_rows( $theirs->id ), 'the DELETE is scoped to one item' );
	}

	public function test_legacy_null_source_rows_are_purged() {
		$item    = $this->make_item();
		$speaker = $this->make_speaker();

		$this->insert_speaker_row( $item->id, $speaker->id );

		// The orphan that renders as a stray ", Speaker Name" in the admin.
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO ' . SpeakerModel::get_prop( 'meta_table_name' ) . " (`key`, `source_id`, `source_type_id`, `item_id`) VALUES ( 'source_item', NULL, %d, %d )",
				SpeakerModel::get_type_id(),
				$item->id
			)
		);

		$this->assertCount( 2, $this->speaker_rows( $item->id ), 'fixture: one real row, one orphan' );

		$item->update_speakers( [ $speaker->id ] );

		$rows = $this->speaker_rows( $item->id );

		$this->assertCount( 1, $rows, 'a prepared %d never matches NULL — this needs the IS NULL branch' );
		$this->assertEquals( $speaker->id, $rows[0]->source_id );
	}

	public function test_series_duplicates_collapse_the_same_way() {
		$item   = $this->make_item();
		$series = $this->make_series();

		global $wpdb;
		$table = \CP_Library\Models\Item::get_prop( 'meta_table_name' );

		foreach ( [ 5, 42 ] as $order ) {
			$wpdb->insert(
				$table,
				[
					'key'          => 'item_type',
					'item_type_id' => $series->id,
					'item_id'      => $item->id,
					'order'        => $order,
				]
			);
		}

		$item->update_types( [ $series->id ] );

		$rows = $this->type_rows( $item->id );

		$this->assertCount( 1, $rows, 'the series survives its duplicate' );
		$this->assertEquals( $series->id, $rows[0]->item_type_id );
		$this->assertEquals( 5, $rows[0]->order, 'the original order is preserved' );
	}
}
