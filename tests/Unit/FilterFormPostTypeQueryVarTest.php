<?php
/**
 * Tests for the filter form putting `post_type` on the query string.
 *
 * The bug this locks down: templates/parts/filters/form.php always printed
 * <input type="hidden" name="post_type" value="cpl_item">, and the filter
 * script copies every form field into the URL. `post_type` is a public
 * WordPress query var, so a Page that contains [cp-sermons] (pretty
 * permalinks) was requested as /test-sermons/?facet-speaker=17&post_type=cpl_item.
 * WordPress merged that into the main query (pagename plus post_type=cpl_item),
 * the page lookup returned nothing, and WordPress 404'd before the shortcode
 * ran. The same request without post_type returns 200 and is still filtered,
 * because facet params are read from $_GET onto the shortcode's own query.
 *
 * The [cp-sermons] shortcode replaces the global $wp_query with a cpl_item
 * query before the form renders, so is_post_type_archive() would lie here.
 * The decision has to read $wp_the_query, the original main query.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Library\Filters\TemplateHelpers;
use PHPUnit\Framework\TestCase;

/**
 * Query stand-in. Only is_post_type_archive() is reached.
 */
class PostTypeArchiveQuery {

	/** @var string[] */
	private $archives;

	public function __construct( array $archives ) {
		$this->archives = $archives;
	}

	public function is_post_type_archive( $post_types = '' ) {
		if ( '' === $post_types || null === $post_types ) {
			return ! empty( $this->archives );
		}

		foreach ( (array) $post_types as $type ) {
			if ( in_array( $type, $this->archives, true ) ) {
				return true;
			}
		}

		return false;
	}
}

/**
 * @covers \CP_Library\Filters\TemplateHelpers::should_submit_post_type_query_arg
 * @covers \CP_Library\Filters\TemplateHelpers::strip_conflicting_post_type_query_var
 */
class FilterFormPostTypeQueryVarTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// The template calls ChurchPlugins\Helpers. The submodule class sanitizes
		// through these two functions; stub them so the render does not need
		// WordPress. Fall back to a local stand-in when the submodule is absent.
		if ( ! class_exists( 'ChurchPlugins\\Helpers', false ) && ! file_exists( dirname( __DIR__, 2 ) . '/includes/ChurchPlugins/Helpers.php' ) ) {
			require dirname( __DIR__ ) . '/fixtures/ChurchPlugins/Helpers.php';
		}
		Functions\when( '_sanitize_text_fields' )->returnArg();
		Functions\when( 'apply_filters' )->alias(
			function ( $hook, $value = null ) {
				return $value;
			}
		);

		Functions\when( 'wp_parse_args' )->alias(
			function ( $args, $defaults = array() ) {
				if ( ! is_array( $args ) ) {
					$args = array();
				}

				return array_merge( $defaults, $args );
			}
		);
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html_e' )->alias(
			function ( $text ) {
				echo $text;
			}
		);
		Functions\when( 'esc_attr_e' )->alias(
			function ( $text ) {
				echo $text;
			}
		);
		Functions\when( 'is_admin' )->justReturn( false );
	}

	protected function tearDown(): void {
		$_GET = array();
		unset( $GLOBALS['wp_the_query'], $GLOBALS['wp_query'] );
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_page_form_does_not_emit_public_post_type_param() {
		$html = $this->render_form(
			array( 'post_type' => 'cpl_item' ),
			new PostTypeArchiveQuery( array() )
		);

		$this->assertStringNotContainsString(
			'name="post_type"',
			$html,
			'a Page must not submit the public post_type query var'
		);
		$this->assertStringContainsString(
			'data-post-type="cpl_item"',
			$html,
			'the wrapper still tells the script which post type this form filters'
		);
	}

	public function test_shortcode_replaced_query_does_not_count_as_an_archive() {
		// What is_post_type_archive() would see after [cp-sermons] swaps $wp_query.
		$GLOBALS['wp_query'] = new PostTypeArchiveQuery( array( 'cpl_item' ) );

		$html = $this->render_form(
			array( 'post_type' => 'cpl_item' ),
			new PostTypeArchiveQuery( array() )
		);

		$this->assertStringNotContainsString( 'name="post_type"', $html );
	}

	public function test_sermon_archive_still_submits_post_type() {
		$html = $this->render_form(
			array( 'post_type' => 'cpl_item' ),
			new PostTypeArchiveQuery( array( 'cpl_item' ) )
		);

		$this->assertStringContainsString(
			'<input type="hidden" name="post_type" value="cpl_item">',
			$html
		);
	}

	public function test_series_archive_still_submits_its_post_type() {
		$html = $this->render_form(
			array( 'post_type' => 'cpl_item_type' ),
			new PostTypeArchiveQuery( array( 'cpl_item_type' ) )
		);

		$this->assertStringContainsString(
			'<input type="hidden" name="post_type" value="cpl_item_type">',
			$html
		);
	}

	public function test_series_form_on_a_page_does_not_submit_post_type() {
		$html = $this->render_form(
			array( 'post_type' => 'cpl_item_type' ),
			new PostTypeArchiveQuery( array() )
		);

		$this->assertStringNotContainsString( 'name="post_type"', $html );
		$this->assertStringContainsString( 'data-post-type="cpl_item_type"', $html );
	}

	public function test_context_args_cannot_smuggle_post_type_onto_a_page() {
		$html = $this->render_form(
			array(
				'post_type'         => 'cpl_item',
				'context_args_data' => array(
					'post_type'       => 'cpl_item',
					'service_type_id' => '9',
				),
			),
			new PostTypeArchiveQuery( array() )
		);

		$this->assertStringNotContainsString( 'name="post_type"', $html );
		$this->assertStringContainsString( 'name="service_type_id"', $html );
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

	/**
	 * @param array                 $args
	 * @param PostTypeArchiveQuery $main_query
	 * @return string
	 */
	private function render_form( array $args, PostTypeArchiveQuery $main_query ) {
		global $wp_the_query;

		$wp_the_query = $main_query;
		$_GET         = array();

		$args = array_merge(
			array(
				'facets'            => array(
					'speaker' => array( 'label' => 'Speaker' ),
				),
				'context_args'      => '',
				'context_args_data' => array(),
				'context'           => 'archive',
				'show_search'       => true,
				'post_type'         => 'cpl_item',
			),
			$args
		);

		ob_start();
		include dirname( __DIR__, 2 ) . '/templates/parts/filters/form.php';

		return ob_get_clean();
	}
}
