<?php
/**
 * Series Archive Filters Template
 *
 * This template demonstrates how to integrate the new filter system with series archives.
 *
 * @package CP_Library
 * @since 1.6.0
 */

// Use our template helpers to render series filters
use CP_Library\Filters\TemplateHelpers;

// Default args that can be overridden via template attributes
$args = apply_filters( 'cpl_series_filter_args', [
    'context'         => 'archive',
    'context_args'    => [],
    'show_search'     => true,
    'container_class' => 'cpl-series-filters',
    'template'        => 'grid',
    'taxonomies'      => [ 'cpl_season', 'cpl_topics' ],
    'show_count'      => true,
    'sermon_count'    => true,
    'year'            => true,
] );

// Render series filters
echo TemplateHelpers::render_series_filters( $args );

// Render results container
echo '<div class="cpl-filter-results">';
echo '<div class="cpl-filter-content">';

// Check if we have series
if ( have_posts() ) {
    while ( have_posts() ) {
        the_post();
        cp_library()->templates->get_template_part( 'parts/item-type-' . $args['template'] );
    }
} else {
    echo '<div class="cpl-no-results">' . __( 'No series found matching your criteria.', 'cp-library' ) . '</div>';
}

echo '</div>'; // Close .cpl-filter-content

// Render pagination
if ( $wp_query->max_num_pages > 1 ) {
    echo '<div class="cpl-pagination">';
    $big = 999999999;
    echo paginate_links( [
        'base'    => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
        'format'  => '?paged=%#%',
        'current' => max( 1, get_query_var( 'paged' ) ),
        'total'   => $wp_query->max_num_pages,
    ] );
    echo '</div>';
}

echo '</div>'; // Close .cpl-filter-results