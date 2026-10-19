<?php
/**
 * Tests that permanently deleting a series removes its rows from the item meta
 * table.
 *
 * The item => series association is stored in cpl_item_meta (key `item_type`),
 * not in the series' own meta table, and ItemType::delete() only ever cascaded
 * the latter. So every sermon in a deleted series kept a row pointing at a type
 * that no longer existed — the same orphans the 1.6.3 migration cleans up, but
 * recreated by every series deletion after the migration has already run once.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Integration;

use CP_Library\Models\Item as ItemModel;
use CP_Library\Models\ItemType as ItemTypeModel;

/**
 * @covers \CP_Library\Models\ItemType::delete
 */
class ItemTypeDeleteCascadeTest extends TestCase {

	/**
	 * The series ids an item's rows point at.
	 */
	private function attached_type_ids( $item_id ) {
		return array_map( 'intval', wp_list_pluck( $this->type_rows( $item_id ), 'item_type_id' ) );
	}

	public function test_deleting_a_series_removes_its_item_rows() {
		$item   = $this->make_item();
		$series = $this->make_series();
		$other  = $this->make_series( 'Other Series' );

		$item->update_types( [ $series->id, $other->id ] );

		$this->assertEqualsCanonicalizing(
			[ $series->id, $other->id ],
			$this->attached_type_ids( $item->id ),
			'precondition: both series attached'
		);

		$series->delete();

		$this->assertSame(
			[ (int) $other->id ],
			$this->attached_type_ids( $item->id ),
			'only the deleted series may be removed from the item'
		);

		// The model caches its type list; a stale cache would hide the cleanup.
		$fresh = ItemModel::get_instance( $item->id );
		$this->assertSame( [ (int) $other->id ], array_map( 'intval', $fresh->get_types() ) );
	}

	public function test_deleting_a_series_leaves_other_items_alone() {
		$item_in  = $this->make_item( 'In Series' );
		$item_out = $this->make_item( 'Not In Series' );
		$series   = $this->make_series();
		$other    = $this->make_series( 'Other Series' );

		$item_in->update_types( [ $series->id ] );
		$item_out->update_types( [ $other->id ] );

		$series->delete();

		$this->assertSame( [], $this->attached_type_ids( $item_in->id ) );
		$this->assertSame( [ (int) $other->id ], $this->attached_type_ids( $item_out->id ) );
	}
}
