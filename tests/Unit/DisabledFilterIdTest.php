<?php
/**
 * Disable Filters stores `service_type`. The sermon facet id is `service-type`.
 *
 * The hide check was an exact in_array(), so ticking Service Types left the
 * filter on the archive. Speaker matched because both sides are `speaker`.
 * Comparison now treats "_" and "-" as the same character, so a value saved
 * as `service_type` or as `service-type` hides the facet, and other ids still
 * match themselves.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use CP_Library\Filters\AbstractFilterManager;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Library\Filters\AbstractFilterManager::is_facet_disabled
 * @covers \CP_Library\Filters\AbstractFilterManager::normalize_filter_id
 */
class DisabledFilterIdTest extends TestCase {

	public function test_saved_service_type_hides_the_service_type_facet() {
		$this->assertTrue( AbstractFilterManager::is_facet_disabled( 'service-type', array( 'service_type' ) ) );
	}

	public function test_saved_service_hyphen_type_also_hides_the_facet() {
		$this->assertTrue( AbstractFilterManager::is_facet_disabled( 'service-type', array( 'service-type' ) ) );
	}

	public function test_service_type_value_does_not_hide_speaker() {
		$this->assertFalse( AbstractFilterManager::is_facet_disabled( 'speaker', array( 'service_type' ) ) );
		$this->assertTrue( AbstractFilterManager::is_facet_disabled( 'speaker', array( 'speaker' ) ) );
	}

	public function test_taxonomy_ids_still_match_themselves() {
		$this->assertTrue( AbstractFilterManager::is_facet_disabled( 'cpl_topic', array( 'cpl_topic' ) ) );
		$this->assertFalse( AbstractFilterManager::is_facet_disabled( 'cpl_topic', array( 'service_type' ) ) );
		$this->assertFalse( AbstractFilterManager::is_facet_disabled( 'year', array() ) );
	}
}
