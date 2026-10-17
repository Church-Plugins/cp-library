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
use CP_Library\Admin\Import\ImportSermons;
use CP_Library\Setup\Taxonomies\Scripture;
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
			'Mal 3:1'                  => 'Malachi 3:1',
			'Malachi 3:1'              => 'Malachi 3:1',
			'Mark 1:1'                 => 'Mark 1:1',
			'Ezek 37'                  => 'Ezekiel 37',
			'Ezekiel 37'               => 'Ezekiel 37',
			'Ezra 7'                   => 'Ezra 7',
			'Lam 3:22'                 => 'Lamentations 3:22',
			'Lamentations 3'           => 'Lamentations 3',
			'Ex 20:1'                  => 'Exodus 20:1',
			'Exodus 20'                => 'Exodus 20',
			'1Cor 13'                  => '1 Corinthians 13',
			'1Cor. 13:4'               => '1 Corinthians 13:4',
			'1Corinthians 13'          => '1 Corinthians 13',
			'2Co 5:17'                 => '2 Corinthians 5:17',
			'2Cor 5:17'                => '2 Corinthians 5:17',
			'1Jn 1:9'                  => '1 John 1:9',
			'2Jn 1'                    => '2 John 1',
			'3Jn 1'                    => '3 John 1',
			'1John 1:9'                => '1 John 1:9',
			'First Corinthians 13'     => '1 Corinthians 13',
			'Second Corinthians 5:17'  => '2 Corinthians 5:17',
			'Third John 1'             => '3 John 1',
			'1st Corinthians 13'       => '1 Corinthians 13',
			'2nd Samuel 7'             => '2 Samuel 7',
			'3rd John 1'               => '3 John 1',
			'First John 1:9'           => '1 John 1:9',
			'Second John 1'            => '2 John 1',
			'1stCor 13'                => '1 Corinthians 13',
			'Psalm 23:1, 4'            => 'Psalms 23:1, 4',
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

	public function test_csv_scripture_cell_normalizes_each_comma_separated_reference() {
		$this->assertSame(
			array( 'Psalms 23', 'John 3:16' ),
			ImportSermons::scripture_passages( 'Ps 23, Jn 3:16' )
		);
		$this->assertSame(
			array( 'Psalms 23' ),
			ImportSermons::scripture_passages( 'Psalm 23' )
		);
		$this->assertSame(
			array( '1 Corinthians 13', '2 Corinthians 5:17', '1 John 1:9' ),
			ImportSermons::scripture_passages( '1Cor 13, 2Co 5:17, 1Jn 1:9' )
		);
		$this->assertSame(
			array( '1 Corinthians 13', '3 John 1' ),
			ImportSermons::scripture_passages( 'First Corinthians 13, 3rd John 1' )
		);
		$this->assertSame( array(), ImportSermons::scripture_passages( ' , ' ) );
	}

	/**
	 * A comma inside one reference is a verse list. The shared rewriter leaves
	 * it as one passage. The CSV column split is separate, and it does break
	 * that verse list, because the importer already separated every comma.
	 */
	public function test_a_verse_list_comma_stays_one_passage_until_the_csv_split() {
		$this->assertSame( 'Psalms 23:1, 4', SermonAudio::normalize_scripture_passage( 'Psalm 23:1, 4' ) );
		$this->assertSame(
			array( 'Psalms 23:1', '4' ),
			ImportSermons::scripture_passages( 'Psalm 23:1, 4' )
		);
	}

	public function test_admin_save_keeps_a_psalm_reference() {
		$saved = null;
		$terms = null;

		$templates = new class( dirname( __DIR__, 2 ) ) {
			/** @var string */
			private $root;

			public function __construct( $root ) {
				$this->root = $root;
			}

			public function get_template_hierarchy( $template, $args = array() ) {
				return $this->root . '/templates/' . $template;
			}
		};

		$library           = new \stdClass();
		$library->templates = $templates;
		Functions\when( 'cp_library' )->justReturn( $library );
		Functions\when( 'apply_filters' )->alias(
			static function ( $tag, $value ) {
				return $value;
			}
		);
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'get_term_by' )->alias(
			static function ( $field, $name ) {
				return (object) array(
					'term_id' => 42,
					'name'    => $name,
				);
			}
		);
		Functions\when( 'update_post_meta' )->alias(
			static function ( $post_id, $key, $value ) use ( &$saved ) {
				$saved = $value;
				return true;
			}
		);
		Functions\when( 'wp_set_object_terms' )->alias(
			static function ( $post_id, $term_ids ) use ( &$terms ) {
				$terms = $term_ids;
				return $term_ids;
			}
		);

		$scripture           = ( new ReflectionClass( Scripture::class ) )->newInstanceWithoutConstructor();
		$scripture->taxonomy = 'cpl_scripture';

		$scripture->update_object_scripture( 15, array( 'Psalm 23', 'Jn 3:16', 'Not a book 1' ) );

		$this->assertSame( array( 'Psalms 23', 'John 3:16' ), array_values( $saved ) );
		$this->assertSame( array( 42, 42 ), $terms );
	}
}
