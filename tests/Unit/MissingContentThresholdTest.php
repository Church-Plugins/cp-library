<?php
/**
 * Tests for the rule deciding which missing-content types reach the dashboard.
 *
 * "Needs attention" is a work queue, and a work queue is only useful when its
 * rows are exceptions. Measured on a real 8,800-sermon library: 243 sermons
 * with no media (2.8%) is a Monday morning task list, but 4,099 with no series
 * (46%) and 8,783 with no transcript (99.5%) are not defects — that church
 * simply does not put everything in a series and has never used transcripts.
 * Listing those under "needs attention" is how a dashboard teaches people that
 * its numbers are noise, and then the 243 goes unread too.
 *
 * So a type is reported only when it looks like an exception rather than a
 * convention. The list table filter still offers every type: "show me sermons
 * with no series" is a fair question even when the answer is half the library.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Library\Admin\MissingContent;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Feeds the decision fixed counts so it can be exercised without a database.
 */
class StubbedMissingContent extends MissingContent {

	/** @var array */
	public $counts = array();

	public function __construct() {
		// Deliberately does not call parent::__construct() — that registers hooks.
	}

	public function get_counts( $force = false ) {
		return $this->counts;
	}

	protected function get_types() {
		return array(
			'media'      => array( 'label' => 'Missing audio and video', 'sql' => '' ),
			'speaker'    => array( 'label' => 'No speaker', 'sql' => '' ),
			'series'     => array( 'label' => 'Not in a series', 'sql' => '' ),
			'transcript' => array( 'label' => 'No transcript', 'sql' => '' ),
		);
	}

	protected function get_post_type() {
		return 'cpl_item';
	}
}

/**
 * @covers \CP_Library\Admin\MissingContent::get_problems
 */
class MissingContentThresholdTest extends TestCase {

	/** @var StubbedMissingContent */
	private $missing;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'admin_url' )->alias(
			function ( $path = '' ) {
				return 'https://example.test/wp-admin/' . $path;
			}
		);

		Functions\when( 'add_query_arg' )->alias(
			function ( $args, $url ) {
				return $url . '?' . http_build_query( $args );
			}
		);

		$this->missing = ( new ReflectionClass( StubbedMissingContent::class ) )->newInstanceWithoutConstructor();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Numbers taken from a real library, so this locks down the actual split
	 * rather than a hypothetical one.
	 */
	public function test_conventions_are_dropped_and_exceptions_kept() {
		$this->missing->counts = array(
			'total'  => 8829,
			'counts' => array(
				'media'      => 243,   // 2.8%
				'speaker'    => 565,   // 6.4%
				'series'     => 4099,  // 46.4%
				'transcript' => 8783,  // 99.5%
			),
		);

		$this->assertSame(
			array( 'media', 'speaker' ),
			array_keys( $this->missing->get_problems() ),
			'Only the types that read as exceptions belong on the dashboard.'
		);
	}

	/**
	 * A type with nothing missing is not a problem.
	 */
	public function test_zero_counts_are_omitted() {
		$this->missing->counts = array(
			'total'  => 500,
			'counts' => array( 'media' => 0, 'speaker' => 3 ),
		);

		$this->assertSame( array( 'speaker' ), array_keys( $this->missing->get_problems() ) );
	}

	/**
	 * Exactly at the threshold still counts as an exception; past it does not.
	 */
	public function test_boundary_is_inclusive() {
		$this->missing->counts = array(
			'total'  => 100,
			'counts' => array( 'media' => 25, 'speaker' => 26 ),
		);

		$this->assertSame( array( 'media' ), array_keys( $this->missing->get_problems() ) );
	}

	/**
	 * A site with different conventions can move the line.
	 */
	public function test_threshold_is_filterable() {
		Functions\when( 'apply_filters' )->alias(
			function ( $hook, $value ) {
				return 'cpl_missing_content_threshold' === $hook ? 0.75 : $value;
			}
		);

		$this->missing->counts = array(
			'total'  => 100,
			'counts' => array( 'series' => 50 ),
		);

		$this->assertSame( array( 'series' ), array_keys( $this->missing->get_problems() ) );
	}

	/**
	 * Every reported row has to be clickable — a count with nowhere to go sends
	 * people to eyeball a list of thousands, which is worse than saying nothing.
	 */
	public function test_each_problem_carries_a_filtered_list_url() {
		$this->missing->counts = array(
			'total'  => 100,
			'counts' => array( 'media' => 4 ),
		);

		$problem = $this->missing->get_problems()['media'];

		$this->assertStringContainsString( 'post_type=cpl_item', $problem['url'] );
		$this->assertStringContainsString( 'cpl_missing=media', $problem['url'] );
		$this->assertSame( 4, $problem['count'] );
	}

	/**
	 * A library with no sermons must not divide by zero.
	 */
	public function test_empty_library_does_not_divide_by_zero() {
		$this->missing->counts = array(
			'total'  => 0,
			'counts' => array( 'media' => 0 ),
		);

		$this->assertSame( array(), $this->missing->get_problems() );
	}
}
