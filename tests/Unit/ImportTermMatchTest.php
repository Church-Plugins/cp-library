<?php
/**
 * CSV import must attach a sermon to a series, speaker, or service type only
 * when the name matches exactly.
 *
 * The series lookup asked ItemType::search() for titles LIKE %value% and, when
 * nothing was equal with PHP's case-sensitive ==, kept the first row. A row
 * whose series was "Hope" was saved on the existing "Hope for Today" series.
 *
 * Speakers and service types already compared a lowercased name, but the
 * stored title was not trimmed, and the series path did not share that rule.
 * One comparison now covers all three: exact, case-insensitive, trimmed.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use CP_Library\Admin\Import\ImportSermons;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Library\Admin\Import\ImportSermons::matching_term_id
 * @covers \CP_Library\Admin\Import\ImportSermons::normalize_term_name
 */
class ImportTermMatchTest extends TestCase {

	public function test_hope_does_not_match_hope_for_today() {
		$id = ImportSermons::matching_term_id(
			'Hope',
			array(
				(object) array(
					'id'    => 3,
					'title' => 'Hope for Today',
				),
			)
		);

		$this->assertNull( $id );
	}

	public function test_exact_name_matches_regardless_of_case_and_surrounding_space() {
		$id = ImportSermons::matching_term_id(
			'  hOpE for today  ',
			array(
				(object) array(
					'id'    => 3,
					'title' => 'Hope for Today',
				),
			)
		);

		$this->assertSame( 3, $id );
	}

	public function test_exact_name_is_chosen_when_a_longer_name_contains_it() {
		$id = ImportSermons::matching_term_id(
			'Hope',
			array(
				(object) array(
					'id'    => 3,
					'title' => 'Hope for Today',
				),
				(object) array(
					'id'    => 8,
					'title' => 'Hope',
				),
			)
		);

		$this->assertSame( 8, $id );
	}

	public function test_speaker_map_uses_the_same_rule() {
		$speakers = array(
			4 => 'hope for today',
			9 => 'hope',
		);

		$this->assertNull( ImportSermons::matching_term_id( 'Hope', array( 4 => 'hope for today' ) ) );
		$this->assertSame( 9, ImportSermons::matching_term_id( ' HOPE ', $speakers ) );
		$this->assertSame( 4, ImportSermons::matching_term_id( 'HOPE FOR TODAY', $speakers ) );
	}

	public function test_blank_name_does_not_match() {
		$existing = array(
			(object) array(
				'id'    => 3,
				'title' => 'Hope',
			),
		);

		$this->assertNull( ImportSermons::matching_term_id( '', $existing ) );
		$this->assertNull( ImportSermons::matching_term_id( '   ', $existing ) );
	}
}
