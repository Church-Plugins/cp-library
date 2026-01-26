<?php
/**
 * Filter System Template Helpers
 *
 * Functions that integrate the filter system with templates.
 *
 * @package CP_Library\Filters
 * @since 1.6.0
 */

namespace CP_Library\Filters;

use CP_Library\Admin\Settings;

/**
 * Template helper functions for the filter system.
 *
 * @since 1.6.0
 */
class TemplateHelpers {

    /**
     * Render sermon filters for templates
     *
     * @param array $args Arguments for rendering filters
     * @return string HTML output
     */
    public static function render_sermon_filters( $args = [] ) {
        $filter_manager = FilterManager::get_filter_manager( 'cpl_item' );
        if ( ! $filter_manager ) {
            return '';
        }

        $defaults = [
            'context'         => 'archive',
            'context_args'    => [],
            'show_search'     => true,
            'container_class' => '',
            'template'        => 'grid',
            'taxonomies'      => [ 'cpl_scripture', 'cpl_topic', 'cpl_season' ],
            'speaker'         => true,
            'service_type'    => Settings::get_item( 'enable_service_types', true ),
            'year'            => true,
        ];

        $args = wp_parse_args( $args, $defaults );

        // Determine post type from context
        $post_type = $args['context_args']['post_type'] ?? 'cpl_item';

        if ( $post_type === 'cpl_item' ) {
            $disabled_filters = Settings::get_item_disabled_filters();
        } elseif ( $post_type === 'cpl_item_type' ) {
            $disabled_filters = Settings::get_item_type_disabled_filters();
        } else {
            $disabled_filters = [];
        }

        // Default facets to show
        $enabled_facets = [];

        // Add taxonomy facets
        if ( ! empty( $args['taxonomies'] ) ) {
            foreach ( $args['taxonomies'] as $taxonomy ) {
                $enabled_facets[] = $taxonomy;
            }
        }

        // Add speaker facet
        if ( ! empty( $args['speaker'] ) ) {
            $enabled_facets[] = 'speaker';
        }

        // Add service type facet
        if ( ! empty( $args['service_type'] ) ) {
            $enabled_facets[] = 'service-type';
        }

        // Add year facet
        if ( ! empty( $args['year'] ) ) {
            $enabled_facets[] = 'year';
        }

        // Get disabled filters from settings
        $settings_disabled_filters = $disabled_filters; // From earlier in the method

        // Calculate disabled filters from args (what's NOT enabled)
        $args_disabled_filters = array_diff( array_keys( $filter_manager->get_facets() ), $enabled_facets );

        // Merge both: use disabled from settings + disabled from args
        $disabled_filters = array_unique( array_merge( $settings_disabled_filters, $args_disabled_filters ) );

        // Render filter form
        $output = $filter_manager->render_filter_form([
            'context'          => $args['context'],
            'context_args'     => $args['context_args'],
            'disabled_filters' => $disabled_filters,
            'show_search'      => $args['show_search'],
            'container_class'  => $args['container_class'],
            'post_type'        => 'cpl_item',
            'template'         => $args['template'],
        ]);

        // Render selected filters
        $output .= $filter_manager->render_selected_filters([
            'context'      => $args['context'],
            'context_args' => $args['context_args'],
            'post_type'    => 'cpl_item',
        ]);

        return $output;
    }

    /**
     * Render series filters for templates
     *
     * @param array $args Arguments for rendering filters
     * @return string HTML output
     */
    public static function render_series_filters( $args = [] ) {
        $filter_manager = FilterManager::get_filter_manager( 'cpl_item_type' );
        if ( ! $filter_manager ) {
            return '';
        }

        return $filter_manager->render_series_filters( $args );
    }

    /**
     * Get the filter manager for a specific post type
     *
     * @param string $post_type The post type
     * @return object|null The filter manager or null if not found
     */
    public static function get_filter_manager( $post_type ) {
        return FilterManager::get_filter_manager( $post_type );
    }

    /**
     * Get the current active filter manager based on post type in query
     *
     * @return object|null The current filter manager or null if not found
     */
    public static function get_current_manager() {
        // Get post type from query
        $post_type = '';

        // Check for post type archive
        if (is_post_type_archive()) {
            $post_type = get_query_var('post_type');
        }
        // Check for taxonomy
        elseif (is_tax()) {
            $term = get_queried_object();
            $taxonomy = get_taxonomy($term->taxonomy);
            $post_type = $taxonomy->object_type[0] ?? '';
        }
        // Default to current post type
        else {
            $post_type = get_post_type();
        }

        // Get filter manager for this post type
        return FilterManager::get_filter_manager($post_type);
    }

    /**
     * Check if the current request has active filters
     *
     * @return bool True if filters are active, false otherwise
     */
    public static function has_active_filters() {
        // Get current filter manager
        $current_manager = self::get_current_manager();

        if (!$current_manager) {
            return false;
        }

        // Check if there are active facets
        $active_facets = $current_manager->get_active_facets_from_request();

        return !empty($active_facets);
    }

    /**
     * Add structured data to an item in filtered results
     *
     * @param \WP_Post $item The post object
     * @param int $position The position in the result set
     * @return string HTML with structured data script tag
     */
    public static function item_structured_data($item, $position = 1) {
        if (!self::has_active_filters() || !$item) {
            return '';
        }

        ob_start();
        cp_library()->templates->get_template_part("parts/filter-structured-data", [
            'item' => $item,
            'post_type' => get_post_type($item),
            'position' => $position
        ]);
        return ob_get_clean();
    }

    /**
     * Render filters based on current post type
     *
     * @param array $args Arguments for rendering filters
     * @return string HTML output
     */
    public static function render_current_filters( $args = [] ) {
        $current_manager = self::get_current_manager();

        if ( ! $current_manager ) {
            return '';
        }

        $post_type = $current_manager->get_post_type();

        if ( 'cpl_item' === $post_type ) {
            return self::render_sermon_filters( $args );
        } elseif ( 'cpl_item_type' === $post_type ) {
            return self::render_series_filters( $args );
        }

        return '';
    }
}
