<?php
/**
 * Tests for the title SermonAudio imports choose.
 *
 * SermonAudio sends two titles. displayTitle is abbreviated, often with a
 * trailing "...", so the parts of one series were imported as the same title
 * (and, for new sermons, the same slug stem). fullTitle is the complete title
 * and is nullable. The import must use fullTitle when it is a non-empty
 * string, and displayTitle otherwise — including when fullTitle is null, "",
 * or whitespace only. The returned title is trim()'d, so surrounding
 * whitespace is dropped and whitespace between words is kept. A long
 * A long title is kept aside from that trim. When both titles are missing or
 * blank, resolve_sermon_title() returns "". "Untitled sermon" is not part
 * of the formatted item: that fallback is only for a sermon that does not
 * exist yet, and putting it in the hashed payload would not match the title
 * an existing sermon keeps.
 *
 * The sync hash is the formatted item. Two fetches of the same blank payload
 * hash the same, so a sermon is not re-queued on every sync just because
 * SermonAudio sent no title. A real title change still changes the hash.
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

	public function test_blank_titles_stay_empty_in_the_formatted_item() {
		$sermon = $this->sermon(
			array(
				'displayTitle' => null,
				'fullTitle'    => '   ',
				'sermonID'     => 'sa-99',
			)
		);

		$this->assertSame( '', $this->adapter->format_item( $sermon )['post_title'] );
		$this->assertNotSame( 'sa-99', $this->adapter->format_item( $sermon )['post_title'] );
		$this->assertNotSame( 'Untitled sermon', $this->adapter->format_item( $sermon )['post_title'] );
	}

	public function test_null_display_title_and_null_full_title_stay_empty() {
		$sermon = $this->sermon(
			array(
				'displayTitle' => null,
				'fullTitle'    => null,
				'sermonID'     => 'sa-null-both',
			)
		);

		$this->assertSame( '', $this->adapter->format_item( $sermon )['post_title'] );
	}

	public function test_null_display_title_and_empty_full_title_stay_empty() {
		$sermon = $this->sermon(
			array(
				'displayTitle' => null,
				'fullTitle'    => '',
				'sermonID'     => 'sa-empty-full',
			)
		);

		$this->assertSame( '', $this->adapter->format_item( $sermon )['post_title'] );
	}

	public function test_blank_titles_and_no_sermon_id_stay_empty() {
		$sermon = $this->sermon(
			array(
				'displayTitle' => null,
				'fullTitle'    => null,
			)
		);
		unset( $sermon->sermonID );

		$this->assertSame( '', SermonAudio::resolve_sermon_title( $sermon ) );
	}

	public function test_surrounding_whitespace_is_trimmed_and_inner_whitespace_is_kept() {
		$sermon = $this->sermon(
			array(
				'displayTitle' => 'short',
				'fullTitle'    => "  Gathering  Together \n",
			)
		);

		$this->assertSame( 'Gathering  Together', $this->adapter->format_item( $sermon )['post_title'] );
	}

	public function test_a_blank_full_title_returns_the_trimmed_display_title() {
		$sermon = $this->sermon(
			array(
				'displayTitle' => "  Abbreviated   Title  ",
				'fullTitle'    => '   ',
			)
		);

		$this->assertSame( 'Abbreviated   Title', SermonAudio::resolve_sermon_title( $sermon ) );
	}

	public function test_a_blank_payload_hashes_the_same_on_every_fetch() {
		$first  = $this->adapter->format_item(
			$this->sermon(
				array(
					'displayTitle' => null,
					'fullTitle'    => '',
				)
			)
		);
		$second = $this->adapter->format_item(
			$this->sermon(
				array(
					'displayTitle' => " \t ",
					'fullTitle'    => null,
				)
			)
		);

		$this->assertSame( '', $first['post_title'] );
		$this->assertSame( '', $second['post_title'] );
		$this->assertSame(
			$this->adapter->create_store_key( $first ),
			$this->adapter->create_store_key( $second ),
			'a blank title must not change the sync hash between fetches'
		);

		$as_untitled               = $first;
		$as_untitled['post_title'] = 'Untitled sermon';
		$this->assertNotSame(
			$this->adapter->create_store_key( $first ),
			$this->adapter->create_store_key( $as_untitled )
		);
	}

	public function test_a_very_long_full_title_is_kept_intact() {
		$long = str_repeat( 'Gathering Together Part One ', 20 );
		$sermon = $this->sermon(
			array(
				'displayTitle' => 'Gathering Together Part...',
				'fullTitle'    => $long,
			)
		);

		$this->assertGreaterThan( 200, strlen( $long ), 'the fixture itself must be long' );
		$this->assertSame( trim( $long ), $this->adapter->format_item( $sermon )['post_title'] );
		$this->assertNotSame( $long, $this->adapter->format_item( $sermon )['post_title'], 'surrounding whitespace is trimmed' );
		$this->assertStringContainsString( 'Part One Gathering', $this->adapter->format_item( $sermon )['post_title'] );
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
