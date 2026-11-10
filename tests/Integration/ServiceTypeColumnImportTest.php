<?php
/**
 * A CSV with no Service Type column must not clear service types.
 *
 * import_item() always passed the parsed Service Type cell to
 * update_service_types(). An unmapped column parses to '' on every row, so
 * re-importing a CSV to fix titles removed every hand-assigned service type
 * from the sermons it matched (#248). 1.8.2 made this reach more sites,
 * because the block now runs whenever Variations are off.
 *
 * An unmapped column now leaves service types alone. A mapped column with an
 * empty cell still clears them, because that is what the file says.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Integration;

use CP_Library\Admin\Import\ImportSermons;
use CP_Library\Models\Item as ItemModel;
use CP_Library\Models\ServiceType as ServiceTypeModel;
use ReflectionMethod;
use ReflectionProperty;

/**
 * @covers \CP_Library\Admin\Import\ImportSermons::import_item
 * @covers \CP_Library\Admin\Import\ImportSermons::column_is_mapped
 */
class ServiceTypeColumnImportTest extends TestCase {

	const TITLE = 'Walking in the Light';

	public function set_up() {
		parent::set_up();

		add_filter( 'cpl_enable_service_type', '__return_true' );
		add_filter( 'cp_library_import_sermons_use_default_series', '__return_false' );

		$service_type = cp_library()->setup->post_types->service_type;

		if ( ! post_type_exists( $service_type->post_type ) ) {
			$service_type->register_post_type();
		}
	}

	public function tear_down() {
		$this->reset_import_cache( '_service_types' );
		$this->reset_import_cache( '_speakers' );

		remove_filter( 'cpl_enable_service_type', '__return_true' );
		remove_filter( 'cp_library_import_sermons_use_default_series', '__return_false' );

		parent::tear_down();
	}

	public function test_unmapped_service_type_column_keeps_existing_service_types() {
		$this->import( 'Sunday AM', true );
		$this->assertSame( array( 'Sunday AM' ), $this->service_type_titles() );

		$this->import( '', false );

		$this->assertSame( array( 'Sunday AM' ), $this->service_type_titles() );
	}

	public function test_mapped_empty_service_type_cell_clears_service_types() {
		$this->import( 'Sunday AM', true );
		$this->assertSame( array( 'Sunday AM' ), $this->service_type_titles() );

		$this->import( '', true );

		$this->assertSame( array(), $this->service_type_titles() );
	}

	/**
	 * @param string $service_type Service Type cell.
	 * @param bool   $mapped       Whether the Service Type column is mapped.
	 */
	private function import( $service_type, $mapped ) {
		$importer = new ImportSermons();

		if ( $mapped ) {
			$importer->load_mapping( array( 'service_type' => 'Service Type' ) );
		}

		$method = new ReflectionMethod( ImportSermons::class, 'import_item' );
		$method->setAccessible( true );
		$method->invoke(
			$importer,
			array(
				'title'        => self::TITLE,
				'description'  => '',
				'transcript'   => '',
				'series'       => '',
				'date'         => '8/9/2026',
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
	 * @return string[]
	 */
	private function service_type_titles() {
		$posts = get_posts(
			array(
				'post_type'   => cp_library()->setup->post_types->item->post_type,
				'title'       => self::TITLE,
				'post_status' => 'publish',
				'numberposts' => -1,
			)
		);

		$this->assertCount( 1, $posts, 'the re-import should update the same sermon' );

		$item = ItemModel::get_instance_from_origin( $posts[0]->ID );

		return array_map(
			static function ( $id ) {
				return ServiceTypeModel::get_instance( $id )->title;
			},
			$item->get_service_types()
		);
	}

	private function reset_import_cache( $property ) {
		$cache = new ReflectionProperty( ImportSermons::class, $property );
		$cache->setAccessible( true );
		$cache->setValue( null, false );
	}
}
