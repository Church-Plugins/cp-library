<?php
/**
 * CP Sermons SearchWP integration.
 *
 * @package CP_Library
 */

namespace CP_Library\Integrations;

use CP_Library\Setup\PostTypes\Item;

/**
 * SearchWP integration.
 *
 * @since 1.5.0
 */
class SearchWP {
	/**
	 * Singleton instance.
	 *
	 * @var SearchWP
	 */
	protected static $_instance;

	/**
	 * Get the instance.
	 *
	 * @return SearchWP
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof SearchWP ) {
			self::$_instance = new SearchWP();
		}

		return self::$_instance;
	}

	/**
	 * SearchWP constructor.
	 */
	protected function __construct() {
		$this->actions();
	}

	/**
	 * Add actions.
	 */
	protected function actions() {
		add_filter( 'posts_pre_query', [ $this, 'searchwp_for_admin' ], 10, 2 );
	}

	/**
	 * Use SearchWP searching on admin sermon pages. (To search for transcript)
	 *
	 * @param \WP_Post[]|int[]|null $posts The post objects.
	 * @param \WP_Query $query The WP_Query object.
	 */
	public function searchwp_for_admin( $posts, $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'cpl_item' !== $query->get( 'post_type' ) ) {
			return $posts;
		}

		if ( empty( $query->get( 's' ) ) ) {
			return $posts;
		}

		$search = new \SearchWP\Query(
			$query->get( 's' ),
			[
				'engine'   => apply_filters( 'cp_library_searchwp_engine', 'default' ),
				'per_page' => $query->get( 'posts_per_page' ),
				'paged'    => get_query_var( 'paged' ),
			]
		);

		if ( 0 === $search->found_results ) {
			return [];
		}

		$ids = wp_list_pluck( $search->results, 'id' );

		$new_query_vars = $query->query_vars;
		$new_query_vars['post__in'] = $ids;
		unset( $new_query_vars['s'] ); // unset search query vars
		unset( $new_query_vars['sentence'] );
		unset( $new_query_vars['search_terms'] );
		unset( $new_query_vars['search_orderby_title'] );
		unset( $new_query_vars['search_fields'] );
		unset( $new_query_vars['search_columns'] );

		// create a new query
		$new_query = new \WP_Query( $new_query_vars );

		return $new_query->posts;
 	}
}
