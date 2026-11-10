<?php
/**
 * The variation source label was passed through the key filter.
 *
 * get_source() filters cpl_variations_source to choose the source key.
 * get_source_label() used that same filter on the display name, so a site
 * forcing the source received the slug (cpl_service_type) where the screen
 * should say "Service Types".
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Library\Setup\Variations;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Library\Setup\Variations::get_source_label
 */
class VariationSourceLabelTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( '__' )->returnArg();
		Functions\when( 'apply_filters' )->alias(
			static function ( $tag, $value, ...$ignored ) {
				if ( 'cpl_variations_source' === $tag ) {
					return 'cpl_service_type';
				}

				if ( 'cpl_variations_sources' === $tag ) {
					return array(
						'cpl_service_type' => 'Service Types',
					);
				}

				return $value;
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_a_forced_source_key_does_not_replace_the_label() {
		$this->assertSame( 'Service Types', Variations::get_instance()->get_source_label() );
	}
}
