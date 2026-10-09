<?php
/**
 * Tests for transcript text included in item output.
 *
 * Item output includes a transcript when transcripts are shown. When they are
 * set to hidden, the text is included only for a user who can edit the item.
 * Everyone else receives an empty string.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Library\Controllers\Item;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \CP_Library\Controllers\Item::transcript_for_output
 * @covers \CP_Library\Controllers\Item::rest_transcript_field
 * @covers \CP_Library\Controllers\Item::get_transcript
 */
class TranscriptOutputTest extends TestCase {

	/** @var string */
	private $stored = 'Sunday sermon transcript';

	/** @var mixed */
	private $show_transcript = 0;

	/** @var bool */
	private $can_edit = false;

	/** @var bool */
	private $publicly_viewable = true;

	/** @var bool */
	private $password_required = false;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$test = $this;

		Functions\when( 'get_option' )->alias(
			static function ( $key, $default = [] ) use ( $test ) {
				if ( 'cpl_item_options' === $key ) {
					return [ 'show_transcript' => $test->showTranscript() ];
				}

				return $default;
			}
		);

		Functions\when( 'apply_filters' )->alias(
			static function ( $tag, $value ) {
				return $value;
			}
		);

		Functions\when( 'get_post_meta' )->alias(
			static function ( $post_id, $key = '', $single = false ) use ( $test ) {
				if ( 15 === (int) $post_id && 'transcript' === $key ) {
					return $test->storedTranscript();
				}

				return '';
			}
		);

		Functions\when( 'current_user_can' )->alias(
			static function ( $cap, $post_id = 0 ) use ( $test ) {
				return $test->canEdit() && 'edit_post' === $cap && 15 === (int) $post_id;
			}
		);

		Functions\when( 'is_post_publicly_viewable' )->alias(
			static function ( $post_id ) use ( $test ) {
				return $test->publiclyViewable() && 15 === (int) $post_id;
			}
		);

		Functions\when( 'post_password_required' )->alias(
			static function () use ( $test ) {
				return $test->passwordRequired();
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function showTranscript() {
		return $this->show_transcript;
	}

	public function storedTranscript() {
		return $this->stored;
	}

	public function canEdit() {
		return $this->can_edit;
	}

	public function publiclyViewable() {
		return $this->publicly_viewable;
	}

	public function passwordRequired() {
		return $this->password_required;
	}

	public function test_hidden_transcript_is_absent_for_anonymous() {
		$this->show_transcript = 0;
		$this->can_edit        = false;

		$this->assertSame( '', Item::transcript_for_output( 15 ) );
		$this->assertSame( '', Item::rest_transcript_field( [ 'id' => 15 ] ) );
		$this->assertSame( '', $this->item()->get_transcript() );
	}

	public function test_hidden_transcript_is_present_for_an_editor() {
		$this->show_transcript = 0;
		$this->can_edit        = true;

		$this->assertSame( $this->stored, Item::transcript_for_output( 15 ) );
		$this->assertSame( $this->stored, Item::rest_transcript_field( [ 'id' => 15 ] ) );
		$this->assertSame( $this->stored, $this->item()->get_transcript() );
	}

	public function test_shown_transcript_is_present_without_edit_access() {
		$this->show_transcript = 1;
		$this->can_edit        = false;

		$this->assertSame( $this->stored, Item::transcript_for_output( 15 ) );
		$this->assertSame( $this->stored, Item::rest_transcript_field( [ 'id' => 15 ] ) );
		$this->assertSame( $this->stored, $this->item()->get_transcript() );
	}

	public function test_transcript_is_absent_when_the_item_is_not_public() {
		$this->show_transcript   = 1;
		$this->can_edit          = false;
		$this->publicly_viewable = false;

		$this->assertSame( '', Item::transcript_for_output( 15 ) );
		$this->assertSame( '', $this->item()->transcript_for_api( true ) );
	}

	public function test_transcript_is_absent_when_a_password_is_required() {
		$this->show_transcript    = 1;
		$this->can_edit           = false;
		$this->password_required  = true;

		$this->assertSame( '', Item::transcript_for_output( 15 ) );
	}

	public function test_editor_receives_a_transcript_for_a_non_public_item() {
		$this->show_transcript   = 0;
		$this->can_edit          = true;
		$this->publicly_viewable = false;

		$this->assertSame( $this->stored, Item::transcript_for_output( 15 ) );
	}

	public function test_list_records_omit_the_transcript() {
		$this->show_transcript = 1;
		$this->can_edit        = true;

		$item = $this->item();

		$this->assertSame( '', $item->transcript_for_api( false ) );
		$this->assertSame( $this->stored, $item->transcript_for_api( true ) );
	}

	/**
	 * @return Item
	 */
	private function item() {
		$item        = ( new ReflectionClass( Item::class ) )->newInstanceWithoutConstructor();
		$item->post  = (object) [ 'ID' => 15 ];
		$item->model = new class() {
			public static function get_prop( $prop ) {
				return 'cpl_item';
			}
		};

		return $item;
	}
}
