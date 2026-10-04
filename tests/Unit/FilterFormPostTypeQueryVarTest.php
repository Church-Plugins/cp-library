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
 * The param is suppressed only on a Page or other singular. Taxonomy
 * archives have no post_type of their own, and apply_facet_filters()
 * returns before applying facets when that query var is empty, so those
 * archives still have to submit it.
 *
 * The [cp-sermons] shortcode replaces the global $wp_query with a cpl_item
 * query before the form renders. That swapped query is not a page. The
 * decision has to read $wp_the_query, which still is.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Query stand-in. Only is_page() and is_singular() are reached.
 *
 * A Page is singular. A taxonomy archive and a post type archive are neither.
 */
class MainQueryFlags {

	/** @var bool */
	private $page;

	/** @var bool */
	private $singular;

	/**
	 * @param bool      $page     is_page().
	 * @param bool|null $singular is_singular(). Defaults to the page flag,
	 *                            which is how WordPress treats a Page.
	 */
	public function __construct( $page, $singular = null ) {
		$this->page     = (bool) $page;
		$this->singular = null === $singular ? (bool) $page : (bool) $singular;
	}

	public function is_page() {
		return $this->page;
	}

	public function is_singular() {
		return $this->singular;
	}
}

/**
 * @covers \CP_Library\Filters\TemplateHelpers::should_submit_post_type_query_arg
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
			new MainQueryFlags( true, true )
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

	public function test_shortcode_swapped_query_still_counts_as_a_page() {
		// The swapped sermon query is not a page. Reading it would submit post_type.
		$GLOBALS['wp_query'] = new MainQueryFlags( false, false );

		$html = $this->render_form(
			array( 'post_type' => 'cpl_item' ),
			new MainQueryFlags( true, true )
		);

		$this->assertStringNotContainsString( 'name="post_type"', $html );
	}

	public function test_singular_does_not_submit_post_type() {
		$html = $this->render_form(
			array( 'post_type' => 'cpl_item' ),
			new MainQueryFlags( false, true )
		);

		$this->assertStringNotContainsString( 'name="post_type"', $html );
		$this->assertStringContainsString( 'data-post-type="cpl_item"', $html );
	}

	public function test_taxonomy_archive_still_submits_post_type() {
		// A topic/scripture/season archive is not a page and not singular.
		$html = $this->render_form(
			array( 'post_type' => 'cpl_item' ),
			new MainQueryFlags( false, false )
		);

		$this->assertStringContainsString(
			'<input type="hidden" name="post_type" value="cpl_item">',
			$html
		);
	}

	public function test_sermon_archive_still_submits_post_type() {
		$html = $this->render_form(
			array( 'post_type' => 'cpl_item' ),
			new MainQueryFlags( false, false )
		);

		$this->assertStringContainsString(
			'<input type="hidden" name="post_type" value="cpl_item">',
			$html
		);
	}

	public function test_series_archive_still_submits_its_post_type() {
		$html = $this->render_form(
			array( 'post_type' => 'cpl_item_type' ),
			new MainQueryFlags( false, false )
		);

		$this->assertStringContainsString(
			'<input type="hidden" name="post_type" value="cpl_item_type">',
			$html
		);
	}

	public function test_series_form_on_a_page_does_not_submit_post_type() {
		$html = $this->render_form(
			array( 'post_type' => 'cpl_item_type' ),
			new MainQueryFlags( true, true )
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
			new MainQueryFlags( true, true )
		);

		$this->assertStringNotContainsString( 'name="post_type"', $html );
		$this->assertStringContainsString( 'name="service_type_id"', $html );
	}

	/**
	 * @param array          $args
	 * @param MainQueryFlags $main_query
	 * @return string
	 */
	private function render_form( array $args, MainQueryFlags $main_query ) {
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
