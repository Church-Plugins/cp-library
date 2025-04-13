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
<div class="cpl-filter--filters">
    <?php if ( ! empty( $_GET['cpl_search'] ) ):
        $filter_get = $get;
        unset( $filter_get['cpl_search'] );
        ?>
        <a href="<?php echo esc_url( add_query_arg( $filter_get, $uri ) ); ?>"
           class="cpl-filter--filters--filter">
            <?php echo __( 'Search:' ) . ' ' . esc_html( Helpers::get_request( 'cpl_search' ) ); ?>
        </a>
    <?php endif; ?>

    <?php
    // Get registered facets
    $facets = cp_library()->filters->get_facets(['public' => true]);
    
    // Loop through each facet
    foreach ( $facets as $facet_id => $facet ):
        $param = $facet['param'];
        
        // Skip if no values are set for this facet
        if ( empty( $_GET[$param] ) ) {
            continue;
        }
        
        // Handle taxonomy facets
        if ( $facet['type'] === 'taxonomy' ):
            foreach ( $_GET[$param] as $slug ):
                if ( ! $term = get_term_by( 'slug', $slug, $facet['taxonomy'] ) ) {
                    continue;
                }

                $filter_get = $get;
                unset( $filter_get[$param][array_search( $slug, $filter_get[$param] )] );
                ?>
                <a href="<?php echo esc_url( add_query_arg( $filter_get, $uri ) ); ?>"
                   class="cpl-filter--filters--filter">
                    <?php echo esc_html( $term->name ); ?>
                </a>
            <?php endforeach;
        
        // Handle source facets (speaker, service-type)
        elseif ( $facet['type'] === 'source' && in_array($facet_id, ['speaker', 'service-type']) ):
            foreach ( $_GET[$param] as $id ):
                try {
                    if ( $facet_id === 'speaker' ) {
                        $value = \CP_Library\Models\Speaker::get_instance( $id );
                    } elseif ( $facet_id === 'service-type' ) {
                        $value = \CP_Library\Models\ServiceType::get_instance( $id );
                    } else {
                        continue;
                    }
                } catch ( \Exception $e ) {
                    continue;
                }
                
                $filter_get = $get;
                unset( $filter_get[$param][array_search( $id, $filter_get[$param] )] );
                ?>
                <a href="<?php echo esc_url( add_query_arg( $filter_get, $uri ) ); ?>"
                   class="cpl-filter--filters--filter">
                    <?php echo esc_html( $value->title ); ?>
                </a>
            <?php endforeach;
        
        // Handle series facet
        elseif ( $facet['type'] === 'series' ):
            foreach ( $_GET[$param] as $id ):
                try {
                    $value = \CP_Library\Models\ItemType::get_instance_from_origin( $id );
                } catch ( \Exception $e ) {
                    continue;
                }
                
                $filter_get = $get;
                unset( $filter_get[$param][array_search( $id, $filter_get[$param] )] );
                ?>
                <a href="<?php echo esc_url( add_query_arg( $filter_get, $uri ) ); ?>"
                   class="cpl-filter--filters--filter">
                    <?php echo esc_html( $value->title ); ?>
                </a>
            <?php endforeach;
        endif;
    endforeach;
    ?>
</div>