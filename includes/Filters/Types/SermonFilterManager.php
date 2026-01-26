<?php
/**
 * Sermon Filter Manager
 *
 * Implementation of the AbstractFilterManager for sermon post type (cpl_item).
 *
 * @package CP_Library\Filters\Types
 * @since   1.6.0
 */

namespace CP_Library\Filters\Types;

use CP_Library\Filters\AbstractFilterManager;
use CP_Library\Admin\Settings;
use ChurchPlugins\Helpers;

/**
 * SermonFilterManager class - Filter manager for sermon content type.
 *
 * This class handles the sermon-specific implementation of the filter system,
 * registering sermon-related facets and handling sermon-specific queries.
 *
 * @since 1.6.0
 */
class SermonFilterManager extends AbstractFilterManager {

	/**
	 * Constructor
	 *
	 * @param array $args Configuration arguments
	 */
	public function __construct( $args = [] ) {
		$args['post_type'] = 'cpl_item';
		parent::__construct( $args );
	}

	/**
	 * Setup actions and filters specific to sermon filter manager
	 */
	protected function actions() {
		parent::actions();

		// Register AJAX handlers
		add_action( 'wp_ajax_cpl_filter_sermons', [ $this, 'ajax_filter_sermons' ] );
		add_action( 'wp_ajax_nopriv_cpl_filter_sermons', [ $this, 'ajax_filter_sermons' ] );

		// Handle custom query vars for service types
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
	}

	/**
	 * Register default contexts for sermons
	 */
	protected function register_default_contexts() {
		// Archive context
		$this->register_context( 'archive', [
			'label' => __( 'Archive', 'cp-library' ),
		] );

		// Service Type context
		$this->register_context( 'service-type', [
			'label'          => __( 'Service Type', 'cp-library' ),
			'query_callback' => [ $this, 'modify_service_type_query' ],
		] );

		// Speaker context
		$this->register_context( 'speaker', [
			'label'          => __( 'Speaker', 'cp-library' ),
			'query_callback' => [ $this, 'modify_speaker_query' ],
		] );
	}

	/**
	 * Register default facets for sermons
	 */
	protected function register_default_facets() {
		// Register all public taxonomies for sermons
		$this->register_taxonomies_for_post_type( $this->post_type );

		// Register speaker facet
		$this->register_source_facet( 'speaker', 'speaker', [
			'label'     => __( 'Speaker', 'cp-library' ),
			'param'     => 'facet-speaker',
			'query_var' => 'speaker',
		] );

		// Register service type facet if enabled
		if ( Settings::get_item( 'enable_service_types', true ) ) {
			$this->register_source_facet( 'service-type', 'service_type', [
				'label'     => __( 'Service Type', 'cp-library' ),
				'param'     => 'facet-service-type',
				'query_var' => 'service-type',
			] );
		}

		// Register date facet (year)
		$this->register_facet( 'year', [
			'label'            => __( 'Year', 'cp-library' ),
			'param'            => 'facet-year',
			'query_var'        => 'year',
			'type'             => 'date',
			'query_callback'   => [ $this, 'query_year_facet' ],
			'options_callback' => [ $this, 'get_year_options' ],
		] );

		// Allow other plugins to register facets
		do_action( 'cpl_register_sermon_facets', $this );
	}

	/**
	 * Add custom query vars for service types
	 *
	 * @param array $vars Query vars
	 *
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'cpl_service_types';

		return $vars;
	}

	/**
	 * Modify query for service type context
	 *
	 * @param array $query_args   Query arguments
	 * @param array $context_args Context arguments
	 *
	 * @return array
	 */
	public function modify_service_type_query( $query_args, $context_args ) {
		if ( ! empty( $context_args['service_type_id'] ) ) {
			$query_args['cpl_service_types'] = $context_args['service_type_id'];
		}

		return $query_args;
	}

	/**
	 * Modify query for speaker context
	 *
	 * @param array $query_args   Query arguments
	 * @param array $context_args Context arguments
	 *
	 * @return array
	 */
	public function modify_speaker_query( $query_args, $context_args ) {
		if ( ! empty( $context_args['speaker_id'] ) ) {
			$meta_query = isset( $query_args['meta_query'] ) ? $query_args['meta_query'] : [];

			$meta_query[] = [
				'key'     => 'cp_speaker',
				'value'   => $context_args['speaker_id'],
				'compare' => '=',
			];

			$query_args['meta_query'] = $meta_query;
		}

		return $query_args;
	}

	/**
	 * AJAX handler for filtering sermons
	 */
	public function ajax_filter_sermons() {
		// Verify nonce
		$nonce = Helpers::get_param( $_POST, 'nonce', '' );
		if ( ! wp_verify_nonce( $nonce, 'cpl_filter_nonce' ) ) {
			wp_send_json_error( [
				'message' => 'Security check failed',
				'code'    => 'invalid_nonce',
				'status'  => 403
			] );
		}

		// Check post type if specified
		$post_type = Helpers::get_param( $_POST, 'post_type', $this->post_type );
		if ( $post_type !== $this->post_type ) {
			// If post type doesn't match, let the Init class router handle it
			return;
		}

		try {
			// Get filter parameters
			$filters        = Helpers::get_param( $_POST, 'filters', [] );
			$context        = Helpers::get_param( $_POST, 'context', 'archive' );
			$context_args   = Helpers::get_param( $_POST, 'context_args', [] );
			$paged          = intval( Helpers::get_param( $_POST, 'paged', 1 ) );
			$posts_per_page = intval( Helpers::get_param( $_POST, 'posts_per_page', get_option( 'posts_per_page' ) ) );
			$template       = Helpers::get_param( $_POST, 'template', 'grid' );

			// Build query args
			$query_args = [
				'post_type'      => $this->post_type,
				'post_status'    => 'publish',
				'paged'          => $paged,
				'posts_per_page' => $posts_per_page,
			];

			// Apply context-specific query modifications
			$context_config = $this->get_context( $context );
			if ( $context_config && isset( $context_config['query_callback'] ) && is_callable( $context_config['query_callback'] ) ) {
				$query_args = call_user_func( $context_config['query_callback'], $query_args, $context_args );
			}

			// Apply each filter
			foreach ( $filters as $facet_id => $values ) {
				if ( empty( $values ) ) {
					continue;
				}

				$facet = $this->get_facet( $facet_id );
				if ( ! $facet ) {
					continue;
				}

				// Apply filter using query callback
				if ( isset( $facet['query_callback'] ) && is_callable( $facet['query_callback'] ) ) {
					$tmp_query = new \WP_Query();
					call_user_func( $facet['query_callback'], $tmp_query, $values, $facet );

					// Merge any tax_query added by the callback
					if ( $tax_query = $tmp_query->get( 'tax_query' ) ) {
						$existing_tax_query      = isset( $query_args['tax_query'] ) ? $query_args['tax_query'] : [];
						$query_args['tax_query'] = array_merge( $existing_tax_query, $tax_query );
					}

					// Merge any meta_query added by the callback
					if ( $meta_query = $tmp_query->get( 'meta_query' ) ) {
						$existing_meta_query      = isset( $query_args['meta_query'] ) ? $query_args['meta_query'] : [];
						$query_args['meta_query'] = array_merge( $existing_meta_query, $meta_query );
					}

					// Merge any date_query added by the callback
					if ( $date_query = $tmp_query->get( 'date_query' ) ) {
						$existing_date_query      = isset( $query_args['date_query'] ) ? $query_args['date_query'] : [];
						$query_args['date_query'] = array_merge( $existing_date_query, $date_query );
					}
				}
			}

			// Allow other plugins to modify the query
			$query_args = apply_filters( 'cpl_filter_sermons_query_args', $query_args, $filters, $context, $context_args );

			// Run the query
			$query = new \WP_Query( $query_args );

			// Build the response
			ob_start();

			if ( $query->have_posts() ) {
				while ( $query->have_posts() ) {
					$query->the_post();
					cp_library()->templates->get_template_part( 'parts/item-' . $template );
				}
			} else {
				echo '<div class="cpl-no-results">' . __( 'No sermons found matching your criteria.', 'cp-library' ) . '</div>';
			}

			$html = ob_get_clean();
			wp_reset_postdata();

			// Include pagination if needed
			$pagination = '';
			if ( $query->max_num_pages > 1 ) {
				ob_start();

				$big = 999999999;
				echo '<div class="cpl-pagination">';
				echo paginate_links( [
					'base'    => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
					'format'  => '?paged=%#%',
					'current' => max( 1, $paged ),
					'total'   => $query->max_num_pages,
				] );
				echo '</div>';

				$pagination = ob_get_clean();
			}

			wp_send_json_success( [
				'html'       => $html,
				'pagination' => $pagination,
				'count'      => $query->found_posts,
				'max_pages'  => $query->max_num_pages,
				'post_type'  => $this->post_type
			] );
		} catch ( \Exception $e ) {
			wp_send_json_error( [
				'message'   => $e->getMessage(),
				'code'      => 'filter_error',
				'status'    => 500,
				'post_type' => $this->post_type
			] );
		}
	}


	/**
	 * Apply year facet to query
	 *
	 * @param \WP_Query $query        The query object
	 * @param array     $values       Selected years
	 * @param array     $facet_config Facet configuration
	 */
	public function query_year_facet( $query, $values, $facet_config ) {
		if ( empty( $values ) ) {
			return;
		}

		// Create date query for the selected years
		$date_query = $query->get( 'date_query' ) ?: [];

		foreach ( $values as $year ) {
			$date_query[] = [
				'year' => intval( $year ),
			];
		}

		if ( count( $values ) > 1 ) {
			$date_query['relation'] = 'OR';
		}

		$query->set( 'date_query', $date_query );
	}


	/**
	 * Get year options
	 *
	 * @param array $args Arguments
	 *
	 * @return array Year options
	 */
	public function get_year_options( $args = [] ) {
		global $wpdb;

		$args = wp_parse_args( $args, [
			'threshold' => 1,
			'post_type' => $this->post_type,
			'post__in'  => [],
		] );

		// Build where clause for post__in
		$where_in = '';
		if ( ! empty( $args['post__in'] ) ) {
			$where_in = ' AND ID IN (' . implode( ',', array_map( 'absint', $args['post__in'] ) ) . ')';
		}

		// Get years with post counts
		$query = $wpdb->prepare(
			"SELECT
                YEAR(post_date) as year,
                COUNT(*) as count
            FROM
                {$wpdb->posts}
            WHERE
                post_type = %s
                AND post_status = 'publish'
                {$where_in}
            GROUP BY
                YEAR(post_date)
            HAVING
                count >= %d
            ORDER BY
                year DESC",
			$this->post_type,
			$args['threshold']
		);

		$results = $wpdb->get_results( $query );

		// Format results
		$years = [];
		foreach ( $results as $result ) {
			$years[] = (object) [
				'id'    => $result->year,
				'value' => $result->year,
				'title' => $result->year,
				'count' => $result->count,
			];
		}

		return $years;
	}

	/**
	 * Apply facet filters to a WP_Query with sermon-specific handling
	 *
	 * @param \WP_Query $query The query object
	 */
	public function apply_facet_filters( $query ) {
		// First, apply the standard facet filters from the parent class
		parent::apply_facet_filters( $query );

		// Skip if we're not handling this query
		if ( is_admin() ) {
			return;
		}

		// Skip if not a post type we're handling
		if ( ! $query_post_type = $query->get( 'post_type' ) ) {
			return;
		}

		if ( ! is_array( $query_post_type ) ) {
			$query_post_type = [ $query_post_type ];
		}

		if ( ! in_array( $this->post_type, $query_post_type ) ) {
			return;
		}

		// Apply sermon-specific query modifications based on context
		$this->apply_context_specific_query_mods( $query );

		// Allow other plugins to modify the query
		do_action( 'cpl_sermon_filter_query', $query, $this->get_active_facets_from_request(), $this );
	}

	/**
	 * Apply context-specific query modifications
	 *
	 * @param \WP_Query $query The query object
	 */
	protected function apply_context_specific_query_mods( $query ) {
		// Determine the current context
		$context = '';

		// Check if we're on a taxonomy archive
		if ( is_tax() ) {
			$context = 'taxonomy';
			$this->apply_taxonomy_archive_mods( $query );
		}

		// Check if we're on a speaker page
		if ( is_singular( 'cpl_speaker' ) ) {
			$context = 'speaker';
			$this->apply_speaker_page_mods( $query );
		}

		// Allow plugins to apply additional context-specific mods
		do_action( "cpl_sermon_filter_query_{$context}", $query, $this );
	}

	/**
	 * Apply taxonomy archive specific query modifications
	 *
	 * @param \WP_Query $query The query object
	 */
	protected function apply_taxonomy_archive_mods( $query ) {
		// Get current taxonomy and term
		$taxonomy = get_query_var( 'taxonomy' );
		$term     = get_query_var( 'term' );

		if ( empty( $taxonomy ) || empty( $term ) ) {
			return;
		}

		// Apply custom sorting for specific taxonomies
		if ( 'cpl_scripture' === $taxonomy ) {
			// For scripture archives, we might want to sort by book order
			// This would require additional logic to map books to a sortable order
		} elseif ( 'cpl_season' === $taxonomy ) {
			// For season archives, sort by date descending as default
			if ( ! $query->get( 'orderby' ) ) {
				$query->set( 'orderby', 'date' );
				$query->set( 'order', 'DESC' );
			}
		}
	}

	/**
	 * Apply speaker page specific query modifications
	 *
	 * @param \WP_Query $query The query object
	 */
	protected function apply_speaker_page_mods( $query ) {
		$speaker_id = get_the_ID();

		if ( empty( $speaker_id ) ) {
			return;
		}

		// Set up meta query to find sermons by this speaker
		$meta_query   = $query->get( 'meta_query' ) ?: [];
		$meta_query[] = [
			'key'     => 'cp_speaker',
			'value'   => $speaker_id,
			'compare' => '=',
		];

		$query->set( 'meta_query', $meta_query );

		// Default to date sort
		if ( ! $query->get( 'orderby' ) ) {
			$query->set( 'orderby', 'date' );
			$query->set( 'order', 'DESC' );
		}
	}

}
