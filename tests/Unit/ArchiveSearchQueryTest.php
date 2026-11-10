<?php
/**
 * Tests for the sermon and series archive search.
 *
 * The filter form navigates with ?cpl_search=Prisoners and prints that term
 * as a chip. The list under the chip was still the whole library: a published
 * sermon titled "Take No prisoners" did not come back. WordPress sets
 * posts_per_page from Settings → Reading before pre_get_posts, and the archive
 * hooks returned as soon as that var was set, so the search term was never
 * copied onto the query.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Library\Setup\PostTypes\Item;
use CP_Library\Setup\PostTypes\ItemType;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Query stand-in. The unit suite does not boot WordPress.
 */
class ArchiveQueryDouble {

	/** @var array<string,mixed> */
	public $vars;

	/** @var bool */
	public $main;

	/**
	 * @param array<string,mixed> $vars Query vars already filled, as parse_query leaves them.
	 * @param bool                $main Whether this is the main request query.
	 */
	public function __construct( array $vars, $main = true ) {
		$this->vars = $vars;
		$this->main = $main;
	}

	/**
	 * @param string $key Query var.
	 * @return mixed
	 */
	public function get( $key ) {
		return array_key_exists( $key, $this->vars ) ? $this->vars[ $key ] : '';
	}

	/**
	 * @param string $key   Query var.
	 * @param mixed  $value Value to store.
	 * @return void
	 */
	public function set( $key, $value ) {
		$this->vars[ $key ] = $value;
	}

	/**
	 * @return bool
	 */
	public function is_main_query() {
		return $this->main;
	}
}

/**
 * @covers \CP_Library\Setup\ArchiveSearch::apply
 * @covers \CP_Library\Setup\PostTypes\Item::item_query
 * @covers \CP_Library\Setup\PostTypes\ItemType::default_posts_per_page
 */
class ArchiveSearchQueryTest extends TestCase {

	/** @var Item */
	private $items;

	/** @var ItemType */
	private $series;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'sanitize_text_field' )->alias(
			static function ( $value ) {
				return trim( (string) $value );
			}
		);

		$_GET = array();

		$this->items  = ( new ReflectionClass( Item::class ) )->newInstanceWithoutConstructor();
		$this->series = ( new ReflectionClass( ItemType::class ) )->newInstanceWithoutConstructor();
		$this->items->post_type  = 'cpl_item';
		$this->series->post_type = 'cpl_item_type';
	}

	protected function tearDown(): void {
		$_GET = array();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_sermon_archive_search_filters_when_posts_per_page_is_already_set() {
		$_GET['cpl_search'] = 'Prisoners';

		$query = new ArchiveQueryDouble(
			array(
				'post_type'      => 'cpl_item',
				'posts_per_page' => 10,
			)
		);

		$this->items->item_query( $query );

		$this->assertSame( 'Prisoners', $query->get( 's' ) );
		$this->assertSame( 10, $query->get( 'posts_per_page' ), 'the Reading setting page size stays put' );
	}

	public function test_series_archive_search_filters_when_posts_per_page_is_already_set() {
		$_GET['cpl_search'] = 'Isaiah';

		$query = new ArchiveQueryDouble(
			array(
				'post_type'      => 'cpl_item_type',
				'posts_per_page' => 12,
			)
		);

		$this->series->default_posts_per_page( $query );

		$this->assertSame( 'Isaiah', $query->get( 's' ) );
		$this->assertSame( 12, $query->get( 'posts_per_page' ) );
	}

	public function test_a_secondary_sermon_loop_is_not_narrowed_by_the_archive_search() {
		$_GET['cpl_search'] = 'Prisoners';

		$query = new ArchiveQueryDouble(
			array(
				'post_type'      => 'cpl_item',
				'posts_per_page' => 3,
			),
			false
		);

		$this->items->item_query( $query );

		$this->assertSame( '', $query->get( 's' ) );
	}

	public function test_search_does_not_apply_to_a_different_post_type() {
		$_GET['cpl_search'] = 'Prisoners';

		$query = new ArchiveQueryDouble(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 10,
			)
		);

		$this->items->item_query( $query );

		$this->assertSame( '', $query->get( 's' ) );
	}

	public function test_admin_list_is_left_to_its_own_search_box() {
		Functions\when( 'is_admin' )->justReturn( true );
		$_GET['cpl_search'] = 'Prisoners';

		$query = new ArchiveQueryDouble(
			array(
				'post_type'      => 'cpl_item',
				'posts_per_page' => 20,
			)
		);

		$this->items->item_query( $query );

		$this->assertSame( '', $query->get( 's' ) );
	}

	public function test_blank_search_does_not_turn_the_archive_into_an_empty_search() {
		$_GET['cpl_search'] = '   ';

		$query = new ArchiveQueryDouble(
			array(
				'post_type'      => 'cpl_item',
				'posts_per_page' => 10,
			)
		);

		$this->items->item_query( $query );

		$this->assertSame( '', $query->get( 's' ) );
	}
}
