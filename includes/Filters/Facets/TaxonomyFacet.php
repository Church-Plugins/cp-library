<?php
/**
 * Taxonomy Facet Type
 *
 * Implementation of the FacetType interface for taxonomy-based filtering.
 *
 * @package CP_Library\Filters
 * @since 1.6.0
 */

namespace CP_Library\Filters\Facets;

use CP_Library\Filters\AbstractFacet;
use CP_Library\Admin\Settings;

/**
 * TaxonomyFacet class - Taxonomy-based facet implementation.
 *
 * Provides filtering based on WordPress taxonomy terms.
 *
 * @since 1.6.0
 */
class TaxonomyFacet extends AbstractFacet {

    /**
     * Facet type identifier
     *
     * @var string
     */
    protected $type = 'taxonomy';

    /**
     * Taxonomy name
     *
     * @var string
     */
    protected $taxonomy = '';

    /**
     * Whether the taxonomy is hierarchical
     *
     * @var bool
     */
    protected $hierarchical = false;

    /**
     * Constructor
     *
     * @param array $args Configuration arguments
     */
    public function __construct( $args = [] ) {
        parent::__construct( $args );

        if ( isset( $args['taxonomy'] ) ) {
            $this->taxonomy = $args['taxonomy'];

            // Get taxonomy object to set defaults
            $taxonomy_object = get_taxonomy( $this->taxonomy );

            if ( ! $this->label && $taxonomy_object ) {
                $this->label = $taxonomy_object->labels->singular_name;
            }

            if ( isset( $taxonomy_object->hierarchical ) ) {
                $this->hierarchical = $taxonomy_object->hierarchical;
            }
        }

        if ( isset( $args['hierarchical'] ) ) {
            $this->hierarchical = (bool) $args['hierarchical'];
        }
    }

    /**
     * Get the taxonomy name
     *
     * @return string Taxonomy name
     */
    public function get_taxonomy() {
        return $this->taxonomy;
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
        if ( empty( $this->taxonomy ) || empty( $values ) ) {
            return;
        }

        // Get existing tax queries
        $tax_query = $query->get( 'tax_query' ) ?: [];

        // Add our taxonomy query
        $tax_query[] = [
            'taxonomy' => $this->taxonomy,
            'field'    => 'slug',
            'terms'    => $values,
            'operator' => 'IN',
        ];

        // Set the tax query
        $query->set( 'tax_query', $tax_query );
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
            'threshold'  => 3,
            'post__in'   => [],
            'context'    => 'archive',
        ]);

        if ( empty( $this->taxonomy ) || empty( $args['post_type'] ) ) {
            return [];
        }

        // Different default for scripture
        $default_order_by = 'cpl_scripture' === $this->taxonomy ? 'name' : 'sermon_count';
        $order_by = Settings::get_advanced( 'sort_' . $this->taxonomy, $default_order_by );
        $order = 'sermon_count' === $order_by ? 'DESC' : 'ASC';

        // Build where clause for post__in
        $where_in = '';
        if ( ! empty( $args['post__in'] ) ) {
            $where_in = ' AND p.ID IN (' . implode( ',', array_map( 'absint', $args['post__in'] ) ) . ')';
        }

        // Build the query with the where clause included manually
        $query = $wpdb->prepare(
            "SELECT
                t.name AS title,
                t.term_id AS id,
                t.slug AS value,
                COUNT(p.ID) AS count
            FROM
                {$wpdb->terms} AS t
            LEFT JOIN
                {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
            LEFT JOIN
                {$wpdb->term_relationships} AS tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            LEFT JOIN
                {$wpdb->posts} AS p ON tr.object_id = p.ID {$where_in}
            WHERE
                tt.taxonomy = %s
            AND
                p.post_type = %s
            AND
                p.post_status = 'publish'
            GROUP BY
                t.term_id
            HAVING
                count >= %d
            ORDER BY
                " . ( 'sermon_count' === $order_by ? "count {$order}" : "title {$order}" ),
            $this->taxonomy,
            $args['post_type'],
            $args['threshold']
        );

        $results = $wpdb->get_results( $query );

        // Special handling for scripture ordering
        if ( 'cpl_scripture' === $this->taxonomy && 'name' === $order_by ) {
            usort( $results, [ cp_library()->setup->taxonomies->scripture, 'sort_scripture' ] );
        }

        return $this->format_options( $results ?: [] );
    }

    /**
     * Get the display value for a selected taxonomy term
     *
     * @param string $value The term slug
     * @param array $facet_config The facet configuration
     * @return string The term name
     */
    protected function get_display_value($value, $facet_config) {
        // Get the term by slug
        $term = get_term_by('slug', $value, $this->taxonomy);

        if (!$term || is_wp_error($term)) {
            return $value; // Fallback to the slug if term not found
        }

        return $term->name;
    }
}
