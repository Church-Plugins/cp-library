<?php
/**
 * Series Filter Manager
 *
 * Implementation of the AbstractFilterManager for series post type (cpl_item_type).
 *
 * @package CP_Library\Filters\Types
 * @since 1.6.0
 */

namespace CP_Library\Filters\Types;

use CP_Library\Filters\AbstractFilterManager;
use CP_Library\Admin\Settings;

/**
 * SeriesFilterManager class - Filter manager for series content type.
 *
 * This class handles the series-specific implementation of the filter system,
 * registering series-related facets and handling series-specific queries.
 *
 * @since 1.6.0
 */
class SeriesFilterManager extends AbstractFilterManager {

    /**
     * Constructor
     *
     * @param array $args Configuration arguments
     */
    public function __construct( $args = [] ) {
        $args['post_type'] = 'cpl_item_type';
        parent::__construct( $args );
    }

    /**
     * Setup actions and filters specific to series filter manager
     */
    protected function actions() {
        parent::actions();

        // Register AJAX handlers
        add_action( 'wp_ajax_cpl_filter_series', [ $this, 'ajax_filter_series' ] );
        add_action( 'wp_ajax_nopriv_cpl_filter_series', [ $this, 'ajax_filter_series' ] );
    }

    /**
     * Register default contexts for series
     */
    protected function register_default_contexts() {
        // Archive context
        $this->register_context( 'archive', [
            'label' => __( 'Archive', 'cp-library' ),
            'query_callback' => [ $this, 'modify_archive_query' ],
        ] );

        // Season context
        $this->register_context( 'season', [
            'label' => __( 'Season', 'cp-library' ),
            'query_callback' => [ $this, 'modify_season_query' ],
        ] );

        // Topic context
        $this->register_context( 'topic', [
            'label' => __( 'Topic', 'cp-library' ),
            'query_callback' => [ $this, 'modify_topic_query' ],
        ] );
    }

    /**
     * Modify query for archive context
     *
     * @param array $query_args Query arguments
     * @param array $context_args Context arguments
     * @return array
     */
    public function modify_archive_query( $query_args, $context_args ) {
        // Default ordering for series archives
        if ( ! isset( $query_args['orderby'] ) ) {
            $query_args['orderby'] = 'date';
            $query_args['order'] = 'DESC';
        }

        return $query_args;
    }

    /**
     * Modify query for season context
     *
     * @param array $query_args Query arguments
     * @param array $context_args Context arguments
     * @return array
     */
    public function modify_season_query( $query_args, $context_args ) {
        if ( ! empty( $context_args['season_id'] ) ) {
            $tax_query = isset( $query_args['tax_query'] ) ? $query_args['tax_query'] : [];

            $tax_query[] = [
                'taxonomy' => 'cpl_season',
                'field'    => 'term_id',
                'terms'    => $context_args['season_id'],
            ];

            $query_args['tax_query'] = $tax_query;
        }

        return $query_args;
    }

    /**
     * Modify query for topic context
     *
     * @param array $query_args Query arguments
     * @param array $context_args Context arguments
     * @return array
     */
    public function modify_topic_query( $query_args, $context_args ) {
        if ( ! empty( $context_args['topic_id'] ) ) {
            $tax_query = isset( $query_args['tax_query'] ) ? $query_args['tax_query'] : [];

            $tax_query[] = [
                'taxonomy' => 'cpl_topics',
                'field'    => 'term_id',
                'terms'    => $context_args['topic_id'],
            ];

            $query_args['tax_query'] = $tax_query;
        }

        return $query_args;
    }

    /**
     * Register default facets for series
     */
    protected function register_default_facets() {
        // Register taxonomy facets that apply to series
        $this->register_taxonomies_for_post_type($this->post_type);

        // Register year facet
        $this->register_facet( 'year', [
            'label'             => __( 'Year', 'cp-library' ),
            'param'             => 'facet-year',
            'query_var'         => 'year',
            'type'              => 'date',
            'query_callback'    => [ $this, 'query_year_facet' ],
            'options_callback'  => [ $this, 'get_year_options' ],
        ]);

        // Register sermon count facet
        $this->register_facet( 'sermon_count', [
            'label'             => __( 'Number of Sermons', 'cp-library' ),
            'param'             => 'facet-sermon-count',
            'query_var'         => 'sermon_count',
            'type'              => 'meta',
            'meta_key'          => '_cpl_sermon_count',
            'query_callback'    => [ $this, 'query_sermon_count_facet' ],
            'options_callback'  => [ $this, 'get_sermon_count_options' ],
        ]);
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
     * Apply sermon count facet to query
     *
     * @param \WP_Query $query        The query object
     * @param array     $values       Selected count ranges
     * @param array     $facet_config Facet configuration
     */
    public function query_sermon_count_facet( $query, $values, $facet_config ) {
        if ( empty( $values ) ) {
            return;
        }

        // Get meta query
        $meta_query = $query->get( 'meta_query' ) ?: [];
        $range_queries = [];

        // Parse range values (e.g., "1-5", "6-10", "10+")
        foreach ( $values as $range ) {
            if ( strpos( $range, '-' ) !== false ) {
                // Range like "1-5"
                list( $min, $max ) = explode( '-', $range );
                $range_queries[] = [
                    'key'     => '_cpl_sermon_count',
                    'value'   => [ intval( $min ), intval( $max ) ],
                    'type'    => 'NUMERIC',
                    'compare' => 'BETWEEN',
                ];
            } elseif ( strpos( $range, '+' ) !== false ) {
                // Range like "10+"
                $min = intval( str_replace( '+', '', $range ) );
                $range_queries[] = [
                    'key'     => '_cpl_sermon_count',
                    'value'   => $min,
                    'type'    => 'NUMERIC',
                    'compare' => '>=',
                ];
            } else {
                // Exact number
                $range_queries[] = [
                    'key'     => '_cpl_sermon_count',
                    'value'   => intval( $range ),
                    'type'    => 'NUMERIC',
                    'compare' => '=',
                ];
            }
        }

        // Add range queries with OR relation
        if ( ! empty( $range_queries ) ) {
            if ( count( $range_queries ) > 1 ) {
                $meta_query[] = [
                    'relation' => 'OR',
                    $range_queries,
                ];
            } else {
                $meta_query[] = $range_queries[0];
            }
        }

        $query->set( 'meta_query', $meta_query );
    }

    /**
     * Get year options
     *
     * @param array $args Arguments
     * @return array Year options
     */
    public function get_year_options( $args = [] ) {
        global $wpdb;

        $args = wp_parse_args( $args, [
            'threshold'  => 1,
            'post_type'  => $this->post_type,
            'post__in'   => [],
        ]);

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
     * Apply facet filters to a WP_Query with series-specific handling
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

        // Apply series-specific query modifications based on context
        $this->apply_context_specific_query_mods( $query );

        // Allow other plugins to modify the query
        do_action( 'cpl_series_filter_query', $query, $this->get_active_facets_from_request(), $this );
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

        // Allow plugins to apply additional context-specific mods
        do_action( "cpl_series_filter_query_{$context}", $query, $this );
    }

    /**
     * Apply taxonomy archive specific query modifications
     *
     * @param \WP_Query $query The query object
     */
    protected function apply_taxonomy_archive_mods( $query ) {
        // Get current taxonomy and term
        $taxonomy = get_query_var( 'taxonomy' );
        $term = get_query_var( 'term' );

        if ( empty( $taxonomy ) || empty( $term ) ) {
            return;
        }

        // Apply custom sorting based on taxonomy
        if ( 'cpl_season' === $taxonomy ) {
            // For season archives, sort by date descending as default
            if ( ! $query->get( 'orderby' ) ) {
                $query->set( 'orderby', 'date' );
                $query->set( 'order', 'DESC' );
            }
        } else if ( 'cpl_topics' === $taxonomy ) {
            // For topic archives, sort by title as default
            if ( ! $query->get( 'orderby' ) ) {
                $query->set( 'orderby', 'title' );
                $query->set( 'order', 'ASC' );
            }
        }
    }

    /**
     * AJAX handler for filtering series
     */
    public function ajax_filter_series() {
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
            $filters = Helpers::get_param( $_POST, 'filters', [] );
            $context = Helpers::get_param( $_POST, 'context', 'archive' );
            $context_args = Helpers::get_param( $_POST, 'context_args', [] );
            $paged = intval( Helpers::get_param( $_POST, 'paged', 1 ) );
            $posts_per_page = intval( Helpers::get_param( $_POST, 'posts_per_page', get_option( 'posts_per_page' ) ) );
            $template = Helpers::get_param( $_POST, 'template', 'grid' );

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
                        $existing_tax_query = isset( $query_args['tax_query'] ) ? $query_args['tax_query'] : [];
                        $query_args['tax_query'] = array_merge( $existing_tax_query, $tax_query );
                    }

                    // Merge any meta_query added by the callback
                    if ( $meta_query = $tmp_query->get( 'meta_query' ) ) {
                        $existing_meta_query = isset( $query_args['meta_query'] ) ? $query_args['meta_query'] : [];
                        $query_args['meta_query'] = array_merge( $existing_meta_query, $meta_query );
                    }

                    // Merge any date_query added by the callback
                    if ( $date_query = $tmp_query->get( 'date_query' ) ) {
                        $existing_date_query = isset( $query_args['date_query'] ) ? $query_args['date_query'] : [];
                        $query_args['date_query'] = array_merge( $existing_date_query, $date_query );
                    }
                }
            }

            // Allow other plugins to modify the query
            $query_args = apply_filters( 'cpl_filter_series_query_args', $query_args, $filters, $context, $context_args );

            // Run the query
            $query = new \WP_Query( $query_args );

            // Build the response
            ob_start();

            if ( $query->have_posts() ) {
                while ( $query->have_posts() ) {
                    $query->the_post();
                    cp_library()->templates->get_template_part( 'parts/item-type-' . $template );
                }
            } else {
                echo '<div class="cpl-no-results">' . __( 'No series found matching your criteria.', 'cp-library' ) . '</div>';
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
     * Get sermon count options
     *
     * @param array $args Arguments
     * @return array Sermon count options
     */
    public function get_sermon_count_options( $args = [] ) {
        // Define count ranges for series
        $ranges = [
            '1-3'  => __( '1-3 sermons', 'cp-library' ),
            '4-6'  => __( '4-6 sermons', 'cp-library' ),
            '7-12' => __( '7-12 sermons', 'cp-library' ),
            '13+'  => __( '13+ sermons', 'cp-library' ),
        ];

        global $wpdb;

        $args = wp_parse_args( $args, [
            'threshold'  => 1,
            'post_type'  => $this->post_type,
            'post__in'   => [],
        ]);

        // Build where clause for post__in
        $where_in = '';
        if ( ! empty( $args['post__in'] ) ) {
            $where_in = ' AND post_id IN (' . implode( ',', array_map( 'absint', $args['post__in'] ) ) . ')';
        }

        // Get counts of series in each range
        $range_counts = [];

        // 1-3 range
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta}
            WHERE meta_key = '_cpl_sermon_count'
            AND meta_value BETWEEN 1 AND 3
            {$where_in}",
        );
        $range_counts['1-3'] = $wpdb->get_var( $sql );

        // 4-6 range
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta}
            WHERE meta_key = '_cpl_sermon_count'
            AND meta_value BETWEEN 4 AND 6
            {$where_in}",
        );
        $range_counts['4-6'] = $wpdb->get_var( $sql );

        // 7-12 range
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta}
            WHERE meta_key = '_cpl_sermon_count'
            AND meta_value BETWEEN 7 AND 12
            {$where_in}",
        );
        $range_counts['7-12'] = $wpdb->get_var( $sql );

        // 13+ range
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta}
            WHERE meta_key = '_cpl_sermon_count'
            AND meta_value >= 13
            {$where_in}",
        );
        $range_counts['13+'] = $wpdb->get_var( $sql );

        // Format results
        $options = [];
        foreach ( $ranges as $range => $label ) {
            $count = isset( $range_counts[$range] ) ? intval( $range_counts[$range] ) : 0;

            // Skip ranges with no series or below threshold
            if ( $count < $args['threshold'] ) {
                continue;
            }

            $options[] = (object) [
                'id'    => $range,
                'value' => $range,
                'title' => $label,
                'count' => $count,
            ];
        }

        return $options;
    }

    /**
     * Render series filters for templates
     *
     * @param array $args Arguments for rendering filters
     * @return string HTML output
     */
    public function render_series_filters( $args = [] ) {
        $args = wp_parse_args( $args, [
            'context'         => 'archive',
            'context_args'    => [],
            'show_search'     => true,
            'container_class' => '',
            'template'        => 'grid',
            'taxonomies'      => [ 'cpl_season', 'cpl_topics' ],
            'show_count'      => true,
            'sermon_count'    => true,
            'year'            => true,
        ]);

        // Default taxonomies to show
        $enabled_facets = [];

        // Add taxonomy facets
        if ( ! empty( $args['taxonomies'] ) ) {
            foreach ( $args['taxonomies'] as $taxonomy ) {
                $enabled_facets[] = $taxonomy;
            }
        }

        // Add year facet
        if ( ! empty( $args['year'] ) ) {
            $enabled_facets[] = 'year';
        }

        // Add sermon count facet
        if ( ! empty( $args['sermon_count'] ) ) {
            $enabled_facets[] = 'sermon_count';
        }

        // Get disabled filters from settings
        $settings_disabled_filters = Settings::get_item_type_disabled_filters();

        // Calculate disabled filters from args (what's NOT enabled)
        $args_disabled_filters = array_diff( array_keys( $this->get_facets() ), $enabled_facets );

        // Merge both: use disabled from settings + disabled from args
        $disabled_filters = array_unique( array_merge( $settings_disabled_filters, $args_disabled_filters ) );

        // Render filter form
        $output = $this->render_filter_form([
            'context'          => $args['context'],
            'context_args'     => $args['context_args'],
            'disabled_filters' => $disabled_filters,
            'show_search'      => $args['show_search'],
            'container_class'  => $args['container_class'],
            'post_type'        => $this->post_type,
            'template'         => $args['template'],
        ]);

        // Render selected filters
        $output .= $this->render_selected_filters([
            'context'      => $args['context'],
            'context_args' => $args['context_args'],
            'post_type'    => $this->post_type,
        ]);

        return $output;
    }
}
