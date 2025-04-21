<?php
/**
 * Meta Facet Type
 *
 * Implementation of the FacetType interface for meta-based filtering.
 *
 * @package CP_Library\Filters
 * @since 1.6.0
 */

namespace CP_Library\Filters\Facets;

use CP_Library\Filters\AbstractFacet;

/**
 * MetaFacet class - Meta-based facet implementation.
 *
 * Provides filtering based on WordPress post meta values.
 *
 * @since 1.6.0
 */
class MetaFacet extends AbstractFacet {

    /**
     * Facet type identifier
     *
     * @var string
     */
    protected $type = 'meta';

    /**
     * Meta key to filter by
     *
     * @var string
     */
    protected $meta_key = '';

    /**
     * Constructor
     *
     * @param array $args Configuration arguments
     */
    public function __construct( $args = [] ) {
        parent::__construct( $args );

        if ( isset( $args['meta_key'] ) ) {
            $this->meta_key = $args['meta_key'];
        }

        if ( ! $this->label && ! empty( $this->meta_key ) ) {
            // Generate a default label from the meta key
            $this->label = ucwords( str_replace( ['_', '-'], ' ', $this->meta_key ) );
        }
    }

    /**
     * Get the meta key
     *
     * @return string Meta key
     */
    public function get_meta_key() {
        return $this->meta_key;
    }

    /**
     * Modify a WP_Query object to apply this facet's filtering
     *
     * @param \WP_Query $query        The query to modify
     * @param array     $values       The filter values to apply
     * @param array     $facet_config The facet configuration
     * 
     * @return void
     */
    public function apply_to_query( $query, $values, $facet_config ) {
        if ( empty( $this->meta_key ) || empty( $values ) ) {
            return;
        }

        // Get existing meta queries
        $meta_query = $query->get( 'meta_query' ) ?: [];

        // Add our meta query
        $meta_query[] = [
            'key'     => $this->meta_key,
            'value'   => $values,
            'compare' => 'IN',
        ];

        // Set the meta query
        $query->set( 'meta_query', $meta_query );
    }

    /**
     * Get available options for this facet
     *
     * @param array $args Arguments for generating options including:
     *                     - post_type: The post type
     *                     - threshold: Minimum count to include
     *                     - post__in: Limit to these post IDs
     *                     - context: Current filter context
     * 
     * @return array Array of option objects
     */
    public function get_options( $args ) {
        global $wpdb;

        $args = wp_parse_args( $args, [
            'post_type'  => '',
            'threshold'  => 1,
            'post__in'   => [],
            'orderby'    => 'count',
            'order'      => 'DESC',
        ]);

        if ( empty( $this->meta_key ) || empty( $args['post_type'] ) ) {
            return [];
        }

        // Build where clause for post__in
        $where_in = '';
        if ( ! empty( $args['post__in'] ) ) {
            $where_in = ' AND p.ID IN (' . implode( ',', array_map( 'absint', $args['post__in'] ) ) . ')';
        }

        // Get unique meta values with counts
        $query = $wpdb->prepare(
            "SELECT meta_value AS value, COUNT(*) AS count, meta_value AS title, meta_value AS id
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = %s
            AND p.post_type = %s
            AND p.post_status = 'publish'
            {$where_in}
            GROUP BY meta_value
            HAVING count >= %d
            ORDER BY " . ( $args['orderby'] === 'count' ? "count {$args['order']}" : "meta_value {$args['order']}" ),
            $this->meta_key,
            $args['post_type'],
            $args['threshold']
        );

        $results = $wpdb->get_results( $query );

        return $this->format_options( $results ?: [] );
    }
}