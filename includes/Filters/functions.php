<?php
/**
 * Filter System Template Functions
 *
 * Global functions for accessing filter system features in templates.
 *
 * @package CP_Library\Filters
 * @since 1.6.0
 */

use CP_Library\Filters\TemplateHelpers;
use CP_Library\Filters\SEO;

/**
 * Check if the current page has active filters
 *
 * @return bool True if filters are active
 */
function cpl_has_active_filters() {
    return TemplateHelpers::has_active_filters();
}

/**
 * Render structured data for an item in filtered results
 *
 * @param \WP_Post|int $post The post object or ID
 * @param int $position The position in the result set
 * @return string HTML with structured data
 */
function cpl_item_structured_data($post = null, $position = 1) {
    if (!$post) {
        $post = get_post();
    } elseif (is_numeric($post)) {
        $post = get_post($post);
    }
    
    return TemplateHelpers::item_structured_data($post, $position);
}

/**
 * Get canonical URL for the current filtered page
 *
 * @return string Canonical URL
 */
function cpl_get_canonical_url() {
    $seo = SEO::get_instance();
    return $seo->get_unfiltered_url();
}

/**
 * Output rel=canonical tag for filtered pages
 */
function cpl_output_canonical() {
    $seo = SEO::get_instance();
    
    if (cpl_has_active_filters()) {
        $canonical_url = $seo->get_unfiltered_url();
        echo '<link rel="canonical" href="' . esc_url($canonical_url) . '" />' . "\n";
    }
}

/**
 * Output robots meta tag for filtered pages
 */
function cpl_output_robots_meta() {
    if (cpl_has_active_filters()) {
        echo '<meta name="robots" content="noindex, follow" />' . "\n";
    }
}

/**
 * Add filters to the given query
 *
 * @param \WP_Query $query The query object
 * @param array $filters Array of filters to apply (facet_id => values)
 * @param string $post_type The post type to filter
 * @return \WP_Query Modified query
 */
function cpl_add_filters_to_query($query, $filters, $post_type = 'cpl_item') {
    if (empty($filters)) {
        return $query;
    }
    
    $filter_manager = CP_Library\Filters\FilterManager::get_filter_manager($post_type);
    
    if (!$filter_manager) {
        return $query;
    }
    
    // Loop through the filters and apply each one
    foreach ($filters as $facet_id => $values) {
        $facet_config = $filter_manager->get_facet($facet_id);
        
        if (!$facet_config) {
            continue;
        }
        
        // Skip facets that don't apply to this post type
        if (!empty($facet_config['post_types']) && !in_array($post_type, $facet_config['post_types'])) {
            continue;
        }
        
        // Use the facet's query_callback if it has one
        if (!empty($facet_config['query_callback']) && is_callable($facet_config['query_callback'])) {
            call_user_func($facet_config['query_callback'], $query, $values, $facet_config);
        }
    }
    
    return $query;
}

/**
 * Get active filters for the current request
 *
 * @param string $post_type The post type to get filters for
 * @return array Active filters (facet_id => values)
 */
function cpl_get_active_filters($post_type = '') {
    if (empty($post_type)) {
        // Try to get from current query
        if (is_post_type_archive()) {
            $post_type = get_query_var('post_type');
        } elseif (is_tax()) {
            $term = get_queried_object();
            $taxonomy = get_taxonomy($term->taxonomy);
            $post_type = $taxonomy->object_type[0] ?? 'cpl_item';
        } else {
            $post_type = get_post_type() ?: 'cpl_item';
        }
    }
    
    $filter_manager = CP_Library\Filters\FilterManager::get_filter_manager($post_type);
    
    if (!$filter_manager) {
        return [];
    }
    
    return $filter_manager->get_active_facets_from_request();
}