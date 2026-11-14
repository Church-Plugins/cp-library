<?php
/**
 * Several passages from the same book were shown as only the last one.
 *
 * The passage text in post meta is complete, and the sermon edit screen lists
 * every passage. Item::get_scripture() is what the single page, the list
 * view, the Sermon Scripture block, and the item REST payload all read. It
 * stored each passage under the book term's slug, so a later passage from
 * that book replaced the earlier one. Hebrews 1 and Hebrews 2 came back as
 * Hebrews 2. John 15:18 and John 16:4 came back as John 16:4. Daniel 8:13,
 * Daniel 9:17, and Daniel 9:24-27 came back as Daniel 9:24-27, while Luke
 * (a different book) was kept.
 *
 * The list stays an associative array. Keys still start with the book slug,
 * and a repeated book gets a numeric suffix, so JSON encoding keeps an
 * object and a filter callback that reads keys still sees the slug.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Library\Controllers\Item;
use CP_Library\Setup\Taxonomies\Scripture;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Scripture taxonomy stand-in. Book names come from the real book list.
 */
class ScriptureDisplayCatalog {

	/** @var string */
	public $taxonomy = 'cpl_scripture';

	/** @var string[] */
	public $passages = array();

	/** @var object[] */
	public $terms = array();

	/** @var Scripture */
	private $books;

	public function __construct( Scripture $books ) {
		$this->books = $books;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	public function get_object_passages( $post_id ) {
		return $this->passages;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return object[]
	 */
	public function get_object_scripture( $post_id ) {
		return $this->terms;
	}

	/**
	 * @param string $passage Passage text.
	 * @return false|string
	 */
	public function get_book( $passage ) {
		return $this->books->get_book( $passage );
	}
}

/**
 * Item double. Skips the model lookup and the database.
 */
class ScriptureDisplayItem extends Item {

	public function __construct() {
		$this->post  = (object) array(
			'ID'          => 15,
			'post_parent' => 0,
			'post_name'   => 'a-sermon',
		);
		$this->model = (object) array( 'id' => 9 );
	}

	public function filter( $value, $function ) {
		return $value;
	}

	public function get_permalink() {
		return 'https://example.test/sermon/a-sermon/';
	}

	public function get_thumbnail( $context = '' ) {
		return '';
	}

	public function get_title() {
		return 'A sermon';
	}

	public function get_content( $raw = false ) {
		return '';
	}

	public function get_publish_date() {
		return 1700000000;
	}

	public function get_categories() {
		return array();
	}

	public function get_speakers() {
		return array();
	}

	public function get_locations() {
		return array();
	}

	public function get_topics() {
		return array();
	}

	public function get_video() {
		return array(
			'type'   => 'url',
			'value'  => false,
			'marker' => 0,
		);
	}

	public function get_audio() {
		return '';
	}

	public function get_types() {
		return array();
	}

	public function get_service_types() {
		return array();
	}

	public function get_passage() {
		return '';
	}

	public function get_timestamp() {
		return '';
	}

	public function get_downloads() {
		return array();
	}
}

/**
 * @covers \CP_Library\Controllers\Item::get_scripture
 * @covers \CP_Library\Controllers\Item::get_api_data
 */
class ScriptureDisplayTest extends TestCase {

	/** @var ScriptureDisplayCatalog */
	private $catalog;

	/** @var ScriptureDisplayItem */
	private $item;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

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

		$books           = ( new ReflectionClass( Scripture::class ) )->newInstanceWithoutConstructor();
		$books->taxonomy = 'cpl_scripture';
		$this->catalog   = new ScriptureDisplayCatalog( $books );

		$taxonomies             = new \stdClass();
		$taxonomies->scripture  = $this->catalog;
		$setup                  = new \stdClass();
		$setup->taxonomies      = $taxonomies;
		$library                = new \stdClass();
		$library->setup         = $setup;
		$library->templates     = $templates;

		Functions\when( 'cp_library' )->justReturn( $library );
		Functions\when( 'apply_filters' )->alias(
			static function ( $tag, $value ) {
				return $value;
			}
		);
		Functions\when( 'is_wp_error' )->alias(
			static function ( $thing ) {
				return $thing instanceof \WP_Error;
			}
		);
		Functions\when( 'get_term_by' )->alias(
			static function ( $field, $name ) {
				if ( ! is_string( $name ) || '' === $name ) {
					return false;
				}

				$slug = strtolower( str_replace( ' ', '-', $name ) );

				return (object) array(
					'term_id' => $slug,
					'name'    => $name,
					'slug'    => $slug,
				);
			}
		);
		Functions\when( 'get_term_link' )->alias(
			static function ( $term ) {
				return 'https://example.test/scripture/' . $term->slug . '/';
			}
		);
		Functions\when( 'get_post_status' )->justReturn( 'publish' );
		Functions\when( 'get_option' )->justReturn( 'F j, Y' );

		$this->item = new ScriptureDisplayItem();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @dataProvider passage_lists
	 *
	 * @param string[] $passages Passages stored on the sermon, in order.
	 */
	public function test_every_saved_passage_is_returned( array $passages ) {
		$this->catalog->passages = $passages;

		$listed = $this->item->get_scripture();
		$seen   = array();

		$this->assertSame( $passages, array_column( $listed, 'name' ) );

		foreach ( $listed as $key => $row ) {
			$this->assertIsString( $key );
			$this->assertSame( 0, strpos( $key, $row['slug'] ) );
			$this->assertSame( 'https://example.test/scripture/' . $row['slug'] . '/', $row['url'] );

			if ( ! isset( $seen[ $row['slug'] ] ) ) {
				$this->assertSame( $row['slug'], $key, 'the first passage for a book stays under the book slug' );
				$seen[ $row['slug'] ] = true;
			} else {
				$this->assertMatchesRegularExpression(
					'/^' . preg_quote( $row['slug'], '/' ) . '-\d+$/',
					$key
				);
			}
		}
	}

	/**
	 * @return array<string,array{0:string[]}>
	 */
	public function passage_lists() {
		return array(
			'hebrews chapters'       => array( array( 'Hebrews 1', 'Hebrews 2' ) ),
			'john verses'            => array( array( 'John 15:18', 'John 16:4' ) ),
			'daniel and luke'        => array( array( 'Daniel 8:13', 'Daniel 9:17', 'Daniel 9:24-27', 'Luke 21:20-24' ) ),
			'acts and philippians'   => array( array( 'Acts 15:36-41', 'Acts 16:1-15', 'Philippians 1' ) ),
			'romans and galatians'   => array( array( 'Romans 3:21-26', 'Romans 5:1-5', 'Galatians 2:16' ) ),
			'different books'        => array( array( '1 Peter 2:9-10', 'Ephesians 1:3-14' ) ),
			'hebrews and psalms'     => array( array( 'Hebrews 1', 'Psalms 45' ) ),
			'peter and romans'       => array( array( '1 Peter 2:13-17', 'Romans 13' ) ),
			'corinthians and judges' => array( array( '2 Corinthians 4:7-11', 'Judges 7:1-7' ) ),
			'single passage'         => array( array( 'John 3:16' ) ),
		);
	}

	public function test_rest_scripture_field_is_a_json_object() {
		$this->catalog->passages = array( 'Hebrews 1', 'Hebrews 2', 'Luke 21:20-24' );

		$data = $this->item->get_api_data();
		$json = json_encode( $data );

		$this->assertIsString( $json );

		$decoded = json_decode( $json );

		$this->assertIsObject( $decoded->scripture );
		$this->assertStringNotContainsString( '"scripture":[', $json );
		$this->assertSame(
			array( 'Hebrews 1', 'Hebrews 2', 'Luke 21:20-24' ),
			array_map(
				static function ( $row ) {
					return $row->name;
				},
				array_values( get_object_vars( $decoded->scripture ) )
			)
		);
		$this->assertSame(
			array( 'hebrews', 'hebrews-2', 'luke' ),
			array_keys( get_object_vars( $decoded->scripture ) )
		);
		$this->assertSame( 'hebrews', $decoded->scripture->{'hebrews-2'}->slug );
	}

	public function test_terms_without_passage_meta_stay_one_entry_per_book() {
		$this->catalog->passages = array();
		$this->catalog->terms    = array(
			(object) array(
				'name' => 'Hebrews',
				'slug' => 'hebrews',
			),
			(object) array(
				'name' => 'Luke',
				'slug' => 'luke',
			),
		);

		$listed = $this->item->get_scripture();

		$this->assertSame( array( 'Hebrews', 'Luke' ), array_column( $listed, 'name' ) );
		$this->assertSame( array( 'hebrews', 'luke' ), array_keys( $listed ) );
	}
}
