<?php
/**
 * Server-rendered filter dropdowns print term titles into HTML.
 *
 * A term whose name is the characters "<b>x</b>" has to show up as that
 * text. The dropdown markup escapes the title on the way out.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use CP_Library\API\AJAX;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @covers \CP_Library\API\AJAX::render_dropdown_options
 */
class FilterDropdownOutputTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		require_once dirname( __DIR__ ) . '/fixtures/filter-dropdown-stubs.php';
	}

	public function test_markup_term_name_renders_as_text() {
		$ajax = ( new ReflectionClass( AJAX::class ) )->newInstanceWithoutConstructor();

		$html = $ajax->render_dropdown_options(
			array(
				(object) array(
					'title' => '<b>x</b>',
					'value' => 'b-x',
					'count' => 1,
				),
			),
			'cpl_topic',
			array(),
			false
		);

		$this->assertStringContainsString( '&lt;b&gt;x&lt;/b&gt;', $html );
		$this->assertStringNotContainsString( '<b>x</b>', $html );
		$this->assertStringNotContainsString( '<b>', $html );
	}
}
