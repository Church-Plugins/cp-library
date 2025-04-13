<?php
/**
 * Template for rendering the filter form
 * 
 * Template parameters:
 * 
 * @var array $facets                Array of facets to display
 * @var string $context_args         HTML attributes string for data attributes
 * @var array $context_args_data     Array of context arguments for hidden fields
 * @var string $context              Context identifier (e.g. 'archive', 'service-type')
 * @var string $container_class      Additional CSS classes for the container
 * @var bool $show_search            Whether to show the search input
 * @var array $disabled_filters      Array of facet IDs that are disabled
 */

use ChurchPlugins\Helpers;

// Parse arguments with defaults
$args = wp_parse_args($args, [
    'facets' => [],
    'context_args' => '',
    'context_args_data' => [],
    'context' => 'archive',
    'container_class' => '',
    'show_search' => true,
    'disabled_filters' => []
]);

// Extract variables
extract($args);
?>
<div class="cpl-filter <?php echo esc_attr($container_class); ?>"
     data-context="<?php echo esc_attr($context); ?>" <?php echo $context_args; ?>>
    <form method="get" class="cpl-filter--form">
        <div class="cpl-filter--toggle">
            <a href="#" class="cpl-filter--toggle--button cpl-button">
                <span><?php esc_html_e( 'Filter', 'cp-library' ); ?></span>
                <?php echo Helpers::get_icon( 'filter' ); ?>
            </a>
        </div>

        <?php foreach ( $facets as $facet_id => $facet_config ): ?>
            <div class="cpl-filter--<?php echo esc_attr( $facet_id ); ?> cpl-filter--has-dropdown">
                <a href="#"
                   class="cpl-filter--dropdown-button cpl-button is-light"><?php echo esc_html( $facet_config['label'] ); ?></a>
                <div class="cpl-filter--dropdown cpl-ajax-facet"
                     data-facet-type="<?php echo esc_attr( $facet_id ); ?>"
                     data-context="<?php echo esc_attr( $args['context'] ); ?>">
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ( $show_search ): ?>
            <div class="cpl-filter--search">
                <div class="cpl-filter--search--box">
                    <button type="submit" class="cpl-search-submit"><span class="material-icons-outlined">search</span></button>
                    <input type="text" name="cpl_search" 
                           value="<?php echo esc_attr( Helpers::get_param( $_GET, 'cpl_search' ) ); ?>"
                           placeholder="<?php esc_html_e( 'Search', 'cp-library' ); ?>"/>
                </div>
            </div>
        <?php endif; ?>

        <?php
        // Add hidden fields for context-specific parameters
        if ( is_array($context_args_data) ) {
        foreach ( $context_args_data as $key => $value ):
            if ( is_array( $value ) ) {
                foreach ( $value as $val ) {
                    echo '<input type="hidden" name="' . esc_attr( $key ) . '[]" value="' . esc_attr( $val ) . '">';
                }
            } else {
                echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
            }
        endforeach;
        }
        ?>
    </form>
</div>