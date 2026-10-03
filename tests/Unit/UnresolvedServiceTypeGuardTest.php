<?php
/**
 * Tests for the guard that stops an unmatched name from clearing a sermon's
 * service type.
 *
 * Speaker and Series gained this guard in 1.6.3; Service Type was the third
 * sibling with the same save path and was left out. A WP All Import feed with a
 * misspelled, unpublished, or comma-delimited service type column resolved to
 * an empty list, which update_service_types() read as "no service types" —
 * deleting the existing rows, and for a variant the very row that records which
 * variation it is.
 *
 * The distinction the guard draws is between *nothing submitted* (a real
 * request to clear) and *something submitted that we could not resolve* (a
 * lookup failure, which must change nothing).
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use Brain\Monkey;
use CP_Library\Setup\PostTypes\ServiceType;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Exposes the protected guard. The constructor registers WP hooks and reads
 * settings, so instances are built without it.
 */
class GuardableServiceType extends ServiceType {

	public function __construct() {
		// Deliberately does not call parent::__construct().
	}

	public function guard( $raw, $ids ) {
		return $this->is_unresolved( $raw, $ids );
	}
}

/**
 * @covers \CP_Library\Setup\PostTypes\ServiceType::is_unresolved
 */
class UnresolvedServiceTypeGuardTest extends TestCase {

	/** @var GuardableServiceType */
	private $service_type;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->service_type = ( new ReflectionClass( GuardableServiceType::class ) )->newInstanceWithoutConstructor();
		$this->service_type->plural_label = 'Service Types';
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_resolved_ids_are_allowed_through() {
		$this->assertFalse(
			$this->service_type->guard( 'Sunday AM', [ 2 ] ),
			'a name that resolved must be applied'
		);
	}

	/**
	 * @dataProvider empty_submissions
	 */
	public function test_all_empty_shapes_are_allowed_to_clear( $raw, $reason ) {
		$this->assertFalse( $this->service_type->guard( $raw, [] ), $reason );
	}

	public function empty_submissions() {
		return [
			'empty string'    => [ '', 'an unmapped feed column' ],
			'empty array'     => [ [], 'CMB2 sends an empty array when nothing is selected' ],
			'null'            => [ null, 'absent value' ],
			'whitespace only' => [ '   ', 'a column of spaces is not a service type' ],
			'array of blanks' => [ [ '', '  ' ], 'nor is a list of them' ],
		];
	}

	/**
	 * @dataProvider unresolved_submissions
	 */
	public function test_unmatched_names_are_blocked( $raw, $reason ) {
		$this->assertTrue( $this->service_type->guard( $raw, [] ), $reason );
	}

	public function unresolved_submissions() {
		return [
			'misspelled name' => [ 'Sundy AM', 'a typo must not wipe the sermon' ],
			'draft type'      => [ 'Youth Night', 'an unpublished service type resolves to nothing' ],
			'delimited list'  => [ 'Sunday AM, Sunday PM', 'nor must a list we failed to split' ],
			'array of names'  => [ [ 'Sunday AM', 'Sunday PM' ], 'same for an array form' ],
		];
	}

	public function test_partial_resolution_is_allowed_through() {
		$this->assertFalse(
			$this->service_type->guard( [ 'Sunday AM', 'Nobody' ], [ 2 ] ),
			'a partial match must not be treated as unresolved'
		);
	}
}
