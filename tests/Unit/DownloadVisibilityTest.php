<?php
/**
 * Tests for which item ids the audio download endpoint will serve.
 *
 * The endpoint used to serve any id it was given. It now refuses anything
 * that is not a sermon, before the sermon visibility check runs, so a
 * missing id (a variation used to send `item_id=undefined`) or another post
 * type never reaches Item.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Library\Download;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Library\Download::item_is_downloadable
 */
class DownloadVisibilityTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		if ( ! defined( 'CP_LIBRARY_UPREFIX' ) ) {
			define( 'CP_LIBRARY_UPREFIX', 'cpl' );
		}
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_missing_item_id_is_refused() {
		Functions\expect( 'get_post_type' )->never();

		$this->assertFalse( Download::item_is_downloadable( 'undefined' ) );
	}

	public function test_other_post_types_are_refused_before_the_visibility_check() {
		Functions\when( 'get_post_type' )->justReturn( 'page' );
		Functions\expect( 'post_password_required' )->never();
		Functions\expect( 'is_post_publicly_viewable' )->never();

		$this->assertFalse( Download::item_is_downloadable( 12 ) );
	}
}
