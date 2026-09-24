<?php
/**
 * Tests that the association DELETEs destroy nothing they were not asked to.
 *
 * SurplusAssociationDeleteTest proves the right rows go. This proves nothing
 * else does — the other half of the question, and the more dangerous one,
 * because over-deleting is silent.
 *
 * Which scope actually does the work was checked by deleting each one from the
 * query and seeing what failed, and it is not evenly distributed:
 *
 *   `key` is load-bearing on the item meta table. audio_url and video_url live
 *     alongside the item_type relations with a NULL item_type_id, so without it
 *     the NULL branch takes a sermon's media URLs with the orphans. Dropping
 *     `key` fails the two value-bearing-meta tests below.
 *
 *   `source_type_id` is load-bearing only in the NULL/0 branch. Elsewhere it is
 *     belt-and-braces: speakers and service types share the cp_source table and
 *     draw ids from one auto-increment sequence, so a speaker id can never
 *     collide with a service type id and matching on source_id alone is already
 *     unambiguous. Dropping it fails only the NULL-purge test.
 *
 *   `item_id` protects the `source_type` markers, not `key` — those rows carry a
 *     NULL item_id, so a per-item DELETE cannot reach them however it is keyed.
 *
 * The cross-type and marker tests below therefore pass against an under-scoped
 * query too. They are kept deliberately: they state the invariants the save path
 * is supposed to hold, which is what makes a future change that does break them
 * visible.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Integration;

use CP_Library\Models\Item as ItemModel;
use CP_Library\Models\ServiceType as ServiceTypeModel;
use CP_Library\Models\Speaker as SpeakerModel;

/**
 * @covers \CP_Library\Models\Item::remove_stale_sources
 * @covers \CP_Library\Models\Item::update_speakers
 * @covers \CP_Library\Models\Item::update_service_types
 * @covers \CP_Library\Models\Item::update_types
 */
class AssociationScopingTest extends TestCase {

	/**
	 * Service types are opt-in; these tests need them on.
	 */
	private function enable_service_types() {
		add_filter( 'cpl_enable_service_type', '__return_true' );
	}

	public function test_updating_speakers_leaves_service_type_rows_alone() {
		$this->enable_service_types();

		$item    = $this->make_item();
		$speaker = $this->make_speaker();
		$service = $this->make_service_type();

		$this->insert_source_row( $item->id, $speaker->id, SpeakerModel::get_type_id() );
		$this->insert_source_row( $item->id, $speaker->id, SpeakerModel::get_type_id() ); // duplicate
		$service_row = $this->insert_source_row( $item->id, $service->id, ServiceTypeModel::get_type_id() );

		$item->update_speakers( [ $speaker->id ] );

		$service_rows = $this->source_rows_of_type( $item->id, ServiceTypeModel::get_type_id() );

		$this->assertCount( 1, $service_rows, 'the service type association must survive a speaker save' );
		$this->assertEquals( $service_row, $service_rows[0]->id );
		$this->assertCount( 1, $this->source_rows_of_type( $item->id, SpeakerModel::get_type_id() ) );
	}

	public function test_updating_service_types_leaves_speaker_rows_alone() {
		$this->enable_service_types();

		$item    = $this->make_item();
		$speaker = $this->make_speaker();
		$service = $this->make_service_type();

		$speaker_row = $this->insert_source_row( $item->id, $speaker->id, SpeakerModel::get_type_id() );
		$this->insert_source_row( $item->id, $service->id, ServiceTypeModel::get_type_id() );
		$this->insert_source_row( $item->id, $service->id, ServiceTypeModel::get_type_id() ); // duplicate

		$item->update_service_types( [ $service->id ] );

		$speaker_rows = $this->source_rows_of_type( $item->id, SpeakerModel::get_type_id() );

		$this->assertCount( 1, $speaker_rows, 'the speaker must survive a service-type save' );
		$this->assertEquals( $speaker_row, $speaker_rows[0]->id );
	}

	public function test_clearing_service_types_entirely_leaves_speakers_intact() {
		$this->enable_service_types();

		$item    = $this->make_item();
		$speaker = $this->make_speaker();
		$service = $this->make_service_type();

		$this->insert_source_row( $item->id, $speaker->id, SpeakerModel::get_type_id() );
		$this->insert_source_row( $item->id, $service->id, ServiceTypeModel::get_type_id() );

		$item->update_service_types( [] );

		$this->assertCount( 0, $this->source_rows_of_type( $item->id, ServiceTypeModel::get_type_id() ) );
		$this->assertCount( 1, $this->source_rows_of_type( $item->id, SpeakerModel::get_type_id() ), 'clearing one type must not clear the other' );
	}

	public function test_null_source_purge_is_scoped_to_the_type_being_updated() {
		$this->enable_service_types();

		global $wpdb;

		$item    = $this->make_item();
		$speaker = $this->make_speaker();
		$table   = SpeakerModel::get_prop( 'meta_table_name' );

		$this->insert_source_row( $item->id, $speaker->id, SpeakerModel::get_type_id() );

		// One legacy orphan per type. Saving speakers may only purge the speaker one.
		foreach ( [ SpeakerModel::get_type_id(), ServiceTypeModel::get_type_id() ] as $type_id ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$table} (`key`, `source_id`, `source_type_id`, `item_id`) VALUES ( 'source_item', NULL, %d, %d )",
					$type_id,
					$item->id
				)
			);
		}

		$item->update_speakers( [ $speaker->id ] );

		$this->assertCount(
			1,
			$this->source_rows_of_type( $item->id, SpeakerModel::get_type_id() ),
			'the speaker orphan is purged, the real row stays'
		);
		$this->assertCount(
			1,
			$this->source_rows_of_type( $item->id, ServiceTypeModel::get_type_id() ),
			'the other type\'s orphan is not this save\'s business'
		);
	}

	public function test_updating_speakers_does_not_delete_the_speaker_type_marker() {
		global $wpdb;

		$item    = $this->make_item();
		$speaker = $this->make_speaker();
		$table   = SpeakerModel::get_prop( 'meta_table_name' );

		$this->insert_source_row( $item->id, $speaker->id, SpeakerModel::get_type_id() );

		// Written by Speaker::add_type() on insert. It is what makes this record show
		// up as a speaker at all — get_all_speakers() joins on it — so losing it
		// removes the speaker from every list and filter on the site.
		$marker = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE `key` = 'source_type' AND `source_id` = %d", $speaker->id )
		);
		$this->assertSame( 1, $marker, 'fixture: the speaker carries its type marker' );

		$item->update_speakers( [] );

		$this->assertSame(
			1,
			(int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE `key` = 'source_type' AND `source_id` = %d", $speaker->id )
			),
			'unassigning a speaker from a sermon must not unmake the speaker'
		);
	}

	public function test_updating_series_leaves_value_bearing_item_meta_alone() {
		global $wpdb;

		$item   = $this->make_item();
		$series = $this->make_series();
		$table  = ItemModel::get_prop( 'meta_table_name' );

		$wpdb->insert( $table, [ 'key' => 'item_type', 'item_type_id' => $series->id, 'item_id' => $item->id ] );
		$wpdb->insert( $table, [ 'key' => 'item_type', 'item_type_id' => $series->id, 'item_id' => $item->id ] );

		// These share the table and have a NULL item_type_id, so an under-scoped
		// DELETE — particularly the NULL branch — would take them.
		$wpdb->insert( $table, [ 'key' => 'audio_url', 'value' => 'https://example.org/a.mp3', 'item_id' => $item->id ] );
		$wpdb->insert( $table, [ 'key' => 'video_url', 'value' => 'https://example.org/v.mp4', 'item_id' => $item->id ] );

		$item->update_types( [ $series->id ] );

		$this->assertEquals(
			'https://example.org/a.mp3',
			$wpdb->get_var( $wpdb->prepare( "SELECT `value` FROM {$table} WHERE `key` = 'audio_url' AND `item_id` = %d", $item->id ) ),
			'audio_url survives a series save'
		);
		$this->assertEquals(
			'https://example.org/v.mp4',
			$wpdb->get_var( $wpdb->prepare( "SELECT `value` FROM {$table} WHERE `key` = 'video_url' AND `item_id` = %d", $item->id ) )
		);
		$this->assertCount( 1, $this->type_rows( $item->id ), 'and the duplicate still collapses' );
	}

	public function test_clearing_series_entirely_leaves_value_bearing_item_meta_alone() {
		global $wpdb;

		$item   = $this->make_item();
		$series = $this->make_series();
		$table  = ItemModel::get_prop( 'meta_table_name' );

		$wpdb->insert( $table, [ 'key' => 'item_type', 'item_type_id' => $series->id, 'item_id' => $item->id ] );
		$wpdb->insert( $table, [ 'key' => 'audio_url', 'value' => 'https://example.org/a.mp3', 'item_id' => $item->id ] );

		$item->update_types( [] );

		$this->assertCount( 0, $this->type_rows( $item->id ) );
		$this->assertEquals(
			'https://example.org/a.mp3',
			$wpdb->get_var( $wpdb->prepare( "SELECT `value` FROM {$table} WHERE `key` = 'audio_url' AND `item_id` = %d", $item->id ) )
		);
	}

	public function test_migration_does_not_delete_source_type_markers() {
		global $wpdb;

		$item    = $this->make_item();
		$speaker = $this->make_speaker();
		$table   = SpeakerModel::get_prop( 'meta_table_name' );

		$this->insert_source_row( $item->id, $speaker->id, SpeakerModel::get_type_id() );

		$migrations = get_option( 'cp_library_migrations', [] );
		unset( $migrations['1.6.3'] );
		update_option( 'cp_library_migrations', $migrations );

		cp_library_migrate_1_6_3( '1.6.2', '1.6.3' );

		$this->assertSame(
			1,
			(int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE `key` = 'source_type' AND `source_id` = %d", $speaker->id )
			),
			'the orphan cleanup is scoped by `key` and must leave type markers alone'
		);
		$this->assertCount( 1, $this->source_rows_of_type( $item->id, SpeakerModel::get_type_id() ) );
	}

	public function test_migration_leaves_valid_associations_across_many_items() {
		$speakers = [ $this->make_speaker( 'A' ), $this->make_speaker( 'B' ) ];
		$items    = [ $this->make_item( 'One' ), $this->make_item( 'Two' ), $this->make_item( 'Three' ) ];

		foreach ( $items as $item ) {
			foreach ( $speakers as $speaker ) {
				$this->insert_source_row( $item->id, $speaker->id, SpeakerModel::get_type_id() );
			}
		}

		// A single orphan among them must not take the valid rows with it.
		$this->insert_source_row( $items[1]->id, 999999, SpeakerModel::get_type_id() );

		$migrations = get_option( 'cp_library_migrations', [] );
		unset( $migrations['1.6.3'] );
		update_option( 'cp_library_migrations', $migrations );

		cp_library_migrate_1_6_3( '1.6.2', '1.6.3' );

		foreach ( $items as $item ) {
			$this->assertCount(
				2,
				$this->source_rows_of_type( $item->id, SpeakerModel::get_type_id() ),
				'every valid association survives the cleanup'
			);
		}
	}
}
