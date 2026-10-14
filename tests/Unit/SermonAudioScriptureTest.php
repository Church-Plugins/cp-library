<?php
/**
 * Tests for SermonAudio scripture book names.
 *
 * SermonAudio spells the psalter "Psalm". Our book list names it "Psalms".
 * Scripture::get_book() keeps a passage only when the text starts with a name
 * from that list, and update_object_scripture() drops the passage when it
 * does not. "Psalm 46" was thrown away. The import rewrites the leading book
 * to the name we store before the passage is saved.
 *
 * The same rewrite covers the other singular/plural mismatch and the usual
 * abbreviations. It must not fold a numbered book into a different number:
 * "2 Corinthians" stays 2 Corinthians, and "1 John" stays 1 John.
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
 * @covers \CP_Library\Adapters\SermonAudio::normalize_scripture_passage
 * @covers \CP_Library\Adapters\SermonAudio::normalize_bible_text
 * @covers \CP_Library\Adapters\SermonAudio::format_item
 */
class SermonAudioScriptureTest extends TestCase {

	/** @var SermonAudio */
	private $adapter;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
			define( 'HOUR_IN_SECONDS', 3600 );
		}

		Functions\when( 'wp_kses_post' )->returnArg();

		$item_type            = new \stdClass();
		$item_type->post_type = 'cpl_item';
		$post_types           = new \stdClass();
		$post_types->item     = $item_type;
		$setup                = new \stdClass();
		$setup->post_types    = $post_types;
		$library              = new \stdClass();
		$library->setup       = $setup;

		Functions\when( 'cp_library' )->justReturn( $library );

		$this->adapter = ( new ReflectionClass( SermonAudio::class ) )->newInstanceWithoutConstructor();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_psalm_is_rewritten_to_psalms_and_known_aliases_follow() {
		$cases = array(
			'Psalm 46'                 => 'Psalms 46',
			'Psalm 55:22'              => 'Psalms 55:22',
			'psalm 23'                 => 'Psalms 23',
			'PSALM 46:1-3'             => 'Psalms 46:1-3',
			'  Psalm 1  '              => 'Psalms 1',
			'Psalm'                    => 'Psalms',
			'Psalms 23'                => 'Psalms 23',
			'Ps 23'                    => 'Psalms 23',
			'Ps. 23'                   => 'Psalms 23',
			'Psa 119:105'              => 'Psalms 119:105',
			'Song of Songs 2:1'        => 'Song of Solomon 2:1',
			'Song of Solomon 2:1'      => 'Song of Solomon 2:1',
			'Canticles 1:1'            => 'Song of Solomon 1:1',
			'Revelations 21:1'         => 'Revelation 21:1',
			'Revelation 21:1'          => 'Revelation 21:1',
			'Rev. 22:21'               => 'Revelation 22:21',
			'1 Cor 13'                 => '1 Corinthians 13',
			'1 Cor. 13:4-7'            => '1 Corinthians 13:4-7',
			'2 Cor 5:17'               => '2 Corinthians 5:17',
			'1 Corinthians 13'         => '1 Corinthians 13',
			'2 Corinthians 5:17'       => '2 Corinthians 5:17',
			'II Corinthians 5:17'      => '2 Corinthians 5:17',
			'John 3:16'                => 'John 3:16',
			'1 John 1:9'               => '1 John 1:9',
			'2 John 1'                 => '2 John 1',
			'3 John 1'                 => '3 John 1',
			'Jn 1:1'                   => 'John 1:1',
			'1 Jn 1:9'                 => '1 John 1:9',
			'Jude 1'                   => 'Jude 1',
			'Judges 6'                 => 'Judges 6',
			'Philemon 1'               => 'Philemon 1',
			'Phil 4:4'                 => 'Philippians 4:4',
			'Philippians 4:4'          => 'Philippians 4:4',
			'Gen 1:1'                  => 'Genesis 1:1',
			'Matt 5:1'                 => 'Matthew 5:1',
			'Genesis 1:1'              => 'Genesis 1:1',
			'Not a book 1:1'           => 'Not a book 1:1',
		);

		foreach ( $cases as $input => $expected ) {
			$this->assertSame( $expected, SermonAudio::normalize_scripture_passage( $input ), $input );
		}
	}

	public function test_a_psalm_reference_starts_with_the_book_name_we_store() {
		$normalized = SermonAudio::normalize_scripture_passage( 'Psalm 46' );

		$this->assertSame( 0, strpos( strtolower( $normalized ), 'psalms' ) );
		$this->assertNotSame( 0, strpos( 'psalm 46', 'psalms' ) );
	}

	public function test_several_references_are_normalized_and_blank_pieces_dropped() {
		$this->assertSame(
			'Psalms 23; John 3:16; 1 Corinthians 13',
			SermonAudio::normalize_bible_text( 'Psalm 23; John 3:16; 1 Cor 13' )
		);
		$this->assertSame( 'Psalms 23', SermonAudio::normalize_bible_text( 'Psalm 23;' ) );
		$this->assertSame( '', SermonAudio::normalize_bible_text( '   ;  ' ) );
		$this->assertNull( SermonAudio::normalize_bible_text( null ) );
	}

	public function test_format_item_stores_the_normalized_scripture() {
		$sermon = (object) array(
			'sermonID'         => 'sa-psalm',
			'displayTitle'     => 'A Psalm',
			'fullTitle'        => 'Cast thy burden upon the LORD',
			'preachDate'       => '2024-01-07',
			'publishTimestamp' => 1700000000,
			'moreInfoText'     => '',
			'hasAudio'         => false,
			'hasVideo'         => false,
			'bibleText'        => 'Psalm 55:22; 1 Cor. 13',
			'eventType'        => null,
		);

		$formatted = $this->adapter->format_item( $sermon );

		$this->assertSame( 'Psalms 55:22; 1 Corinthians 13', $formatted['cpl_data']['scripture'] );
		$this->assertSame( 'Cast thy burden upon the LORD', $formatted['post_title'] );
	}
}
