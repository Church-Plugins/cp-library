<?php
/**
 * Tests for the guard that stops a save which never included the association
 * field from clearing a sermon's associations.
 *
 * CMB2 fires cmb2_save_field_{id} for every registered field on any save it
 * performs — even when the field's key is absent from the data being saved
 * (CMB2_Field::save_field() then runs with a null value and action 'removed').
 * The save handlers read that absence as an empty submission and called
 * update_*( [] ), so any save that legitimately carried only a subset of fields
 * — CMB2::save_fields() from custom code, another box's form — silently
 * stripped the sermon's series, speakers and service types.
 *
 * The distinction drawn: a key absent from the metabox's OWN submission
 * (identified by its nonce riding along in the data) is an emptied multiselect,
 * which posts nothing and must clear; a key absent from any other save means
 * the field was not part of the request, and nothing may change.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Library\Setup\PostTypes\ItemType;
use CP_Library\Setup\PostTypes\ServiceType;
use CP_Library\Setup\PostTypes\Speaker;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Exposers for the protected helper. The constructors register WP hooks and
 * read settings, so instances are built without them.
 */
class FieldDataSpeaker extends Speaker {
	public function __construct() {}
	public function submitted( $field ) {
		return $this->get_submitted_field_data( $field );
	}
}

class FieldDataSeries extends ItemType {
	public function __construct() {}
	public function submitted( $field ) {
		return $this->get_submitted_field_data( $field );
	}
}

class FieldDataServiceType extends ServiceType {
	public function __construct() {}
	public function submitted( $field ) {
		return $this->get_submitted_field_data( $field );
	}
}

/**
 * @covers \CP_Library\Setup\PostTypes\Speaker::get_submitted_field_data
 * @covers \CP_Library\Setup\PostTypes\ItemType::get_submitted_field_data
 * @covers \CP_Library\Setup\PostTypes\ServiceType::get_submitted_field_data
 */
class AbsentFieldClearGuardTest extends TestCase {

	const NONCE = 'nonce_CMB2phpcpl_test_box';

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * The three setup classes carry an identical copy of the helper (they cannot
	 * share one without a ChurchPlugins core release), so every case runs against
	 * all three to catch the copies drifting apart.
	 */
	public function classes() {
		return [
			'speaker'      => [ FieldDataSpeaker::class, 'cpl_speaker' ],
			'series'       => [ FieldDataSeries::class, 'cpl_series' ],
			'service type' => [ FieldDataServiceType::class, 'cpl_service_type' ],
		];
	}

	private function unit( $class ) {
		return ( new ReflectionClass( $class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * A stand-in for CMB2_Field carrying only what the helper reads.
	 */
	private function field( $key, array $data_to_save ) {
		return new class( $key, $data_to_save ) {
			public $data_to_save;
			public $cmb_id = 'cpl_test_box';
			public $object_id = 42;
			private $key;

			public function __construct( $key, $data ) {
				$this->key          = $key;
				$this->data_to_save = $data;
			}

			public function id( $raw = false ) {
				return $this->key;
			}
		};
	}

	private function stub_metabox_with_nonce() {
		$cmb = new class() {
			public function nonce() {
				return AbsentFieldClearGuardTest::NONCE;
			}
		};

		Functions\when( 'cmb2_get_metabox' )->justReturn( $cmb );
	}

	/**
	 * @dataProvider classes
	 */
	public function test_a_submitted_value_is_returned_as_is( $class, $key ) {
		$this->assertSame(
			[ '4' ],
			$this->unit( $class )->submitted( $this->field( $key, [ $key => [ '4' ], self::NONCE => 'abc' ] ) ),
			'a value present in the save data must be applied'
		);
	}

	/**
	 * @dataProvider classes
	 */
	public function test_the_boxes_own_submission_may_clear_by_omission( $class, $key ) {
		$this->stub_metabox_with_nonce();

		$this->assertSame(
			[],
			$this->unit( $class )->submitted( $this->field( $key, [ self::NONCE => 'abc', 'post_title' => 'A Sermon' ] ) ),
			'an emptied multiselect posts nothing but its box nonce — that is a real clear'
		);
	}

	/**
	 * @dataProvider classes
	 */
	public function test_a_save_without_the_field_changes_nothing( $class, $key ) {
		$this->stub_metabox_with_nonce();

		$this->assertNull(
			$this->unit( $class )->submitted( $this->field( $key, [ 'some_other_field' => 'value' ] ) ),
			'a partial save that never included the field must not clear associations'
		);
	}

	/**
	 * @dataProvider classes
	 */
	public function test_an_unregistered_box_changes_nothing( $class, $key ) {
		// cmb2_get_metabox() returns false when the box id is unknown.
		Functions\when( 'cmb2_get_metabox' )->justReturn( false );

		$this->assertNull(
			$this->unit( $class )->submitted( $this->field( $key, [ 'some_other_field' => 'value' ] ) )
		);
	}
}
