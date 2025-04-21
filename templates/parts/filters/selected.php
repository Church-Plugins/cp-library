<?php
/**
 * Template for rendering the selected filters display
 * 
 * Template parameters:
 * 
 * @var array $args            Filter arguments
 * @var array $get             GET parameters to use for constructing filter links
 * @var string $uri            Current URI base for constructing filter links
 * @var string $context        The context identifier (e.g. 'archive', 'service-type')
 * @var array $context_args    Context-specific arguments
 */

use ChurchPlugins\Helpers;

// Parse arguments with defaults
$args = wp_parse_args($args, [
    'get' => $_GET,
    'uri' => explode('?', $_SERVER['REQUEST_URI'] ?? '?')[0],
    'context' => 'archive',
    'context_args' => []
]);

// Extract variables
extract($args);

$taxonomies = cp_library()->setup->taxonomies->get_objects();
?>
<div class="cpl-filter--filters" role="region" aria-label="<?php esc_attr_e( 'Selected filters', 'cp-library' ); ?>">
    <?php 
    // Count active filters
    $active_filter_count = 0;
    if (!empty($_GET['cpl_search'])) {
        $active_filter_count++;
    }
    
    // Get registered facets
    $facets = cp_library()->filters->get_facets(['public' => true]);
    
    // Count active facets
    foreach ($facets as $facet_id => $facet) {
        $param = $facet['param'];
        if (!empty($_GET[$param])) {
            if (is_array($_GET[$param])) {
                $active_filter_count += count($_GET[$param]);
            } else {
                // Single value (not an array)
                $active_filter_count += 1;
            }
        }
    }
    
    // If active filters exist, display a descriptive header for screen readers
    if ($active_filter_count > 0): ?>
        <div class="screen-reader-text">
            <?php 
                printf(
                    _n(
                        'You have %d active filter. You can remove it by clicking its button below.',
                        'You have %d active filters. You can remove them by clicking their buttons below.',
                        $active_filter_count,
                        'cp-library'
                    ),
                    $active_filter_count
                ); 
            ?>
        </div>
    <?php endif; ?>

    <?php if ( ! empty( $_GET['cpl_search'] ) ):
        $filter_get = $get;
        unset( $filter_get['cpl_search'] );
        $search_term = Helpers::get_request( 'cpl_search' );
        ?>
        <a href="<?php echo esc_url( add_query_arg( $filter_get, $uri ) ); ?>"
           class="cpl-filter--filters--filter"
           role="button"
           aria-label="<?php esc_attr_e( sprintf( 'Remove search filter: %s', $search_term ) ); ?>">
            <?php echo __( 'Search:' ) . ' ' . esc_html( $search_term ); ?>
            <span class="cpl-filter-remove" aria-hidden="true">×</span>
        </a>
    <?php endif; ?>

    <?php
    // Loop through each facet
    foreach ( $facets as $facet_id => $facet ):
        $param = $facet['param'];
        
        // Skip if no values are set for this facet
        if ( empty( $_GET[$param] ) ) {
            continue;
        }
        
        // Render each selected value using the filter manager
        if (is_array($_GET[$param])) {
            foreach ( $_GET[$param] as $value ):
                echo $manager->render_selected_filter_item($facet_id, $value, $uri, $get);
            endforeach;
        } else {
            // Single value (not an array)
            echo $manager->render_selected_filter_item($facet_id, $_GET[$param], $uri, $get);
        }
    endforeach;
    
    // If we have multiple filters, add a "Clear All" button
    if ($active_filter_count > 1):
        // Create a link to the current URI without any filter parameters
        $clear_uri = $uri;
    ?>
        <a href="<?php echo esc_url($clear_uri); ?>"
           class="cpl-filter--filters--filter cpl-filter--clear-all"
           role="button"
           aria-label="<?php esc_attr_e('Clear all filters', 'cp-library'); ?>">
            <?php esc_html_e('Clear All', 'cp-library'); ?>
            <span class="cpl-filter-remove" aria-hidden="true">×</span>
        </a>
    <?php endif; ?>
</div>