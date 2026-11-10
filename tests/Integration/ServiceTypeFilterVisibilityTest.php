<?php
/**
 * The Service Type filter on /messages/.
 *
 * Two separate misses. Disable Filters saves `service_type` while the facet
 * id is `service-type`, and the hide check was exact, so the checkbox did
 * nothing. Registration also read `enable_service_types` from sermon options
 * and defaulted that to on, so the filter was present on a first load even
 * when Service Types were turned off.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Integration;

use CP_Library\Filters\FilterManager;
use CP_Library\Filters\Types\SermonFilterManager;

/**
 * @covers \CP_Library\Filters\Types\SermonFilterManager::register_default_facets
 * @covers \CP_Library\Filters\AbstractFilterManager::render_filter_form
 */
class ServiceTypeFilterVisibilityTest extends TestCase {

	public function tear_down() {
		remove_filter( 'cpl_enable_service_type', '__return_true' );
		parent::tear_down();
	}

	public function test_service_type_filter_is_absent_when_service_types_are_disabled() {
		$manager = FilterManager::get_filter_manager( 'cpl_item' );

		$this->assertNotNull( $manager );
		$this->assertArrayNotHasKey( 'service-type', $manager->get_facets() );
		$this->assertStringNotContainsString( 'data-facet-type="service-type"', $manager->render_filter_form() );
		$this->assertStringContainsString( 'data-facet-type="speaker"', $manager->render_filter_form() );
	}

	public function test_service_type_filter_registers_when_service_types_are_enabled() {
		add_filter( 'cpl_enable_service_type', '__return_true' );

		$manager = new SermonFilterManager();

		$this->assertArrayHasKey( 'service-type', $manager->get_facets() );
		$this->assertStringContainsString( 'data-facet-type="service-type"', $manager->render_filter_form() );
	}

	public function test_saved_service_type_hides_the_filter_and_leaves_speaker() {
		add_filter( 'cpl_enable_service_type', '__return_true' );
		update_option( 'cpl_item_options', array( 'disable_filters' => array( 'service_type' ) ) );

		$html = ( new SermonFilterManager() )->render_filter_form();

		$this->assertStringNotContainsString( 'data-facet-type="service-type"', $html );
		$this->assertStringContainsString( 'data-facet-type="speaker"', $html );
	}

	public function test_saved_service_hyphen_type_also_hides_the_filter() {
		add_filter( 'cpl_enable_service_type', '__return_true' );
		update_option( 'cpl_item_options', array( 'disable_filters' => array( 'service-type' ) ) );

		$html = ( new SermonFilterManager() )->render_filter_form();

		$this->assertStringNotContainsString( 'data-facet-type="service-type"', $html );
		$this->assertStringContainsString( 'data-facet-type="speaker"', $html );
	}
}
