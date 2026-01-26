<?php
/**
 * FacetType Interface
 *
 * Defines the contract for facet type implementations.
 *
 * @package CP_Library\Filters
 * @since 1.6.0
 */

namespace CP_Library\Filters;

/**
 * FacetType interface - Contract for filter facet implementations.
 *
 * This interface defines the required methods that all facet types must implement
 * to provide consistent behavior in the filter system.
 *
 * @since 1.6.0
 */
interface FacetType {

    /**
     * Get the facet type identifier
     *
     * @return string The facet type ID
     */
    public function get_type();

    /**
     * Get the facet display name
     *
     * @return string The facet type display name
     */
    public function get_label();

    /**
     * Get post types this facet is compatible with
     *
     * @return array Array of post type names
     */
    public function get_compatible_post_types();

    /**
     * Modify a WP_Query object to apply this facet's filtering
     *
     * @param \WP_Query $query        The query to modify
     * @param array     $values       The filter values to apply
     * @param array     $facet_config The facet configuration
     * 
     * @return void
     */
    public function apply_to_query( $query, $values, $facet_config );

    /**
     * Get available options for this facet
     *
     * @param array $args Arguments for generating options including:
     *                     - post_type: The post type
     *                     - threshold: Minimum count to include
     *                     - post__in: Limit to these post IDs
     *                     - context: Current filter context
     * 
     * @return array Array of option objects with properties:
     *               - id: Option identifier
     *               - value: Option value for URL
     *               - title: Display title
     *               - count: Count of items with this option
     */
    public function get_options( $args );
    
    /**
     * Render a selected filter item
     *
     * @param mixed  $value       The selected value
     * @param array  $facet_config The facet configuration
     * @param string $uri         The base URI for constructing removal link
     * @param array  $get_params  Current GET parameters
     * @return string HTML for the selected filter item
     */
    public function render_selected_item( $value, $facet_config, $uri, $get_params );
}