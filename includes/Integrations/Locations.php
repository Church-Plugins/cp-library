<?php

namespace CP_Library\Integrations;

use CP_Locations\Models\Location;

class Locations {

	/**
	 * @var Locations
	 */
	protected static $_instance;

	/**
	 * Only make one instance of Locations
	 *
	 * @return Locations
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof Locations ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor
	 *
	 */
	protected function __construct() {
		$this->includes();
		$this->actions();
	}

	/**
	 * @return void
	 */
	protected function includes() {}

	protected function actions() {
		add_filter( 'cp_location_taxonomy_types', [ $this, 'tax_types' ] );
		add_filter( 'cpl_item_type_sources', [ $this, 'item_type_sources' ] );
		add_filter( 'cpl_item_type_get_items_use_item', [ $this, 'check_item_source' ], 10, 3 );
		add_action( 'cpl_save_series_items_item', [ $this, 'item_save_location' ], 10, 3 );
	}

	/** Actions ***************************************************/

	public function tax_types( $types ) {
		return array_merge( $types, [ cp_library()->setup->post_types->item->post_type, cp_library()->setup->post_types->speaker->post_type ]  );
	}

	/**
	 * Add locations as a
	 * @param $sources
	 *
	 * @return mixed
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function item_type_sources( $sources ) {
		foreach( cp_locations()->setup->taxonomies->location->get_terms() as $term_id => $location ) {
			$sources[ $term_id ] = $location;
		}

		return $sources;
	}

	/**
	 * @param $use
	 * @param $item
	 * @param $source
	 *
	 * @return bool|mixed
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function check_item_source( $use, $item, $source ) {
		if ( ! term_exists( $source, cp_locations()->setup->taxonomies->location->taxonomy ) ) {
			return $use;
		}

		return has_term( $source, cp_locations()->setup->taxonomies->location->taxonomy, $item->origin_id );
	}

	/**
	 * Save the location for an item when in series view.
	 *
	 * @param $item
	 * @param $series_id
	 * @param $source
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function item_save_location( $item, $series_id, $source ) {
		if ( ! term_exists( $source, cp_locations()->setup->taxonomies->location->taxonomy ) ) {
			return;
		}

		wp_set_post_terms( $item->origin_id, $source, cp_locations()->setup->taxonomies->location->taxonomy, true );
	}
}
