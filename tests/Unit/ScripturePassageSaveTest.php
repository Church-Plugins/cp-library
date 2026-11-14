<?php
/**
 * Save and book-parse checks for two reports that are not the same-book
 * display bug.
 *
 * One report: a sermon whose passages name different books shows only the
 * first passage ("Hebrews 1; Psalms 45" shows Hebrews 1, "1 Peter 2:13-17;
 * Romans 13" shows 1 Peter, "2 Corinthians 4:7-11; Judges 7:1-7" shows
 * 2 Corinthians). These tests save those strings on a clean post through
 * Scripture::update_object_scripture(), which is the edit-screen, CSV, and
 * SermonAudio write.
 *
 * The other report: a whole book or a chapter range (Ruth, Ezra, Joshua,
 * Numbers, Nehemiah 1-4, 1 Samuel 1-15, 1 Kings, 2 Kings), and sometimes a
 * blank field, was stored as Judges. These tests ask get_book() and the
 * same save what those strings become.
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
 * @covers \CP_Library\Setup\Taxonomies\Scripture::update_object_scripture
 * @covers \CP_Library\Setup\Taxonomies\Scripture::get_book
 */
class ScripturePassageSaveTest extends TestCase {

	/** @var Scripture */
	private $scripture;

	/** @var mixed */
	private $saved;

	/** @var bool */
	private $deleted = false;

	/** @var array<int,mixed> */
	private $terms = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$saved   = &$this->saved;
		$deleted = &$this->deleted;
		$terms   = &$this->terms;

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

		$library            = new \stdClass();
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
					'term_id' => $name,
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
		Functions\when( 'delete_post_meta' )->alias(
			static function () use ( &$deleted ) {
				$deleted = true;
				return true;
			}
		);
		Functions\when( 'wp_delete_object_term_relationships' )->justReturn( true );
		Functions\when( 'wp_set_object_terms' )->alias(
			static function ( $post_id, $term_ids ) use ( &$terms ) {
				$terms = $term_ids;
				return $term_ids;
			}
		);

		$this->scripture           = ( new ReflectionClass( Scripture::class ) )->newInstanceWithoutConstructor();
		$this->scripture->taxonomy = 'cpl_scripture';
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @dataProvider different_book_fields
	 *
	 * @param string   $field    Raw scripture text.
	 * @param string[] $expected Passages after a semicolon split.
	 */
	public function test_a_split_list_of_different_books_is_stored_in_full( $field, array $expected ) {
		$this->assertSame( $expected, ImportSermons::scripture_passages( $field ) );
		$this->assertSame( implode( '; ', $expected ), SermonAudio::normalize_bible_text( $field ) );

		$pieces = array_map( 'trim', explode( ';', SermonAudio::normalize_bible_text( $field ) ) );
		$this->save( $pieces );

		$this->assertFalse( $this->deleted );
		$this->assertSame( $expected, array_values( $this->saved ) );
		$this->assertNotContains( 'Judges', $this->saved );
		$this->assertSame(
			array_map(
				array( $this->scripture, 'get_book' ),
				$expected
			),
			array_values( $this->terms )
		);
	}

	/**
	 * @dataProvider different_book_fields
	 *
	 * @param string   $field    Raw scripture text, saved as one value.
	 * @param string[] $expected Unused. The provider is shared with the split-list test.
	 */
	public function test_an_unsplit_different_book_string_stays_intact( $field, array $expected ) {
		$this->assertGreaterThan( 1, count( $expected ) );

		$this->save( $field );

		$this->assertSame( array( $field ), array_values( $this->saved ) );
		$this->assertNotSame( array( 'Judges' ), array_values( $this->saved ) );
	}

	/**
	 * @return array<string,array{0:string,1:string[]}>
	 */
	public function different_book_fields() {
		return array(
			'hebrews and psalms'     => array( 'Hebrews 1; Psalms 45', array( 'Hebrews 1', 'Psalms 45' ) ),
			'peter and romans'       => array( '1 Peter 2:13-17; Romans 13', array( '1 Peter 2:13-17', 'Romans 13' ) ),
			'corinthians and judges' => array( '2 Corinthians 4:7-11; Judges 7:1-7', array( '2 Corinthians 4:7-11', 'Judges 7:1-7' ) ),
		);
	}

	/**
	 * @dataProvider old_testament_references
	 *
	 * @param string $passage Passage text.
	 * @param string $book    Book get_book() should return.
	 */
	public function test_book_and_chapter_range_references_do_not_become_judges( $passage, $book ) {
		$this->assertSame( $passage, SermonAudio::normalize_scripture_passage( $passage ) );
		$this->assertSame( $book, $this->scripture->get_book( $passage ) );
		$this->assertNotSame( 'Judges', $this->scripture->get_book( $passage ) );

		$this->save( array( $passage ) );

		$this->assertSame( array( $passage ), array_values( $this->saved ) );
		$this->assertSame( array( $book ), array_values( $this->terms ) );
	}

	/**
	 * @return array<string,array{0:string,1:string}>
	 */
	public function old_testament_references() {
		return array(
			'Ruth'            => array( 'Ruth', 'Ruth' ),
			'Ezra'            => array( 'Ezra', 'Ezra' ),
			'Joshua'          => array( 'Joshua', 'Joshua' ),
			'Numbers'         => array( 'Numbers', 'Numbers' ),
			'Nehemiah 1-4'    => array( 'Nehemiah 1-4', 'Nehemiah' ),
			'1 Samuel 1-15'   => array( '1 Samuel 1-15', '1 Samuel' ),
			'1 Kings'         => array( '1 Kings', '1 Kings' ),
			'2 Kings'         => array( '2 Kings', '2 Kings' ),
		);
	}

	public function test_one_and_two_kings_is_omitted_and_not_stored_as_judges() {
		$this->assertFalse( $this->scripture->get_book( '1 and 2 Kings' ) );
		$this->assertSame( '1 and 2 Kings', SermonAudio::normalize_scripture_passage( '1 and 2 Kings' ) );

		$this->save( array( '1 and 2 Kings' ) );

		$this->assertSame( array(), array_values( $this->saved ) );
		$this->assertNotContains( 'Judges', $this->saved );
		$this->assertSame( array(), $this->terms );
	}

	public function test_a_blank_field_clears_scripture_and_does_not_store_judges() {
		$this->save( '' );

		$this->assertTrue( $this->deleted );
		$this->assertNull( $this->saved );

		$this->deleted = false;
		$this->saved   = null;
		$this->save( array( '', '   ' ) );

		$this->assertFalse( $this->deleted );
		$this->assertSame( array(), array_values( $this->saved ) );
		$this->assertNotContains( 'Judges', $this->saved );
		$this->assertSame( array(), $this->terms );
	}

	/**
	 * @param mixed $passages Passage or passages to save.
	 */
	private function save( $passages ) {
		$this->saved   = null;
		$this->deleted = false;
		$this->terms   = array();

		$this->scripture->update_object_scripture( 15, $passages );
	}
}
