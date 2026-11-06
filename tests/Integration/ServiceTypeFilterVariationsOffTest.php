<?php
/**
 * The archive's Service Type filter must not take the variations path while
 * Variations are off.
 *
 * item_variation_query() compared the saved variation source to the Service
 * Type post type. Most Service Types sites have that source saved, because
 * saving the Messages tab before 1.8.0 stored the first option. With
 * Variations off, ?service-type= still skipped the post_parent = 0 limit, so
 * leftover variation children could show as separate results (#258). The
 * check now uses service_type_is_source(), which is false while Variations
 * are off.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Integration;

use WP_Query;

/**
 * @covers \CP_Library\Setup\PostTypes\Item::item_variation_query
 */
class ServiceTypeFilterVariationsOffTest extends TestCase {

	public function tear_down() {
		unset( $_GET['service-type'] );
		delete_option( 'cpl_item_options' );
		wp_cache_delete( 'cpl_item_options', 'options' );
		parent::tear_down();
	}

	public function test_variations_off_keeps_top_level_sermons_only() {
		$query = $this->filtered_query( '0' );

		$this->assertSame( 0, $query->get( 'post_parent' ) );
		$this->assertEmpty( $query->get( 'cpl_limit_child_visibility' ) );
	}

	public function test_variations_on_with_service_type_source_still_shows_variations() {
		$query = $this->filtered_query( '1' );

		$this->assertTrue( (bool) $query->get( 'cpl_limit_child_visibility' ) );
		$this->assertSame( '', $query->get( 'post_parent' ) );
	}

	/**
	 * @param string $enabled '0' or '1'.
	 * @return WP_Query
	 */
	private function filtered_query( $enabled ) {
		update_option(
			'cpl_item_options',
			array(
				'variations_enabled' => $enabled,
				'variation_source'   => cp_library()->setup->post_types->service_type->post_type,
			)
		);
		wp_cache_delete( 'cpl_item_options', 'options' );

		$_GET['service-type'] = 'sunday-am';

		$query = new WP_Query();
		$query->query_vars = array( 'post_type' => cp_library()->setup->post_types->item->post_type );

		cp_library()->setup->post_types->item->item_variation_query( $query );

		return $query;
	}
}
