<?php
/**
 * Abstract Facet Base Class
 *
 * Base implementation of the FacetType interface with common functionality.
 *
 * @package CP_Library\Filters
 * @since 1.6.0
 */

namespace CP_Library\Filters;

/**
 * AbstractFacet class - Base implementation for facet types.
 *
 * This abstract class provides a foundation for facet implementations with
 * common properties and methods.
 *
 * @since 1.6.0
 */
abstract class AbstractFacet implements FacetType {

    /**
     * Facet type identifier
     *
     * @var string
     */
    protected $type = '';

    /**
     * Facet display label
     *
     * @var string
     */
    protected $label = '';

    /**
     * Compatible post types
     *
     * @var array
     */
    protected $compatible_post_types = [];

    /**
     * Constructor
     *
     * @param array $args Configuration arguments
     */
    public function __construct( $args = [] ) {
        if ( isset( $args['label'] ) ) {
            $this->label = $args['label'];
        }

        if ( isset( $args['post_types'] ) && is_array( $args['post_types'] ) ) {
            $this->compatible_post_types = $args['post_types'];
        }
    }

    /**
     * Get the facet type identifier
     *
     * @return string The facet type ID
     */
    public function get_type() {
        return $this->type;
    }

    /**
     * Get the facet display name
     *
     * @return string The facet type display name
     */
    public function get_label() {
        return $this->label;
    }

    /**
     * Get post types this facet is compatible with
     *
     * @return array Array of post type names
     */
    public function get_compatible_post_types() {
        return $this->compatible_post_types;
    }

    /**
     * Check if this facet is compatible with a post type
     *
     * @param string $post_type The post type to check compatibility for
     * @return bool Whether the facet is compatible
     */
    public function is_compatible_with( $post_type ) {
        // If no specific post types set, assume compatible with all
        if ( empty( $this->compatible_post_types ) ) {
            return true;
        }

        return in_array( $post_type, $this->compatible_post_types );
    }

    /**
     * Format options to ensure consistent structure
     *
     * @param array $options Raw options data
     * @return array Formatted options
     */
    protected function format_options( $options ) {
        $formatted = [];

        foreach ( $options as $option ) {
            // Ensure all required fields exist
            $formatted_option = [
                'id'    => $option->id ?? $option->value ?? '',
                'value' => $option->value ?? '',
                'title' => $option->title ?? $option->name ?? '',
                'count' => $option->count ?? 0,
            ];

            $formatted[] = (object) $formatted_option;
        }

        return $formatted;
    }
    
    /**
     * Render a selected filter item
     *
     * @param mixed  $value       The selected value
     * @param array  $facet_config The facet configuration
     * @param string $uri         The base URI for constructing removal link
     * @param array  $get_params  Current GET parameters
     * @return string HTML for the selected filter item
     */
    public function render_selected_item( $value, $facet_config, $uri, $get_params ) {
        $param = $facet_config['param'];
        $label = $facet_config['label'];
        $display_value = $this->get_display_value($value, $facet_config);
        
        if (empty($display_value)) {
            return '';
        }
        
        // Create a copy of GET params and remove the current value
        $filter_get = $get_params;
        
        // Handle the removal of the filter parameter
        if (isset($filter_get[$param]) && is_array($filter_get[$param])) {
            $index = array_search($value, $filter_get[$param]);
            if ($index !== false) {
                unset($filter_get[$param][$index]);
                // If there are no more values for this param, remove the empty array
                if (empty($filter_get[$param])) {
                    unset($filter_get[$param]);
                }
            }
        } else {
            unset($filter_get[$param]);
        }
        
        // Generate the HTML
        $html = sprintf(
            '<a href="%s" class="cpl-filter--filters--filter" role="button" aria-label="%s">
                <span class="cpl-filter-category" aria-hidden="true">%s:</span>
                %s
                <span class="cpl-filter-remove" aria-hidden="true">×</span>
            </a>',
            esc_url(add_query_arg($filter_get, $uri)),
            esc_attr(sprintf(__('Remove %s filter: %s', 'cp-library'), $label, $display_value)),
            esc_html($label),
            esc_html($display_value)
        );
        
        return $html;
    }
    
    /**
     * Get the display value for a selected filter
     * 
     * @param mixed $value The selected value
     * @param array $facet_config The facet configuration
     * @return string The value to display
     */
    protected function get_display_value($value, $facet_config) {
        // Default implementation just returns the value as is
        // Child classes should override this to provide meaningful display values
        return $value;
    }
}