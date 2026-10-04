<?php
/**
 * Tests for the title SermonAudio imports choose.
 *
 * SermonAudio sends two titles. displayTitle is abbreviated, often with a
 * trailing "...", so the parts of one series were imported as the same title
 * (and, for new sermons, the same slug stem). fullTitle is the complete title
 * and is nullable. The import must use fullTitle when it is a non-empty
 * string, and displayTitle otherwise — including when fullTitle is null, "",
 * or whitespace only (fullTitle is trim()'d, and a whitespace-only result
 * falls back). Nothing in that choice truncates the title; a long fullTitle
 * is stored whole, including surrounding spaces when the rest is not blank.
 * When both titles are missing or blank, the title is "Untitled sermon".
 * The SermonAudio sermon id is not used as the title.
 *
 * The sync hash is the formatted item, so a title change re-queues a sermon
 * that was already imported. That re-queue is what retitles it.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Library\Adapters\SermonAudio;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \CP_Library\Adapters\SermonAudio::resolve_sermon_title
 * @covers \CP_Library\Adapters\SermonAudio::format_item
 * @covers \CP_Library\Adapters\Adapter::create_store_key
 */
class SermonAudioTitleTest extends TestCase {

	/** @var SermonAudio */
	private $adapter;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
			define( 'HOUR_IN_SECONDS', 3600 );
		}

		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( '__' )->returnArg();

		$item_type            = new \stdClass();
		$item_type->post_type = 'cpl_item';
		$post_types           = new \stdClass();
		$post_types->item     = $item_type;
		$setup                = new \stdClass();
		$setup->post_types    = $post_types;
		$library              = new \stdClass();
		$library->setup       = $setup;

		Functions\when( 'cp_library' )->justReturn( $library );

		// The constructor registers cron hooks and reads settings. format_item()
		// does not use instance state.
		$this->adapter = ( new ReflectionClass( SermonAudio::class ) )->newInstanceWithoutConstructor();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_full_title_is_used_when_both_fields_are_present() {
		$sermon = $this->sermon(
			array(
				'displayTitle' => 'Example Sermon About Gather...',
				'fullTitle'    => 'Example Sermon About Gathering Together, Part 1',
			)
		);

		$this->assertSame(
			'Example Sermon About Gathering Together, Part 1',
			$this->adapter->format_item( $sermon )['post_title']
		);
	}

	public function test_null_full_title_falls_back_to_display_title() {
		$sermon = $this->sermon(
			array(
				'displayTitle' => 'A Sermon With No Long Title',
				'fullTitle'    => null,
			)
		);

		$this->assertSame( 'A Sermon With No Long Title', $this->adapter->format_item( $sermon )['post_title'] );
	}

	public function test_empty_full_title_falls_back_to_display_title() {
		$sermon = $this->sermon(
			array(
				'displayTitle' => 'A Sermon With No Long Title',
				'fullTitle'    => '',
			)
		);

		$this->assertSame( 'A Sermon With No Long Title', $this->adapter->format_item( $sermon )['post_title'] );
	}

	public function test_whitespace_only_full_title_falls_back_to_display_title() {
		$sermon = $this->sermon(
			array(
				'displayTitle' => 'A Sermon With No Long Title',
				'fullTitle'    => " \n\t ",
			)
		);

		$this->assertSame( 'A Sermon With No Long Title', $this->adapter->format_item( $sermon )['post_title'] );
	}

	public function test_blank_titles_fall_back_to_untitled_sermon() {
		$sermon = $this->sermon(
			array(
				'displayTitle' => null,
				'fullTitle'    => '   ',
				'sermonID'     => 'sa-99',
			)
		);

		$this->assertSame( 'Untitled sermon', $this->adapter->format_item( $sermon )['post_title'] );
		$this->assertNotSame( 'sa-99', $this->adapter->format_item( $sermon )['post_title'] );
	}

	public function test_null_display_title_and_null_full_title_use_untitled_sermon() {
		$sermon = $this->sermon(
			array(
				'displayTitle' => null,
				'fullTitle'    => null,
				'sermonID'     => 'sa-null-both',
			)
		);

		$this->assertSame( 'Untitled sermon', $this->adapter->format_item( $sermon )['post_title'] );
	}

	public function test_null_display_title_and_empty_full_title_use_untitled_sermon() {
		$sermon = $this->sermon(
			array(
				'displayTitle' => null,
				'fullTitle'    => '',
				'sermonID'     => 'sa-empty-full',
			)
		);

		$this->assertSame( 'Untitled sermon', $this->adapter->format_item( $sermon )['post_title'] );
	}

	public function test_blank_titles_and_no_sermon_id_still_use_untitled_sermon() {
		$sermon = $this->sermon(
			array(
				'displayTitle' => null,
				'fullTitle'    => null,
			)
		);
		unset( $sermon->sermonID );

		$this->assertSame( 'Untitled sermon', SermonAudio::resolve_sermon_title( $sermon ) );
	}

	public function test_a_very_long_full_title_is_kept_intact() {
		$long   = str_repeat( 'Gathering Together Part One ', 20 );
		$sermon = $this->sermon(
			array(
				'displayTitle' => 'Gathering Together Part...',
				'fullTitle'    => $long,
			)
		);

		$this->assertGreaterThan( 200, strlen( $long ), 'the fixture itself must be long' );
		$this->assertSame( $long, $this->adapter->format_item( $sermon )['post_title'] );
	}

	public function test_a_title_change_changes_the_sync_hash_so_the_sermon_is_requeued() {
		$truncated = $this->adapter->format_item(
			$this->sermon(
				array(
					'displayTitle' => 'Example Sermon About Gather...',
					'fullTitle'    => 'Example Sermon About Gather...',
				)
			)
		);
		$complete  = $this->adapter->format_item(
			$this->sermon(
				array(
					'displayTitle' => 'Example Sermon About Gather...',
					'fullTitle'    => 'Example Sermon About Gathering Together, Part 1',
				)
			)
		);

		$this->assertNotSame(
			$this->adapter->create_store_key( $truncated ),
			$this->adapter->create_store_key( $complete ),
			'the stored hash includes the title, so a fetched sermon whose title changed is queued once'
		);
	}

	/**
	 * A SermonAudio sermon payload with the fields format_item() reads.
	 *
	 * @param array $overrides
	 * @return object
	 */
	private function sermon( array $overrides ) {
		return (object) array_merge(
			array(
				'sermonID'         => 'sa-1',
				'displayTitle'     => 'Example Sermon About Gather...',
				'fullTitle'        => null,
				'preachDate'       => '2024-01-07',
				'publishTimestamp' => 1700000000,
				'moreInfoText'     => '',
				'hasAudio'         => false,
				'hasVideo'         => false,
				'bibleText'        => null,
				'eventType'        => null,
			),
			$overrides
		);
	}
}
