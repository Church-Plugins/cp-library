<?php
/**
 * Decision for linking a SermonAudio sermon to one that already exists.
 *
 * Sync inserts a new cpl_item when no post stores that sermonID as
 * external_id. A sermon from a CSV import or the editor has no external_id,
 * so the same service was created twice. The link runs only when exactly one
 * sermon on that site-local calendar day has the same normalized title and
 * no other external id. The database query is covered in the integration
 * suite. These tests lock the comparison and the choice.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use CP_Library\Adapters\SermonAudioMatch;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Library\Adapters\SermonAudioMatch
 */
class SermonAudioMatchTest extends TestCase {

	public function test_normalize_title_decodes_once_then_trims_lowercases_and_collapses_whitespace() {
		$this->assertSame(
			"the lord's prayer",
			SermonAudioMatch::normalize_title( '  The Lord&#039;s   Prayer  ' )
		);
		$this->assertSame(
			'faith & hope',
			SermonAudioMatch::normalize_title( "FAITH &amp; \n\t HOPE" )
		);
		$this->assertSame(
			'faith hope',
			SermonAudioMatch::normalize_title( 'Faith&nbsp;Hope' )
		);
		$this->assertSame( 'café', SermonAudioMatch::normalize_title( 'CAFÉ' ) );
	}

	public function test_normalize_title_does_not_decode_a_second_time_or_strip_punctuation() {
		$this->assertSame( 'a &amp; b', SermonAudioMatch::normalize_title( 'A &amp;amp; B' ) );
		$this->assertSame( 'hello!', SermonAudioMatch::normalize_title( 'Hello!' ) );
		$this->assertNotSame(
			SermonAudioMatch::normalize_title( 'Hello' ),
			SermonAudioMatch::normalize_title( 'Hello!' )
		);
	}

	public function test_normalize_title_of_a_blank_or_invalid_value_is_empty() {
		$this->assertSame( '', SermonAudioMatch::normalize_title( '' ) );
		$this->assertSame( '', SermonAudioMatch::normalize_title( '   ' ) );
		$this->assertSame( '', SermonAudioMatch::normalize_title( null ) );
		$this->assertSame( '', SermonAudioMatch::normalize_title( 12 ) );
		$this->assertSame( '', SermonAudioMatch::normalize_title( "bad\xFF" ) );
	}

	public function test_calendar_day_is_the_post_date_prefix() {
		$this->assertSame( '2024-03-10', SermonAudioMatch::calendar_day( '2024-03-10 23:30:00' ) );
		$this->assertSame( '2024-03-10', SermonAudioMatch::calendar_day( '2024-03-10 00:00:00' ) );
		$this->assertSame( '2024-03-11', SermonAudioMatch::calendar_day( '2024-03-11 00:00:00' ) );
		$this->assertSame( '2024-03-10', SermonAudioMatch::calendar_day( '2024-03-10' ) );
		$this->assertSame( '', SermonAudioMatch::calendar_day( '2024-03-10 12:00' ) );
		$this->assertSame( '', SermonAudioMatch::calendar_day( null ) );
	}

	public function test_next_day_crosses_a_dst_date_without_skipping() {
		$this->assertSame( '2024-03-11', SermonAudioMatch::next_day( '2024-03-10' ) );
		$this->assertSame( '2024-03-01', SermonAudioMatch::next_day( '2024-02-29' ) );
		$this->assertSame( '', SermonAudioMatch::next_day( '2024-02-31' ) );
		$this->assertSame( '', SermonAudioMatch::next_day( '03-10-2024' ) );
	}

	public function test_exactly_one_title_match_with_no_external_id_is_linked() {
		$id = SermonAudioMatch::select_match(
			array(
				$this->candidate( 4, 'Other sermon', '' ),
				$this->candidate( 9, '  The Lord&#039;s   Prayer  ', '' ),
			),
			"the lord's prayer",
			'sa-1'
		);

		$this->assertSame( 9, $id );
	}

	public function test_zero_title_matches_are_not_linked() {
		$this->assertNull(
			SermonAudioMatch::select_match(
				array( $this->candidate( 4, 'Other sermon', '' ) ),
				"the lord's prayer",
				'sa-1'
			)
		);
	}

	public function test_two_title_matches_are_not_linked() {
		$this->assertNull(
			SermonAudioMatch::select_match(
				array(
					$this->candidate( 4, 'Grace', '' ),
					$this->candidate( 9, 'grace', '' ),
				),
				'Grace',
				'sa-1'
			)
		);
	}

	public function test_two_title_matches_are_not_linked_when_only_one_lacks_an_external_id() {
		$this->assertNull(
			SermonAudioMatch::select_match(
				array(
					$this->candidate( 4, 'Grace', 'other-id' ),
					$this->candidate( 9, 'Grace', '' ),
				),
				'Grace',
				'sa-1'
			)
		);
	}

	public function test_one_match_with_a_different_external_id_is_not_linked() {
		$this->assertNull(
			SermonAudioMatch::select_match(
				array( $this->candidate( 9, 'Grace', 'csv-1' ) ),
				'Grace',
				'sa-1'
			)
		);
	}

	public function test_one_match_that_already_has_this_external_id_is_that_post() {
		$this->assertSame(
			9,
			SermonAudioMatch::select_match(
				array( $this->candidate( 9, 'Grace', 'sa-1' ) ),
				'Grace',
				'sa-1'
			)
		);
	}

	public function test_two_sermons_in_one_batch_with_the_same_day_and_title_are_ambiguous() {
		$batch = SermonAudioMatch::flag_batch_ambiguities(
			array(
				'sa-9'  => array(
					'post_title' => 'Sunday Worship',
					'post_date'  => '2024-06-02 09:00:00',
				),
				'sa-11' => array(
					'post_title' => 'sunday   worship',
					'post_date'  => '2024-06-02 11:00:00',
				),
				'sa-other' => array(
					'post_title' => 'A different sermon',
					'post_date'  => '2024-06-02 09:00:00',
				),
			)
		);

		$this->assertSame( 1, $batch['sa-9'][ SermonAudioMatch::BATCH_AMBIGUOUS ] );
		$this->assertSame( 1, $batch['sa-11'][ SermonAudioMatch::BATCH_AMBIGUOUS ] );
		$this->assertArrayNotHasKey( SermonAudioMatch::BATCH_AMBIGUOUS, $batch['sa-other'] );
	}

	public function test_the_same_title_on_different_days_is_not_ambiguous() {
		$batch = SermonAudioMatch::flag_batch_ambiguities(
			array(
				array(
					'post_title' => 'Sunday Worship',
					'post_date'  => '2024-06-02 09:00:00',
				),
				array(
					'post_title' => 'Sunday Worship',
					'post_date'  => '2024-06-09 11:00:00',
				),
			)
		);

		$this->assertArrayNotHasKey( SermonAudioMatch::BATCH_AMBIGUOUS, $batch[0] );
		$this->assertArrayNotHasKey( SermonAudioMatch::BATCH_AMBIGUOUS, $batch[1] );
	}

	public function test_blank_titles_in_a_batch_are_not_treated_as_ambiguous() {
		$batch = SermonAudioMatch::flag_batch_ambiguities(
			array(
				array(
					'post_title' => '   ',
					'post_date'  => '2024-06-02 09:00:00',
				),
				array(
					'post_title' => '',
					'post_date'  => '2024-06-02 11:00:00',
				),
			)
		);

		$this->assertArrayNotHasKey( SermonAudioMatch::BATCH_AMBIGUOUS, $batch[0] );
		$this->assertArrayNotHasKey( SermonAudioMatch::BATCH_AMBIGUOUS, $batch[1] );
	}

	public function test_an_empty_normalized_title_does_not_link_an_empty_sermon() {
		$this->assertNull(
			SermonAudioMatch::select_match(
				array( $this->candidate( 9, '   ', '' ) ),
				'   ',
				'sa-1'
			)
		);
	}

	public function test_nonempty_content_and_scripture_are_kept_and_empty_ones_are_not() {
		$this->assertSame(
			array(
				'content'   => true,
				'scripture' => true,
			),
			SermonAudioMatch::preserved_fields( ' Local notes ', array( 'John 3:16' ) )
		);
		$this->assertSame(
			array(
				'content'   => false,
				'scripture' => false,
			),
			SermonAudioMatch::preserved_fields( '   ', array( '  ', '' ) )
		);
		$this->assertFalse( SermonAudioMatch::preserved_fields( null, null )['content'] );
		$this->assertFalse( SermonAudioMatch::preserved_fields( '', '   ' )['scripture'] );
		$this->assertTrue( SermonAudioMatch::preserved_fields( '', 'John 3:16' )['scripture'] );
	}

	/**
	 * @param int    $id
	 * @param string $title
	 * @param string $external_id
	 * @return array{ID:int,post_title:string,external_id:string}
	 */
	private function candidate( $id, $title, $external_id ) {
		return array(
			'ID'          => $id,
			'post_title'  => $title,
			'external_id' => $external_id,
		);
	}
}
