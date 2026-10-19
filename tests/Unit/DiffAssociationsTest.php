<?php
/**
 * Tests for the speaker / series / service-type association diff.
 *
 * The rows an item holds are not a set. The same source can appear on more than
 * one row (the corruption the 1.6.3 migration cleans up), and legacy rows can
 * carry a NULL or 0 id.
 *
 * The bug this locks down: the reconcile matched one occurrence off the existing
 * list per desired id, then deleted whatever was left *by value* — so an item
 * with two rows for speaker 7 that the user re-saved with 7 still selected had
 * one occurrence matched off, and the leftover 7 deleted BOTH rows. The speaker
 * survived in the request's object cache, then silently vanished from the
 * sermon, its archive and the filters once the cache was evicted.
 *
 * The fix counts the leftovers per id and deletes only that many rows, so these
 * tests are really about the counts in `surplus`.
 *
 * Pure logic — the SQL that consumes this decision is out of reach here and
 * belongs in an integration test.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use CP_Library\Models\Item;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Library\Models\Item::diff_associations
 * @covers \CP_Library\Models\Item::normalize_ids
 */
class DiffAssociationsTest extends TestCase {

	public function test_duplicate_row_for_a_kept_source_removes_only_the_extra() {
		$diff = Item::diff_associations( [ 7, 7 ], [ 7 ] );

		$this->assertSame( [], $diff['add'], 'the speaker already has a row' );
		$this->assertSame( [ 7 => 1 ], $diff['surplus'], 'exactly one of the two rows must go' );
	}

	public function test_triplicate_row_for_a_kept_source_leaves_one() {
		$diff = Item::diff_associations( [ 7, 7, 7 ], [ 7 ] );

		$this->assertSame( [ 7 => 2 ], $diff['surplus'] );
	}

	public function test_removing_a_duplicated_source_removes_every_row() {
		$diff = Item::diff_associations( [ 7, 7 ], [] );

		$this->assertSame( [ 7 => 2 ], $diff['surplus'], 'nothing is kept, so both rows go' );
	}

	public function test_unchanged_association_is_left_alone() {
		$diff = Item::diff_associations( [ 7 ], [ 7 ] );

		$this->assertSame( [], $diff['add'] );
		$this->assertSame( [], $diff['surplus'], 'a no-op save must not touch the table' );
	}

	public function test_new_source_is_added_without_disturbing_the_existing_one() {
		$diff = Item::diff_associations( [ 7 ], [ 7, 9 ] );

		$this->assertSame( [ 9 ], $diff['add'] );
		$this->assertSame( [], $diff['surplus'] );
	}

	public function test_swapping_sources_adds_one_and_drops_the_other() {
		$diff = Item::diff_associations( [ 7 ], [ 9 ] );

		$this->assertSame( [ 9 ], $diff['add'] );
		$this->assertSame( [ 7 => 1 ], $diff['surplus'] );
	}

	public function test_mixed_duplicates_and_removals() {
		// Two rows for 7 (kept), one for 9 (dropped), and 11 is new.
		$diff = Item::diff_associations( [ 7, 9, 7 ], [ 7, 11 ] );

		$this->assertSame( [ 11 ], $diff['add'] );
		$this->assertEqualsCanonicalizing( [ 7 => 1, 9 => 1 ], $diff['surplus'] );
	}

	public function test_legacy_null_and_zero_rows_are_always_surplus() {
		// get_col() returns strings, and a NULL column comes back as null.
		$diff = Item::diff_associations( [ null, '0', '7' ], [ 7 ] );

		$this->assertSame( [], $diff['add'], 'string 7 must match int 7 after normalizing' );
		$this->assertSame( [ 0 => 2 ], $diff['surplus'], 'NULL and 0 both normalize to 0 and are never desired' );
	}

	public function test_string_ids_from_the_database_match_integer_input() {
		$diff = Item::diff_associations( [ '7', '9' ], [ '9', '7' ] );

		$this->assertSame( [], $diff['add'] );
		$this->assertSame( [], $diff['surplus'], 'order must not matter' );
	}

	public function test_desired_list_is_deduplicated_before_matching() {
		// A caller passing the same id twice must not produce a second row.
		$diff = Item::diff_associations( [], [ 7, 7 ] );

		$this->assertSame( [ 7 ], $diff['add'] );
	}

	public function test_zero_and_negative_input_is_discarded() {
		$this->assertSame( [ 7 ], Item::normalize_ids( [ 0, '', null, 7, '0' ] ) );
		$this->assertSame( [ 7 ], Item::normalize_ids( [ -7, 7 ] ), 'absint folds -7 onto 7' );
	}

	public function test_scalar_input_is_accepted() {
		$this->assertSame( [ 7 ], Item::normalize_ids( 7 ) );
		$this->assertSame( [], Item::normalize_ids( null ) );
	}

	public function test_empty_on_both_sides_is_a_no_op() {
		$diff = Item::diff_associations( [], [] );

		$this->assertSame( [], $diff['add'] );
		$this->assertSame( [], $diff['surplus'] );
	}
}
