<?php
/**
 * Tests the 1.6.3 upgrade migration.
 *
 * This runs once on every existing install, unattended, and issues unscoped
 * DELETEs across two tables — so the cost of it being wrong is every site at
 * once. It has to remove exactly the orphans (NULL, 0, or pointing at a record
 * that no longer exists) and nothing else.
 *
 * It also covers the cache half of the fix. The migration used to end in a bare
 * wp_cache_flush(), which on a site with a persistent object cache evicted
 * core's alloptions/user/term caches and every other plugin's data — and on a
 * shared backend, every other site on the network. The replacement invalidates
 * only the items whose rows actually changed, which the last test here pins by
 * checking an unrelated cache entry survives.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Integration;

use CP_Library\Models\Item as ItemModel;
use CP_Library\Models\Speaker as SpeakerModel;

/**
 * @covers ::cp_library_migrate_1_6_3
 * @covers ::cp_library_invalidate_item_caches
 */
class Migration163Test extends TestCase {

	/**
	 * Run the migration as an upgrade from 1.6.2.
	 */
	private function migrate() {
		// The migration is guarded by a completion flag; clear it so each test runs it.
		$migrations = get_option( 'cp_library_migrations', [] );
		unset( $migrations['1.6.3'] );
		update_option( 'cp_library_migrations', $migrations );

		cp_library_migrate_1_6_3( '1.6.2', '1.6.3' );
	}

	/**
	 * Insert a source_item row with an explicit (possibly invalid) source id.
	 */
	private function insert_raw_speaker_row( $item_id, $source_id ) {
		global $wpdb;

		$table = SpeakerModel::get_prop( 'meta_table_name' );

		if ( null === $source_id ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$table} (`key`, `source_id`, `source_type_id`, `item_id`) VALUES ( 'source_item', NULL, %d, %d )",
					SpeakerModel::get_type_id(),
					$item_id
				)
			);

			return (int) $wpdb->insert_id;
		}

		$wpdb->insert(
			$table,
			[
				'key'            => 'source_item',
				'source_id'      => $source_id,
				'source_type_id' => SpeakerModel::get_type_id(),
				'item_id'        => $item_id,
			]
		);

		return (int) $wpdb->insert_id;
	}

	public function test_orphaned_speaker_rows_are_removed_and_real_ones_survive() {
		$item    = $this->make_item();
		$speaker = $this->make_speaker();

		$real = $this->insert_raw_speaker_row( $item->id, $speaker->id );
		$this->insert_raw_speaker_row( $item->id, null );          // NULL source
		$this->insert_raw_speaker_row( $item->id, 0 );             // zero source
		$this->insert_raw_speaker_row( $item->id, 999999 );        // deleted speaker

		$this->assertCount( 4, $this->speaker_rows( $item->id ), 'fixture: one real, three orphans' );

		$this->migrate();

		$rows = $this->speaker_rows( $item->id );

		$this->assertCount( 1, $rows, 'only the real association survives' );
		$this->assertEquals( $real, $rows[0]->id );
		$this->assertEquals( $speaker->id, $rows[0]->source_id );
	}

	public function test_orphaned_series_rows_are_removed_and_real_ones_survive() {
		global $wpdb;

		$item   = $this->make_item();
		$series = $this->make_series();
		$table  = ItemModel::get_prop( 'meta_table_name' );

		$wpdb->insert( $table, [ 'key' => 'item_type', 'item_type_id' => $series->id, 'item_id' => $item->id ] );
		$real = (int) $wpdb->insert_id;

		$wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (`key`, `item_type_id`, `item_id`) VALUES ( 'item_type', NULL, %d )", $item->id ) );
		$wpdb->insert( $table, [ 'key' => 'item_type', 'item_type_id' => 0, 'item_id' => $item->id ] );
		$wpdb->insert( $table, [ 'key' => 'item_type', 'item_type_id' => 999999, 'item_id' => $item->id ] );

		$this->assertCount( 4, $this->type_rows( $item->id ), 'fixture: one real, three orphans' );

		$this->migrate();

		$rows = $this->type_rows( $item->id );

		$this->assertCount( 1, $rows );
		$this->assertEquals( $real, $rows[0]->id );
	}

	public function test_unrelated_meta_rows_are_not_touched() {
		global $wpdb;

		$item  = $this->make_item();
		$table = ItemModel::get_prop( 'meta_table_name' );

		// A value-bearing row under a different key, with no item_type_id at all —
		// it must not be caught by the item_type cleanup.
		$wpdb->insert( $table, [ 'key' => 'audio_url', 'value' => 'https://example.org/a.mp3', 'item_id' => $item->id ] );

		$this->migrate();

		$this->assertEquals(
			'https://example.org/a.mp3',
			$wpdb->get_var( $wpdb->prepare( "SELECT `value` FROM {$table} WHERE `key` = 'audio_url' AND `item_id` = %d", $item->id ) ),
			'the cleanup is scoped by `key`'
		);
	}

	public function test_migration_does_not_run_twice() {
		$item = $this->make_item();

		$this->migrate();

		$migrations = get_option( 'cp_library_migrations', [] );
		$this->assertArrayHasKey( '1.6.3', $migrations, 'completion is recorded' );

		// A second call without clearing the flag must be a no-op, so a row added
		// after the first run survives.
		$this->insert_raw_speaker_row( $item->id, 0 );
		cp_library_migrate_1_6_3( '1.6.2', '1.6.3' );

		$this->assertCount( 1, $this->speaker_rows( $item->id ), 'already-run migration must not fire again' );
	}

	public function test_migration_is_skipped_for_installs_already_past_1_6_3() {
		$item = $this->make_item();
		$this->insert_raw_speaker_row( $item->id, 0 );

		$migrations = get_option( 'cp_library_migrations', [] );
		unset( $migrations['1.6.3'] );
		update_option( 'cp_library_migrations', $migrations );

		cp_library_migrate_1_6_3( '1.7.0', '1.7.1' );

		$this->assertCount( 1, $this->speaker_rows( $item->id ), 'version guard holds' );
	}

	public function test_only_affected_item_caches_are_evicted() {
		$item    = $this->make_item();
		$speaker = $this->make_speaker();

		$this->insert_raw_speaker_row( $item->id, $speaker->id );
		$this->insert_raw_speaker_row( $item->id, 0 );

		// Stand-ins for everything a site-wide flush would have taken with it:
		// core's caches, other plugins' data, other sites on a shared backend.
		wp_cache_set( 'alloptions', [ 'a' => 'b' ], 'options' );
		wp_cache_set( 'some_key', 'some_value', 'another_plugin' );

		$this->migrate();

		$this->assertSame(
			'some_value',
			wp_cache_get( 'some_key', 'another_plugin' ),
			'the migration must not flush cache groups it does not own'
		);
		$this->assertSame(
			[ 'a' => 'b' ],
			wp_cache_get( 'alloptions', 'options' ),
			"core's own caches must survive the upgrade"
		);
	}

	public function test_affected_item_reads_back_without_the_orphan() {
		$item    = $this->make_item();
		$speaker = $this->make_speaker();

		$this->insert_raw_speaker_row( $item->id, $speaker->id );
		$this->insert_raw_speaker_row( $item->id, 0 );

		// Prime the cache with the pre-migration state, the way a page load would.
		$stale = ItemModel::get_instance( $item->id );
		$this->assertCount( 2, $stale->get_speakers(), 'fixture: cache holds the orphan' );

		$this->migrate();

		$fresh = ItemModel::get_instance( $item->id );

		$this->assertEqualsCanonicalizing(
			[ $speaker->id ],
			array_map( 'absint', $fresh->get_speakers() ),
			'the item cache is invalidated, so the orphan is gone on the next read'
		);
	}
}
