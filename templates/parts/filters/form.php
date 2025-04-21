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
 * @var string $post_type            Post type for the filter ('cpl_item' or 'cpl_item_type')
 * @var string $template             Template to use for rendering results ('grid', 'list', etc.)
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
    'disabled_filters' => [],
    'post_type' => 'cpl_item', // Default to sermon post type
    'template' => 'grid',
]);

// Extract variables
extract($args);
?>
<div class="cpl-filter <?php echo esc_attr($container_class); ?>"
     data-context="<?php echo esc_attr($context); ?>"
     data-post-type="<?php echo esc_attr($post_type); ?>"
     data-template="<?php echo esc_attr($template); ?>"
     <?php echo $context_args; ?>>
    <form method="get" class="cpl-filter--form" role="search" aria-label="<?php
         echo esc_attr( sprintf( __( 'Filter %s', 'cp-library' ), $post_type === 'cpl_item' ? __( 'sermons', 'cp-library' ) : __( 'series', 'cp-library' ) ) );
    ?>">
        <div class="cpl-filter--toggle">
            <a href="#"
               class="cpl-filter--toggle--button cpl-button"
               role="button"
               aria-expanded="false"
               aria-controls="cpl-filter-dropdowns-container">
                <span><?php esc_html_e( 'Filter', 'cp-library' ); ?></span>
                <?php echo Helpers::get_icon( 'filter' ); ?>
            </a>
        </div>

        <div id="cpl-filter-dropdowns-container" class="cpl-filter--dropdown-container">
            <?php foreach ( $facets as $facet_id => $facet_config ):
                // Generate unique IDs for ARIA attributes
                $dropdown_id = 'cpl-filter-dropdown-' . esc_attr( $facet_id );
            ?>
                <div class="cpl-filter--<?php echo esc_attr( $facet_id ); ?> cpl-filter--has-dropdown" style="display:none;">
                    <a href="#"
                       id="<?php echo esc_attr( $dropdown_id ); ?>-button"
                       class="cpl-filter--dropdown-button cpl-button is-light"
                       role="button"
                       aria-haspopup="true"
                       aria-expanded="false"
                       aria-controls="<?php echo esc_attr( $dropdown_id ); ?>"
                       ><?php echo esc_html( $facet_config['label'] ); ?></a>
                    <div id="<?php echo esc_attr( $dropdown_id ); ?>"
                         class="cpl-filter--dropdown cpl-ajax-facet"
                         data-facet-type="<?php echo esc_attr( $facet_id ); ?>"
                         data-context="<?php echo esc_attr( $args['context'] ); ?>"
                         role="menu"
                         aria-labelledby="<?php echo esc_attr( $dropdown_id ); ?>-button">
                         <!-- Options will be loaded here via AJAX -->
                         <div class="cpl-filter--loading" aria-live="polite">
                             <span class="screen-reader-text"><?php esc_html_e( 'Loading filter options. Please wait.', 'cp-library' ); ?></span>
                         </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ( $show_search ): ?>
            <div class="cpl-filter--search">
                <div class="cpl-filter--search--box">
                    <button type="submit"
                            class="cpl-search-submit"
                            aria-label="<?php esc_attr_e( 'Search', 'cp-library' ); ?>">
                        <span class="material-icons-outlined" aria-hidden="true">search</span>
                    </button>
                    <input type="text"
                           name="cpl_search"
                           value="<?php echo esc_attr( Helpers::get_param( $_GET, 'cpl_search' ) ); ?>"
                           placeholder="<?php esc_html_e( 'Search', 'cp-library' ); ?>"
                           aria-label="<?php esc_attr_e( 'Search content', 'cp-library' ); ?>"
                    />
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

        // Add hidden field for post type
        echo '<input type="hidden" name="post_type" value="' . esc_attr( $post_type ) . '">';
        ?>
    </form>
</div>
