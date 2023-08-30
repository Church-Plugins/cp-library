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
		add_action( 'rest_api_init', [ $this, 'load_api_routes' ] );
		add_filter( 'posts_clauses', [ $this, 'upcoming_series_filter' ], 15, 2 );
		add_filter( 'posts_clauses', [ $this, 'upcoming_sermons_filter' ], 15, 2 );;
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
			new Items_API(),
			new ItemTypes_API(),
			new Sources_API()
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
	 * @param array $clauses The clauses for the query
	 * @param \WP_Query $query   The query object
	 * @return array The filtered clauses
	 * @author Jonathan Roley
	 */
	public function upcoming_series_filter( $clauses, \WP_Query $query ) {
		global $wpdb;

		if( isset( $query->query['cpl_hide_upcoming'] ) && $query->query['post_type'] === cp_library()->setup->post_types->item_type->post_type ) {
			$clauses['join'] .= "
				INNER JOIN wp_cpl_item_type ON wp_cpl_item_type.origin_id = {$wpdb->posts}.ID";
			$clauses['where'] .= " AND EXISTS ( SELECT 1 FROM wp_cpl_item_meta WHERE wp_cpl_item_meta.key = 'item_type' AND wp_cpl_item_meta.item_type_id = wp_cpl_item_type.id )";
		}

		return $clauses;
	}


	/**
	 * Filters the query to only include Sermons that have audio or video
	 *
	 * @since  1.1.0
	 *
	 * @param array $clauses The clauses for the query
	 * @param \WP_Query $query   The query object
	 * @return array The filtered clauses
	 * @author Jonathan Roley
	 */
	public function upcoming_sermons_filter( $clauses, \WP_Query $query ) {
		global $wpdb;

		if( isset( $query->query['cpl_hide_upcoming'] ) && $query->query['post_type'] === cp_library()->setup->post_types->item->post_type ) {
			$clauses['join'] .= "
				INNER JOIN wp_cpl_item ON wp_cpl_item.origin_id = {$wpdb->posts}.ID";

			$clauses['where'] .= " AND EXISTS ( SELECT 1 from wp_cpl_item_meta WHERE ( wp_cpl_item_meta.key = 'audio_url' OR wp_cpl_item_meta.key = 'video_url' ) AND wp_cpl_item_meta.item_id = wp_cpl_item.id AND wp_cpl_item_meta.value != '' )";
		}

		return $clauses;
	}
}
