<?php
namespace CP_Library\API;

use CP_Library\API\Items as Items_API;
use CP_Library\API\ItemTypes as ItemTypes_API;
use CP_Library\API\Sources as Sources_API;

/**
 * Provides the global $cp_library object
 *
 * @author costmo
 */
class Init {

	/**
	 * @var
	 */
	protected static $_instance;

	public $items;
	public $item_types;
	public $sources;

	/**
	 * AJAX class instance
	 *
	 * @var AJAX
	 */
	public $ajax;

	/**
	 * Only make one instance of Init
	 *
	 * @return Init
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof Init ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor: Add Hooks and Actions
	 *
	 */
	protected function __construct() {
		$this->items      = new Items_API();
		$this->item_types = new ItemTypes_API();
		$this->ajax       = AJAX::get_instance();
		$this->sources = new Sources_API();
		add_action( 'rest_api_init', [ $this, 'load_api_routes' ] );
		add_filter( 'posts_clauses', [ $this, 'upcoming_series_filter' ], 15, 2 );
		add_filter( 'posts_clauses', [ $this, 'upcoming_sermons_filter' ], 15, 2 );
	}

	/** Actions **************************************/

	/**
	 * Loads the APIs that are not loaded automatically
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function load_api_routes() {
		$api_instance = [
			$this->items,
			$this->item_types,
			$this->sources
		];

		foreach( $api_instance as $api ) {
			$api->register_routes();
		}
	}

	/** Helper Methods **************************************/

	/**
	 * Filters the query to only include items that have sermons
	 *
	 * @since  1.1.0
	 *
	 * @param array     $clauses The clauses for the query.
	 * @param \WP_Query $query   The query object.
	 * @return array The filtered clauses
	 * @author Jonathan Roley
	 */
	public function upcoming_series_filter( $clauses, \WP_Query $query ) {
		global $wpdb;

		$item_table_name      = "{$wpdb->prefix}cpl_item";
		$item_type_table_name = "{$wpdb->prefix}cpl_item_type";
		$item_meta_table_name = "{$wpdb->prefix}cpl_item_meta";

		if ( isset( $query->query['cpl_hide_upcoming'] ) && true === $query->query['cpl_hide_upcoming'] && cp_library()->setup->post_types->item_type->post_type === $query->query['post_type'] ) {
			$clauses['join']  .= "
				INNER JOIN {$item_table_name} ON {$item_type_table_name}.origin_id = {$wpdb->posts}.ID";
			$clauses['where'] .= " AND EXISTS ( SELECT 1 FROM {$item_meta_table_name} WHERE {$item_meta_table_name}.key = 'item_type' AND {$item_type_table_name}.item_type_id = {$item_type_table_name}.id )";
		}

		return $clauses;
	}


	/**
	 * Filters the query to only include Sermons that have audio or video
	 *
	 * @since  1.1.0
	 *
	 * @param array     $clauses The clauses for the query.
	 * @param \WP_Query $query The query object.
	 * @return array The filtered clauses
	 * @author Jonathan Roley
	 */
	public function upcoming_sermons_filter( $clauses, \WP_Query $query ) {
		global $wpdb;

		$item_table_name      = "{$wpdb->prefix}cpl_item";
		$item_type_table_name = "{$wpdb->prefix}cpl_item_type";
		$item_meta_table_name = "{$wpdb->prefix}cpl_item_meta";

		if ( isset( $query->query['cpl_hide_upcoming'] ) && true === $query->query['cpl_hide_upcoming'] && cp_library()->setup->post_types->item->post_type === $query->query['post_type'] ) {
			$clauses['join'] .= "
				INNER JOIN {$item_table_name} ON {$item_table_name}.origin_id = {$wpdb->posts}.ID";

			$clauses['where'] .= " AND EXISTS ( SELECT 1 from {$item_meta_table_name} WHERE ( {$item_meta_table_name}.key = 'audio_url' OR {$item_meta_table_name}.key = 'video_url' ) AND {$item_meta_table_name}.item_id = {$item_table_name}.id AND {$item_meta_table_name}.value != '' )";
		}

		return $clauses;
	}
}
