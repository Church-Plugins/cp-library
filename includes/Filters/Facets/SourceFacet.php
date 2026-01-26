<?php
/**
 * Source Facet Type
 *
 * Implementation of the FacetType interface for source-based filtering (speakers, service types).
 *
 * @package CP_Library\Filters
 * @since 1.6.0
 */

namespace CP_Library\Filters\Facets;

use CP_Library\Filters\AbstractFacet;
use CP_Library\Admin\Settings;

/**
 * SourceFacet class - Source-based facet implementation.
 *
 * Provides filtering based on CP Library sources (speakers, service types).
 *
 * @since 1.6.0
 */
class SourceFacet extends AbstractFacet {

    /**
     * Facet type identifier
     *
     * @var string
     */
    protected $type = 'source';

    /**
     * Source type (speaker, service_type)
     *
     * @var string
     */
    protected $source_type = '';

    /**
     * Constructor
     *
     * @param array $args Configuration arguments
     */
    public function __construct( $args = [] ) {
        parent::__construct( $args );

        if ( isset( $args['source_type'] ) ) {
            $this->source_type = $args['source_type'];
        }

        if ( ! $this->label && ! empty( $this->source_type ) ) {
            // Generate a default label from the source type
            $labels = [
                'speaker'      => __( 'Speaker', 'cp-library' ),
                'service_type' => __( 'Service Type', 'cp-library' ),
            ];

            $this->label = $labels[$this->source_type] ?? ucwords( str_replace( '_', ' ', $this->source_type ) );
        }
    }

    /**
     * Get the source type
     *
     * @return string Source type
     */
    public function get_source_type() {
        return $this->source_type;
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
        if ( empty( $this->source_type ) || empty( $values ) ) {
            return;
        }

        switch ( $this->source_type ) {
            case 'speaker':
                $query->set( 'cpl_speakers', $values );
                break;

            case 'service_type':
                // Set service types to query
                $query->set( 'cpl_service_types', $values );
                break;

            default:
                // For any other source type, try to use a filter
                do_action( "cpl_filter_source_{$this->source_type}", $query, $values, $facet_config );
                break;
        }
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
            'orderby'    => Settings::get_advanced( "sort_{$this->source_type}", 'count' ),
        ]);

        if ( empty( $this->source_type ) || empty( $args['post_type'] ) ) {
            return [];
        }

        // Build where clause for post__in
        $where_clause = '1 = 1';
        if ( ! empty( $args['post__in'] ) ) {
            $where_clause = 'sermon.origin_id IN (' . implode( ',', array_map( 'absint', $args['post__in'] ) ) . ')';
        }

        $order_by = ( 'count' === $args['orderby'] ) ? 'count DESC' : 'title ASC';
        $source_type_title = 'service_type' === $this->source_type ? 'service_type' : $this->source_type;

        // Format the SQL manually with the where clause
        $sql = $wpdb->prepare(
            "SELECT
                source.id,
                source.origin_id AS value,
                source.title AS title,
                COUNT(sermon.id) AS count
            FROM
                %1\$s AS source
            LEFT JOIN
                %2\$s AS meta ON meta.source_id = source.id
            INNER JOIN
                %3\$s AS type ON meta.source_type_id = type.id AND type.title = '%4\$s'
            LEFT JOIN
                %5\$s AS sermon ON meta.item_id = sermon.id AND {$where_clause}
            GROUP BY
                source.id
            HAVING
                count >= %6\$d
            ORDER BY
                {$order_by};",
            $wpdb->prefix . 'cp_source',
            $wpdb->prefix . 'cp_source_meta',
            $wpdb->prefix . 'cp_source_type',
            $source_type_title,
            $wpdb->prefix . 'cpl_' . str_replace( 'cpl_', '', $args['post_type'] ),
            $args['threshold']
        );

        $results = $wpdb->get_results( $sql );

        return $this->format_options( $results ?: [] );
    }

    /**
     * Get the display value for a selected source
     *
     * @param string $value The source ID
     * @param array $facet_config The facet configuration
     * @return string The source title
     */
    protected function get_display_value($value, $facet_config) {
        try {
            // Handle different source types
            switch ($this->source_type) {
                case 'speaker':
                    $source = \CP_Library\Models\Speaker::get_instance_from_origin($value);
                    break;

                case 'service_type':
                    $source = \CP_Library\Models\ServiceType::get_instance_from_origin($value);
                    break;

                default:
                    return $value;
            }

            return $source->title;
        } catch (\Exception $e) {
            return $value; // Fallback if source can't be loaded
        }
    }
}
