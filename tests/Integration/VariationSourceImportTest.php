<?php
/**
 * Service Types were dropped on import when Variations were off.
 *
 * The variation source select had no empty option, so saving settings stored
 * Service Types as the source. get_source() returned that saved value even
 * after Variations were turned off, and the importer and the mapping screen
 * treated it as the variation source. A mapped Service Type column then
 * created no service types.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Integration;

use CP_Library\Admin\Import\ImportSermons;
use CP_Library\Admin\Tools;
use CP_Library\Models\Item as ItemModel;
use CP_Library\Models\ServiceType as ServiceTypeModel;
use ReflectionMethod;
use ReflectionProperty;

/**
 * @covers \CP_Library\Setup\Variations::service_type_is_source
 * @covers \CP_Library\Admin\Settings::save_empty_variation_source
 */
class VariationSourceImportTest extends TestCase {

	public function set_up() {
		parent::set_up();

		add_filter( 'cpl_enable_service_type', '__return_true' );
		add_filter( 'cp_library_import_sermons_use_default_series', '__return_false' );

		$service_type = cp_library()->setup->post_types->service_type;

		if ( ! post_type_exists( $service_type->post_type ) ) {
			$service_type->register_post_type();
		}

		if ( ! has_filter( 'cpl_variations_sources', array( $service_type, 'variation_source' ) ) ) {
			$service_type->add_actions();
		}

		if ( ! cmb2_get_metabox( 'cpl_item_options_page' ) ) {
			do_action( 'cmb2_admin_init' );
		}
	}

	public function tear_down() {
		$this->reset_import_cache( '_service_types' );
		$this->reset_import_cache( '_speakers' );

		remove_filter( 'cpl_enable_service_type', '__return_true' );
		remove_filter( 'cp_library_import_sermons_use_default_series', '__return_false' );

		parent::tear_down();
	}

	/**
	 * Variations off, source still saved as Service Types: the column is imported.
	 */
	public function test_import_maps_service_types_when_variations_are_off() {
		$source = cp_library()->setup->post_types->service_type->post_type;

		$this->save_variation_settings( '0', $source );

		$this->assertFalse( cp_library()->setup->variations->is_enabled() );
		$this->assertSame( $source, cp_library()->setup->variations->get_source() );
		$this->assertFalse( cp_library()->setup->variations->service_type_is_source() );

		$this->import_sermon( 'The Purpose of Gathered Worship', 'Sermon' );

		$item = $this->sermon_item( 'The Purpose of Gathered Worship' );
		$ids  = $item->get_service_types();

		$this->assertCount( 1, $ids );
		$this->assertSame( 'Sermon', ServiceTypeModel::get_instance( $ids[0] )->title );
	}

	/**
	 * The mapping screen shows Service Type unless Variations are on and that is the source.
	 */
	public function test_mapping_screen_shows_service_type_row_when_variations_are_off() {
		$source = cp_library()->setup->post_types->service_type->post_type;

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->save_variation_settings( '0', $source );
		$this->assertStringContainsString( 'name="mapping[service_type]"', $this->mapping_html() );

		$this->save_variation_settings( '1', $source );
		$this->assertTrue( cp_library()->setup->variations->service_type_is_source() );
		$this->assertStringNotContainsString( 'name="mapping[service_type]"', $this->mapping_html() );
	}

	/**
	 * None stores an empty source and get_source() returns that empty value.
	 */
	public function test_none_saves_and_clears_variation_source() {
		$source = cp_library()->setup->post_types->service_type->post_type;

		$this->save_variation_settings( '1', $source );
		$this->assertSame( $source, cp_library()->setup->variations->get_source() );

		$field = $this->variation_source_field();
		$this->assertSame( 'None', $field->args( 'show_option_none' ) );

		$this->save_variation_settings( '1', '' );

		$stored = get_option( 'cpl_item_options', array() );
		$this->assertArrayHasKey( 'variation_source', $stored );
		$this->assertSame( '', $stored['variation_source'] );
		$this->assertSame( '', cp_library()->setup->variations->get_source() );
		$this->assertSame( array(), cp_library()->setup->variations->get_source_items() );
		$this->assertFalse( cp_library()->setup->variations->service_type_is_source() );

		$field = $this->variation_source_field();
		$field->value = null;
		$html = $this->render_field( $field );
		$this->assertMatchesRegularExpression( '#<option value=""\s+selected=([\'"])selected\1>None</option>#', $html );
	}

	/**
	 * @param string $enabled '0' or '1', the values the settings radios submit.
	 * @param string $source  Variation source, or '' for None.
	 */
	private function save_variation_settings( $enabled, $source ) {
		$cmb = cmb2_get_metabox( 'cpl_item_options_page', 'cpl_item_options', 'options-page' );
		$this->assertNotFalse( $cmb );

		$cmb->object_id( 'cpl_item_options' );
		$cmb->object_type( 'options-page' );

		$enabled_field = $cmb->get_field( 'variations_enabled' );
		$source_field  = $cmb->get_field( 'variation_source' );
		$this->assertNotFalse( $enabled_field );
		$this->assertNotFalse( $source_field );

		$enabled_field->save_field( $enabled );
		$source_field->save_field( $source );
		cmb2_options( 'cpl_item_options' )->set();

		// get_option must see the write, not a copy CMB2 is still holding.
		wp_cache_delete( 'cpl_item_options', 'options' );
	}

	private function variation_source_field() {
		$cmb = cmb2_get_metabox( 'cpl_item_options_page', 'cpl_item_options', 'options-page' );
		$cmb->object_id( 'cpl_item_options' );
		$cmb->object_type( 'options-page' );

		return $cmb->get_field( 'variation_source', null, true );
	}

	private function render_field( $field ) {
		ob_start();
		$field->render_field();
		return ob_get_clean();
	}

	private function mapping_html() {
		ob_start();
		Tools::get_instance()->import_export_display();
		return ob_get_clean();
	}

	private function import_sermon( $title, $service_type ) {
		$importer = new ImportSermons();
		$importer->load_mapping( array( 'service_type' => 'Service Type' ) );
		$method   = new ReflectionMethod( ImportSermons::class, 'import_item' );
		$method->setAccessible( true );
		$method->invoke(
			$importer,
			array(
				'title'        => $title,
				'description'  => '',
				'transcript'   => '',
				'series'       => '',
				'date'         => '8/2/2026',
				'location'     => '',
				'service_type' => $service_type,
				'speaker'      => '',
				'topics'       => '',
				'season'       => '',
				'scripture'    => '',
				'thumbnail'    => '',
				'video'        => '',
				'audio'        => '',
				'variation'    => '',
				'downloads'    => '',
			),
			array(
				'sideload_audio'     => false,
				'stop_on_error'      => true,
				'sideload_downloads' => false,
			)
		);
	}

	/**
	 * @param string $title
	 * @return ItemModel
	 */
	private function sermon_item( $title ) {
		$posts = get_posts(
			array(
				'post_type'   => cp_library()->setup->post_types->item->post_type,
				'title'       => $title,
				'post_status' => 'publish',
				'numberposts' => 1,
			)
		);

		$this->assertNotEmpty( $posts, 'imported sermon was not created' );

		return ItemModel::get_instance_from_origin( $posts[0]->ID );
	}

	private function reset_import_cache( $property ) {
		$cache = new ReflectionProperty( ImportSermons::class, $property );
		$cache->setAccessible( true );
		$cache->setValue( null, false );
	}
}
