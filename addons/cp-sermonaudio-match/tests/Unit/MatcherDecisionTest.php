<?php
/**
 * Which same-day sermon, if any, SermonAudio may attach to.
 *
 * load_item() only reuses a post when external_id meta already points at it,
 * so a sermon imported from CSV (same title, same calendar day, no
 * external_id) was inserted again. The link is allowed only for exactly one
 * title match that has no external_id or the same one. Zero matches, two or
 * more matches, and one match that already has a different external_id do
 * nothing. 09:00 and 12:00 on one date are the same day. The next date is not.
 *
 * @package CP_SermonAudio_Match
 */

namespace CP_SermonAudio_Match\Tests\Unit;

use CP_SermonAudio_Match\Matcher;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_SermonAudio_Match\Matcher::choose
 * @covers \CP_SermonAudio_Match\Matcher::calendar_day
 * @covers \CP_SermonAudio_Match\Matcher::next_day
 */
class MatcherDecisionTest extends TestCase {

	public function test_zero_matches_skip_with_no_candidate_ids() {
		$choice = Matcher::choose(
			array(
				$this->candidate( 4, 'Something Else', '' ),
			),
			'The Good Shepherd',
			'sa-1'
		);

		$this->assertSame( 'skip', $choice['action'] );
		$this->assertSame( 'none', $choice['reason'] );
		$this->assertSame( array(), $choice['candidate_ids'] );
		$this->assertSame( 0, $choice['post_id'] );
	}

	public function test_one_match_without_an_external_id_links() {
		$choice = Matcher::choose(
			array(
				$this->candidate( 9, 'The Good Shepherd...', '' ),
				$this->candidate( 3, 'A Different Sermon', '' ),
			),
			'The Good Shepherd',
			'sa-9'
		);

		$this->assertSame( 'link', $choice['action'] );
		$this->assertSame( 9, $choice['post_id'] );
		$this->assertSame( array( 9 ), $choice['candidate_ids'] );
		$this->assertSame( '', $choice['reason'] );
	}

	public function test_one_match_with_the_same_external_id_links() {
		$choice = Matcher::choose(
			array(
				$this->candidate( 9, 'The Good Shepherd', 'sa-9' ),
			),
			'The Good Shepherd',
			'sa-9'
		);

		$this->assertSame( 'link', $choice['action'] );
		$this->assertSame( 9, $choice['post_id'] );
	}

	public function test_two_or_more_matches_skip_and_list_every_candidate() {
		$choice = Matcher::choose(
			array(
				$this->candidate( 12, 'The Good Shepherd', '' ),
				$this->candidate( 4, 'Other', '' ),
				$this->candidate( 7, 'The Good Shepherd!', '' ),
			),
			'The Good Shepherd',
			'sa-1'
		);

		$this->assertSame( 'skip', $choice['action'] );
		$this->assertSame( 'multiple', $choice['reason'] );
		$this->assertSame( array( 7, 12 ), $choice['candidate_ids'] );
		$this->assertSame( 0, $choice['post_id'] );
	}

	public function test_a_single_match_with_a_different_external_id_skips() {
		$choice = Matcher::choose(
			array(
				$this->candidate( 15, 'The Good Shepherd', 'sa-other' ),
			),
			'The Good Shepherd',
			'sa-new'
		);

		$this->assertSame( 'skip', $choice['action'] );
		$this->assertSame( 'different_external_id', $choice['reason'] );
		$this->assertSame( array( 15 ), $choice['candidate_ids'] );
		$this->assertSame( 0, $choice['post_id'] );
	}

	public function test_two_matches_skip_even_when_only_one_lacks_an_external_id() {
		$choice = Matcher::choose(
			array(
				$this->candidate( 2, 'The Good Shepherd', '' ),
				$this->candidate( 8, 'The Good Shepherd', 'sa-other' ),
			),
			'The Good Shepherd',
			'sa-new'
		);

		$this->assertSame( 'skip', $choice['action'] );
		$this->assertSame( 'multiple', $choice['reason'] );
		$this->assertSame( array( 2, 8 ), $choice['candidate_ids'] );
	}

	public function test_normalized_titles_count_as_the_same_sermon() {
		$choice = Matcher::choose(
			array(
				$this->candidate( 6, "It\u{2019}s  a   <em>Good</em> Shepherd...", '' ),
			),
			"It's a Good Shepherd",
			'sa-6'
		);

		$this->assertSame( 'link', $choice['action'] );
		$this->assertSame( 6, $choice['post_id'] );
	}

	public function test_the_same_calendar_day_matches_across_09_00_and_noon() {
		$morning = '2024-05-12 09:00:00';
		$noon    = '2024-05-12 12:00:00';

		$this->assertSame( '2024-05-12', Matcher::calendar_day( $morning ) );
		$this->assertSame( Matcher::calendar_day( $morning ), Matcher::calendar_day( $noon ) );
		$this->assertSame( '2024-05-13', Matcher::next_day( '2024-05-12' ) );
	}

	public function test_a_different_calendar_day_does_not_match() {
		$this->assertNotSame(
			Matcher::calendar_day( '2024-05-12 12:00:00' ),
			Matcher::calendar_day( '2024-05-11 09:00:00' )
		);
		$this->assertSame( '', Matcher::calendar_day( 'not-a-date' ) );
		$this->assertSame( '', Matcher::calendar_day( null ) );
		$this->assertSame( '2025-01-01', Matcher::next_day( '2024-12-31' ) );
		$this->assertSame( '', Matcher::next_day( '2024-13-01' ) );
	}

	/**
	 * @param int    $id          Post ID.
	 * @param string $title       Stored title.
	 * @param string $external_id external_id meta, or '' when unset.
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
