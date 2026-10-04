<?php
/**
 * Tests for the optional request filter that drops post_type on a Page.
 *
 * The form and the filter script already stop a Page from submitting
 * post_type, which is what fixes the 404. This filter is a separate heal
 * for a URL that already has ?post_type=cpl_item — a bookmark, or HTML a
 * cache still served. Taxonomy and CPT archive requests have no pagename
 * or page_id, so they keep post_type and their facets still apply.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Library\Filters\TemplateHelpers;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Library\Filters\TemplateHelpers::strip_conflicting_post_type_query_var
 */
class StripConflictingPostTypeQueryVarTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'is_admin' )->justReturn( false );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_page_request_drops_post_type_and_keeps_facet_params() {
		$vars = TemplateHelpers::strip_conflicting_post_type_query_var(
			array(
				'pagename'      => 'test-sermons',
				'post_type'     => 'cpl_item',
				'facet-speaker' => '17',
			)
		);

		$this->assertSame(
			array(
				'pagename'      => 'test-sermons',
				'facet-speaker' => '17',
			),
			$vars
		);
	}

	public function test_plain_permalink_page_drops_either_library_post_type() {
		$vars = TemplateHelpers::strip_conflicting_post_type_query_var(
			array(
				'page_id'   => '12',
				'post_type' => 'cpl_item_type',
			)
		);

		$this->assertSame( array( 'page_id' => '12' ), $vars );
	}

	public function test_archive_request_keeps_post_type() {
		$vars = array(
			'post_type'     => 'cpl_item',
			'facet-speaker' => '17',
		);

		$this->assertSame( $vars, TemplateHelpers::strip_conflicting_post_type_query_var( $vars ) );
	}

	public function test_series_archive_request_keeps_post_type() {
		$vars = array( 'post_type' => 'cpl_item_type' );

		$this->assertSame( $vars, TemplateHelpers::strip_conflicting_post_type_query_var( $vars ) );
	}

	public function test_taxonomy_request_keeps_post_type() {
		$vars = array(
			'cpl_topic' => 'faith',
			'post_type' => 'cpl_item',
		);

		$this->assertSame( $vars, TemplateHelpers::strip_conflicting_post_type_query_var( $vars ) );
	}

	public function test_unrelated_post_type_on_a_page_is_left_alone() {
		$vars = array(
			'pagename'  => 'test-sermons',
			'post_type' => 'post',
		);

		$this->assertSame( $vars, TemplateHelpers::strip_conflicting_post_type_query_var( $vars ) );
	}

	public function test_admin_request_is_not_rewritten() {
		Functions\when( 'is_admin' )->justReturn( true );

		$vars = array(
			'pagename'  => 'test-sermons',
			'post_type' => 'cpl_item',
		);

		$this->assertSame( $vars, TemplateHelpers::strip_conflicting_post_type_query_var( $vars ) );
	}

	public function test_array_post_type_on_a_page_drops_only_library_types() {
		$vars = TemplateHelpers::strip_conflicting_post_type_query_var(
			array(
				'pagename'  => 'test-sermons',
				'post_type' => array( 'cpl_item', 'post' ),
			)
		);

		$this->assertSame(
			array(
				'pagename'  => 'test-sermons',
				'post_type' => array( 'post' ),
			),
			$vars
		);
	}
}
