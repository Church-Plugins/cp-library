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
		add_filter( 'cpl_item_type_get_items', [ $this, 'messages_by_location' ], 10, 2 );
		add_filter( 'cploc_add_location_to_query', [ $this, 'taxonomies_for_location_query' ], 10, 2 );
		add_filter( 'post_type_archive_title', [ $this, 'location_archive_title' ], 10, 2 );
	}

	/** Actions ***************************************************/

	/**
	 * add our post types to the location taxonomy
	 *
	 * @param $types
	 *
	 * @return mixed
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
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
		$user_locations = cp_locations()->setup->permissions::get_user_locations( false, true );

		foreach( cp_locations()->setup->taxonomies->location->get_terms() as $term_id => $location ) {
			if ( empty( $user_locations ) || in_array( $term_id, $user_locations ) ) {
				$sources[ $term_id ] = $location;
			}
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

	/**
	 * Only return the messages for the given location
	 *
	 * @param $items
	 * @param $item_type
	 *
	 * @return array|mixed
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function messages_by_location( $items, $item_type ) {
		if ( ! function_exists( 'cp_locations' ) ) {
			return $items;
		}

		$tax = cp_locations()->setup->taxonomies->location->taxonomy;
		if ( ! $location = cp_locations()->setup->taxonomies->location::get_rewrite_location() ) {
			return $items;
		}

		$location_items = [];
		foreach ( $items as $item ) {
			if ( has_term( $location['term'], $tax, $item->origin_id ) ) {
				$location_items[] = $item;
			}
		}

		return $location_items;
	}

	/**
	 * Check if query is for taxonomies attached to the library
	 *
	 * @param $return
	 * @param $query
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function taxonomies_for_location_query( $return, $query ) {
		// if the post type is defined, fall back to normal behavior
		if ( ! empty( $query->query['post_type'] ) ) {
			return $return;
		}

		foreach( cp_library()->setup->taxonomies->get_taxonomies() as $taxonomy ) {
			if ( ! empty( $query->query_vars[ $taxonomy ] ) ) {
				return true;
			}
		}

		return $return;
	}

	/**
	 * Add location to archive title
	 *
	 * @param $title
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function location_archive_title( $title, $type ) {
		// don't mess with doc title
		if ( doing_action( 'wp_head' ) ) {
			return $title;
		}

		if ( ! $location_id = get_query_var( 'cp_location_id' ) ) {
			return $title;
		}

		return sprintf( '<span class="location">%s<br />%s</span>', get_the_title( $location_id ), $title );
	}
}
