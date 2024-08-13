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
		add_action( 'pre_get_posts', [ $this, 'searchwp_for_admin' ] );
		add_filter( 'get_search_query', [ $this, 'get_search_query_text' ] );
	}

	/**
	 * Get the search query text.
	 *
	 * @param string $query The search query.
	 * @return string
	 */
	public function get_search_query_text( $query ) {
		global $wp_query;
		if ( $wp_query->get( 'cpl_searchwp_search_term' ) ) {
			return $wp_query->get( 'cpl_searchwp_search_term' );
		}
		return $query;
	}

	/**
	 * Use SearchWP searching on admin sermon pages. (To search for transcript)
	 *
	 * @param \WP_Query $query The WP_Query object.
	 */
	public function searchwp_for_admin( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'cpl_item' !== $query->get( 'post_type' ) ) {
			return;
		}

		if ( empty( $query->get( 's' ) ) ) {
			return;
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
			return; // revert to default search
		}

		$query->set( 'cpl_searchwp_search_term', $query->get( 's' ) );
		$query->set( 'post__in', wp_list_pluck( $search->results, 'id' ) );

		// unset search query vars
		$query->set( 's', '' ); 
		$query->set( 'sentence', '' );
		$query->set( 'search_terms', '' );
		$query->set( 'search_orderby_title', '' );
		$query->set( 'search_fields', '' );
		$query->set( 'search_columns', '' );
 	}
}
