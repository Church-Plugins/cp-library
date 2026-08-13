<?php
/**
 * Tests for the export/import post meta exclusion list.
 *
 * `cpl_series_items*` (series => item repeater) and `_cpl_item_variation_*`
 * (sermon variation repeater) cache the post IDs of the site that wrote them.
 * They used to be exported and replayed verbatim, so on the target site the
 * series and variation save handlers would act on whatever posts happened to
 * hold those IDs — retitling, re-dating, reparenting, or (for variations)
 * calling wp_delete_post() on unrelated content the next time an admin hit
 * Update.
 *
 * Both keys carry a dynamic suffix, so an exact-match exclusion list cannot
 * catch them; these tests pin the prefix matching that does.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Library\Admin\ImportExport\Util;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Library\Admin\ImportExport\Util::is_excluded_meta
 * @covers \CP_Library\Admin\ImportExport\Util::excluded_meta_prefixes
 */
class ExcludedMetaTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		// Both lists are filterable; identity is the default.
		Functions\when( 'apply_filters' )->alias(
			static function ( $tag, $value ) {
				return $value;
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @dataProvider excluded_keys
	 */
	public function test_key_is_excluded( $key, $reason ) {
		$this->assertTrue( Util::is_excluded_meta( $key ), $reason );
	}

	public function excluded_keys() {
		return [
			'exact match from the key list' => [ '_edit_lock', 'bookkeeping meta must never travel' ],
			'thumbnail id'                  => [ '_thumbnail_id', 'exported separately as a URL reference' ],
			'import marker'                 => [ '_cpl_import_original_id', 'target-site bookkeeping' ],
			'bare series repeater'          => [ 'cpl_series_items', 'holds item post IDs' ],
			'suffixed series repeater'      => [ 'cpl_series_items_2', 'per-source variant of the repeater' ],
			'series repeater group field'   => [ 'cpl_series_items_data', 'CMB2 group wrapper for the same data' ],
			'suffixed group field'          => [ 'cpl_series_items_data_2', 'per-source variant of the wrapper' ],
			'variation repeater'            => [ '_cpl_item_variation_12', 'holds variant post IDs' ],
		];
	}

	/**
	 * @dataProvider carried_keys
	 */
	public function test_key_is_carried( $key, $reason ) {
		$this->assertFalse( Util::is_excluded_meta( $key ), $reason );
	}

	public function carried_keys() {
		return [
			'audio url'      => [ 'audio_url', 'real sermon content must survive the migration' ],
			'video url'      => [ 'video_url', 'real sermon content must survive the migration' ],
			'downloads'      => [ 'downloads', 'rewritten on import, but must not be dropped' ],
			'transcript'     => [ 'transcript', 'real sermon content must survive the migration' ],
			// Guards the prefix from being written loosely enough to swallow the
			// relation fields, which are handled by import_item_relations().
			'series field'   => [ 'cpl_series', 'not the repeater — must not be caught by the prefix' ],
			'speaker field'  => [ 'cpl_speaker', 'unrelated to the excluded prefixes' ],
			'variation-ish'  => [ '_cpl_item_variations_enabled', 'no trailing underscore — not the repeater' ],
		];
	}

	public function test_prefix_list_is_filterable() {
		Functions\when( 'apply_filters' )->alias(
			static function ( $tag, $value ) {
				return 'cpl_import_export_excluded_meta_prefixes' === $tag ? [ 'custom_' ] : $value;
			}
		);

		$this->assertTrue( Util::is_excluded_meta( 'custom_thing' ), 'filtered prefixes must apply' );
		$this->assertFalse( Util::is_excluded_meta( 'cpl_series_items' ), 'filter replaces the defaults' );
	}
}
