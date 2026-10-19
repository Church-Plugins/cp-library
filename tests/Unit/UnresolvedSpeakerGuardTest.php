<?php
/**
 * Tests for the guard that stops an unmatched name from clearing a sermon's
 * speakers.
 *
 * Speaker names arrive from feeds and importers as text, and are resolved to
 * ids by an exact title/slug lookup against published speakers. A name that
 * doesn't match — unpublished, misspelled, or several names in one column —
 * resolved to an empty array, which the save path read as "this sermon has no
 * speakers" and acted on: re-running a WP All Import feed to refresh audio URLs
 * stripped the speakers off every sermon whose speaker column didn't match.
 *
 * The distinction the guard draws is between *nothing submitted* (a real
 * request to clear) and *something submitted that we could not resolve* (a
 * lookup failure, which must change nothing).
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use Brain\Monkey;
use CP_Library\Setup\PostTypes\Speaker;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Exposes the protected guard. The constructor registers WP hooks and reads
 * settings, so instances are built without it.
 */
class GuardableSpeaker extends Speaker {

	public function __construct() {
		// Deliberately does not call parent::__construct().
	}

	public function guard( $raw, $ids ) {
		return $this->is_unresolved( $raw, $ids );
	}
}

/**
 * @covers \CP_Library\Setup\PostTypes\Speaker::is_unresolved
 */
class UnresolvedSpeakerGuardTest extends TestCase {

	/** @var GuardableSpeaker */
	private $speaker;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->speaker = ( new ReflectionClass( GuardableSpeaker::class ) )->newInstanceWithoutConstructor();
		$this->speaker->plural_label = 'Speakers';
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_resolved_ids_are_allowed_through() {
		$this->assertFalse(
			$this->speaker->guard( 'John Smith', [ 4 ] ),
			'a name that resolved must be applied'
		);
	}

	public function test_empty_submission_is_allowed_to_clear() {
		$this->assertFalse(
			$this->speaker->guard( '', [] ),
			'deselecting every speaker in the admin must still clear them'
		);
	}

	/**
	 * @dataProvider empty_submissions
	 */
	public function test_all_empty_shapes_are_allowed_to_clear( $raw, $reason ) {
		$this->assertFalse( $this->speaker->guard( $raw, [] ), $reason );
	}

	public function empty_submissions() {
		return [
			'empty string'          => [ '', 'an unmapped feed column' ],
			'empty array'           => [ [], 'CMB2 sends an empty array when nothing is selected' ],
			'null'                  => [ null, 'absent value' ],
			'whitespace only'       => [ '   ', 'a column of spaces is not a speaker name' ],
			'array of blanks'       => [ [ '', '  ' ], 'nor is a list of them' ],
		];
	}

	/**
	 * @dataProvider unresolved_submissions
	 */
	public function test_unmatched_names_are_blocked( $raw, $reason ) {
		$this->assertTrue( $this->speaker->guard( $raw, [] ), $reason );
	}

	public function unresolved_submissions() {
		return [
			'misspelled name'   => [ 'Jhon Smith', 'a typo must not wipe the sermon' ],
			'draft speaker'     => [ 'Jane Doe', 'an unpublished speaker resolves to nothing' ],
			'delimited list'    => [ 'John Smith, Jane Doe', 'nor must a list we failed to split' ],
			'array of names'    => [ [ 'John Smith', 'Jane Doe' ], 'same for an array form' ],
			'numeric-ish text'  => [ '0007', 'anything non-empty that resolved to nothing' ],
		];
	}

	public function test_partial_resolution_is_allowed_through() {
		// Two names submitted, one matched: that is a real (if lossy) answer from
		// the feed, not a lookup failure, so it is applied.
		$this->assertFalse(
			$this->speaker->guard( [ 'John Smith', 'Nobody' ], [ 4 ] ),
			'a partial match must not be treated as unresolved'
		);
	}

	public function test_nested_arrays_do_not_count_as_submitted_values() {
		// Guards against a shape that would otherwise trip trim() on an array.
		$this->assertFalse( $this->speaker->guard( [ [ 'nested' ] ], [] ) );
	}
}
