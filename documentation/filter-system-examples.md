# CP Library Filter System: Code Examples

This document provides practical code examples for common tasks with the CP Library filter system.

## Basic Usage Examples

### Rendering Filters in a Template

```php
<?php
// Basic filter form for sermons (auto-detects current post type)
echo CP_Library\Filters\TemplateHelpers::render_current_filters([
    'context' => 'archive',
    'show_search' => true,
    'container_class' => 'my-custom-filter-container'
]);
?>
```

### Customizing Which Filters Display

```php
<?php
// Get the sermon filter manager
$manager = CP_Library\Filters\FilterManager::get_filter_manager('cpl_item');

// Show only specific filters
$enabled_facets = ['cpl_topics', 'cpl_scripture', 'speaker'];
$all_facets = array_keys($manager->get_facets());
$disabled_filters = array_diff($all_facets, $enabled_facets);

// Render with only enabled filters
echo $manager->render_filter_form([
    'context' => 'archive',
    'disabled_filters' => $disabled_filters,
    'show_search' => true
]);

// Show selected filters
echo $manager->render_selected_filters([
    'context' => 'archive'
]);
?>
```

### Using Filters in a Custom Loop

```php
<?php
// Get active filters
$active_filters = cpl_get_active_filters('cpl_item');

// Set up query
$args = [
    'post_type' => 'cpl_item',
    'posts_per_page' => 10
];

// Create query
$query = new WP_Query($args);

// Apply filters to query
cpl_add_filters_to_query($query, $active_filters, 'cpl_item');

// The Loop
if ($query->have_posts()) :
    while ($query->have_posts()) : $query->the_post();
        // Display each post
        the_title('<h2>', '</h2>');
        the_excerpt();
    endwhile;
    wp_reset_postdata();
else :
    echo 'No sermons found.';
endif;
?>
```

### Custom Filter Conditions

```php
<?php
// Check if a specific filter is active
$active_filters = cpl_get_active_filters();

// Check for a specific topic
$has_faith_filter = false;
if (!empty($active_filters['cpl_topics'])) {
    $has_faith_filter = in_array('faith', $active_filters['cpl_topics']);
}

// Show conditional content based on filter
if ($has_faith_filter) :
?>
    <div class="special-faith-content">
        <h3>Faith Resources</h3>
        <p>Check out these additional resources on faith:</p>
        <!-- Additional content -->
    </div>
<?php endif; ?>
```

### Show Filter Notice on Filtered Pages

```php
<?php
// Display filter notice if filters are active
if (function_exists('cpl_has_active_filters') && cpl_has_active_filters()) :
?>
    <div class="my-filter-notice-wrapper">
        <?php cp_library()->templates->get_template_part('parts/filter-notice'); ?>
    </div>
<?php endif; ?>
```

## Advanced Usage Examples

### Custom Filter Registration

```php
<?php
// Add a custom filter to sermon filter manager
add_action('cpl_register_facets_cpl_item', function($filter_manager) {
    // Add a date range filter
    $filter_manager->register_facet('date_range', [
        'label' => __('Date Range', 'my-theme'),
        'param' => 'facet-date-range',
        'type' => 'custom',
        'query_callback' => 'my_date_range_query_callback',
        'options_callback' => 'my_date_range_options_callback'
    ]);
});

// Query callback for date range
function my_date_range_query_callback($query, $values, $facet_config) {
    if (!empty($values)) {
        $date_range = $values[0]; // e.g. "2020-2022"
        list($start, $end) = explode('-', $date_range);
        
        // Convert to full dates
        $start_date = $start . '-01-01';
        $end_date = $end . '-12-31';
        
        $query->set('date_query', [
            [
                'after'     => $start_date,
                'before'    => $end_date,
                'inclusive' => true,
            ],
        ]);
    }
}

// Options callback for date range
function my_date_range_options_callback($args) {
    global $wpdb;
    
    // Get years from posts (assumes we store year in post_date)
    $query = $wpdb->prepare(
        "SELECT DISTINCT YEAR(post_date) as year
         FROM {$wpdb->posts}
         WHERE post_type = %s
         AND post_status = 'publish'
         ORDER BY year DESC",
        $args['post_type']
    );
    
    $years = $wpdb->get_col($query);
    
    // Generate date ranges (last 5 years, 5 year chunks before that)
    $options = [];
    $current_year = current_time('Y');
    
    // Last 5 years
    $start = max($years);
    $end = $current_year;
    $options[] = [
        'title' => sprintf('%d-%d', $start, $end),
        'value' => sprintf('%d-%d', $start, $end),
        'count' => 0 // Calculate this if needed
    ];
    
    // Earlier ranges in 5 year chunks
    if (count($years) > 5) {
        $oldest = min($years);
        for ($i = $start - 5; $i >= $oldest; $i -= 5) {
            $range_start = max($i, $oldest);
            $range_end = $i + 4;
            $options[] = [
                'title' => sprintf('%d-%d', $range_start, $range_end),
                'value' => sprintf('%d-%d', $range_start, $range_end),
                'count' => 0 // Calculate this if needed
            ];
        }
    }
    
    return $options;
}
?>
```

### Creating a Custom Filter Manager for a Custom Post Type

```php
<?php
// Register a custom filter manager for a custom post type
add_action('cpl_register_filter_managers', function() {
    CP_Library\Filters\FilterManager::register_filter_manager(
        'my_custom_post_type',
        MyTheme\Filters\CustomPostTypeFilterManager::class
    );
});

// Custom Filter Manager
namespace MyTheme\Filters;

class CustomPostTypeFilterManager extends \CP_Library\Filters\AbstractFilterManager {
    
    protected function register_default_contexts() {
        // Register contexts
        $this->register_context('archive', [
            'label' => __('Archive', 'my-theme')
        ]);
        
        $this->register_context('shortcode', [
            'label' => __('Shortcode', 'my-theme')
        ]);
    }
    
    protected function register_default_facets() {
        // Register taxonomies
        $taxonomies = get_object_taxonomies($this->post_type, 'objects');
        foreach ($taxonomies as $taxonomy) {
            $this->register_taxonomy_facet($taxonomy->name);
        }
        
        // Register a meta facet
        $this->register_meta_facet('difficulty', 'resource_difficulty', [
            'label' => __('Difficulty Level', 'my-theme')
        ]);
        
        // Register a custom facet
        $this->register_facet('format', [
            'label' => __('Format', 'my-theme'),
            'param' => 'facet-format',
            'type' => 'custom',
            'query_callback' => [$this, 'query_format_facet'],
            'options_callback' => [$this, 'get_format_options']
        ]);
    }
    
    public function query_format_facet($query, $values, $facet_config) {
        if (!empty($values)) {
            $meta_query = $query->get('meta_query') ?: [];
            
            $meta_query[] = [
                'key' => 'resource_format',
                'value' => $values,
                'compare' => 'IN'
            ];
            
            $query->set('meta_query', $meta_query);
        }
    }
    
    public function get_format_options($args) {
        $formats = [
            'pdf' => __('PDF', 'my-theme'),
            'video' => __('Video', 'my-theme'),
            'audio' => __('Audio', 'my-theme'),
            'text' => __('Text', 'my-theme'),
            'worksheet' => __('Worksheet', 'my-theme')
        ];
        
        $options = [];
        foreach ($formats as $value => $title) {
            $options[] = [
                'value' => $value,
                'title' => $title,
                'count' => $this->count_format_posts($value, $args)
            ];
        }
        
        return $options;
    }
    
    private function count_format_posts($format, $args) {
        // Create a count query
        $count_args = [
            'post_type' => $this->post_type,
            'post_status' => 'publish',
            'meta_key' => 'resource_format',
            'meta_value' => $format,
            'posts_per_page' => -1,
            'fields' => 'ids'
        ];
        
        // Apply post__in from args if available
        if (!empty($args['post__in'])) {
            $count_args['post__in'] = $args['post__in'];
        }
        
        $count_query = new \WP_Query($count_args);
        return $count_query->found_posts;
    }
}
?>
```

### JavaScript Filter Initialization with Event Handling

```javascript
// Initialize filters with custom event handling
document.addEventListener('DOMContentLoaded', function() {
    // Initialize sermon filters
    const sermonFilter = new CPLibraryFilter({
        context: 'archive',
        container: document.querySelector('.cpl-sermon-filters'),
        postType: 'cpl_item',
        autoSubmit: true,
        debug: true
    });
    
    // Initialize series filters
    const seriesFilter = new CPLibraryFilter({
        context: 'archive',
        container: document.querySelector('.cpl-series-filters'),
        postType: 'cpl_item_type',
        autoSubmit: true,
        debug: true
    });
    
    // Add custom error handling
    window.addEventListener('error', function(event) {
        // Check if error is related to filters
        if (event.filename && event.filename.includes('filters.js')) {
            console.error('Filter error:', event);
            
            // Show user-friendly error message
            const errorContainer = document.querySelector('.filter-error-container');
            if (errorContainer) {
                errorContainer.textContent = 'An error occurred with filters. Please try again.';
                errorContainer.style.display = 'block';
            }
        }
    });
});
```

### AJAX Filter Loading with Custom Handling

```javascript
// Custom AJAX filter handling
function loadCustomFilterOptions(filterType, selected) {
    const container = document.querySelector('.custom-filter-' + filterType);
    
    // Show loading state
    container.classList.add('loading');
    
    // Create request data
    const data = {
        action: 'cpl_filter_options',
        filter_type: filterType,
        selected: selected || [],
        post_type: 'cpl_item',
        context: 'custom',
        nonce: cplVars.nonce
    };
    
    // Make AJAX request
    jQuery.ajax({
        url: cplVars.ajax_url,
        type: 'POST',
        data: data,
        success: function(response) {
            container.classList.remove('loading');
            
            if (response.success && response.data.options) {
                // Build options HTML
                let html = '';
                response.data.options.forEach(function(option) {
                    html += `
                        <label>
                            <input type="checkbox" 
                                   name="${response.data.param_name}[]" 
                                   value="${option.value}"
                                   ${selected.includes(option.value) ? 'checked' : ''}>
                            ${option.title} (${option.count})
                        </label>
                    `;
                });
                
                container.innerHTML = html;
            } else {
                container.innerHTML = '<p>No filter options available</p>';
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            container.classList.remove('loading');
            container.innerHTML = '<p>Error loading filter options</p>';
            
            console.error('Filter AJAX error:', textStatus, errorThrown);
        }
    });
}
```

### SEO Implementation in a Custom Theme

```php
<?php
// Add to header.php or functions.php

// Add canonical URL
function mytheme_add_canonical_url() {
    if (function_exists('cpl_has_active_filters') && cpl_has_active_filters()) {
        if (function_exists('cpl_output_canonical')) {
            cpl_output_canonical();
        } else {
            // Fallback if function not available
            $url = get_post_type_archive_link(get_post_type());
            if ($url) {
                echo '<link rel="canonical" href="' . esc_url($url) . '" />' . "\n";
            }
        }
    }
}
add_action('wp_head', 'mytheme_add_canonical_url', 10);

// Add robots meta for filtered pages
function mytheme_add_robots_meta() {
    if (function_exists('cpl_has_active_filters') && cpl_has_active_filters()) {
        if (function_exists('cpl_output_robots_meta')) {
            cpl_output_robots_meta();
        } else {
            // Fallback if function not available
            echo '<meta name="robots" content="noindex, follow" />' . "\n";
        }
    }
}
add_action('wp_head', 'mytheme_add_robots_meta', 10);

// Add structured data to posts
function mytheme_add_structured_data() {
    if (function_exists('cpl_has_active_filters') && cpl_has_active_filters() && is_main_query() && in_the_loop()) {
        global $wp_query;
        if (function_exists('cpl_item_structured_data')) {
            echo cpl_item_structured_data(get_post(), $wp_query->current_post + 1);
        }
    }
}
add_action('cpl_before_item_content', 'mytheme_add_structured_data', 10);
?>
```

### Creating a Filtered Related Posts Section

```php
<?php
/**
 * Display related posts with filter capabilities
 *
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function my_related_posts_shortcode($atts) {
    $atts = shortcode_atts([
        'count' => 3,
        'taxonomy' => 'cpl_topics',
        'title' => 'Related Content'
    ], $atts);
    
    // Get current post terms
    $terms = get_the_terms(get_the_ID(), $atts['taxonomy']);
    if (!$terms || is_wp_error($terms)) {
        return '';
    }
    
    // Get term slugs
    $term_slugs = wp_list_pluck($terms, 'slug');
    
    // Create filters array
    $filters = [$atts['taxonomy'] => $term_slugs];
    
    // Set up query
    $args = [
        'post_type' => 'cpl_item',
        'posts_per_page' => $atts['count'],
        'post__not_in' => [get_the_ID()], // Exclude current post
    ];
    
    // Create query
    $query = new WP_Query($args);
    
    // Apply filters to query
    if (function_exists('cpl_add_filters_to_query')) {
        $query = cpl_add_filters_to_query($query, $filters, 'cpl_item');
    } else {
        // Fallback if function not available
        $tax_query = [];
        $tax_query[] = [
            'taxonomy' => $atts['taxonomy'],
            'field' => 'slug',
            'terms' => $term_slugs
        ];
        $query->set('tax_query', $tax_query);
    }
    
    // Start output buffer
    ob_start();
    
    if ($query->have_posts()) :
    ?>
        <div class="related-posts">
            <h3><?php echo esc_html($atts['title']); ?></h3>
            
            <div class="related-posts-grid">
                <?php 
                while ($query->have_posts()) : $query->the_post();
                    // Display each related post
                    ?>
                    <div class="related-post">
                        <?php if (has_post_thumbnail()) : ?>
                            <a href="<?php the_permalink(); ?>">
                                <?php the_post_thumbnail('thumbnail'); ?>
                            </a>
                        <?php endif; ?>
                        
                        <h4><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h4>
                        
                        <div class="related-post-meta">
                            <?php echo get_the_date(); ?>
                        </div>
                    </div>
                    <?php
                endwhile;
                wp_reset_postdata();
                ?>
            </div>
            
            <p class="related-posts-more">
                <?php
                // Create a link to filtered archive
                $archive_url = get_post_type_archive_link('cpl_item');
                $filter_params = [];
                
                foreach ($term_slugs as $slug) {
                    $filter_params[] = "facet-{$atts['taxonomy']}[]={$slug}";
                }
                
                $filtered_url = add_query_arg(
                    implode('&', $filter_params),
                    $archive_url
                );
                ?>
                <a href="<?php echo esc_url($filtered_url); ?>">
                    <?php _e('View all related content', 'my-theme'); ?>
                </a>
            </p>
        </div>
    <?php
    endif;
    
    return ob_get_clean();
}
add_shortcode('related_posts', 'my_related_posts_shortcode');
?>
```

## Troubleshooting Examples

### Debug Filter State

```php
<?php
/**
 * Debug filter state - useful for troubleshooting
 */
function my_debug_filter_state() {
    // Only show to admins
    if (!current_user_can('manage_options')) {
        return;
    }
    
    echo '<div class="filter-debug" style="background: #f5f5f5; padding: 1rem; margin: 1rem 0; border: 1px solid #ddd;">';
    echo '<h3>Filter Debug Info</h3>';
    
    // Check functions exist
    echo '<p>Functions available: ';
    echo function_exists('cpl_has_active_filters') ? 'cpl_has_active_filters ✓ ' : 'cpl_has_active_filters ✗ ';
    echo function_exists('cpl_get_active_filters') ? 'cpl_get_active_filters ✓ ' : 'cpl_get_active_filters ✗ ';
    echo '</p>';
    
    // Check active filters
    echo '<p>Has active filters: ' . (function_exists('cpl_has_active_filters') && cpl_has_active_filters() ? 'Yes' : 'No') . '</p>';
    
    // Show active filters
    if (function_exists('cpl_get_active_filters')) {
        $filters = cpl_get_active_filters();
        echo '<p>Active filters: ' . (empty($filters) ? 'None' : '') . '</p>';
        
        if (!empty($filters)) {
            echo '<ul>';
            foreach ($filters as $facet => $values) {
                echo '<li><strong>' . esc_html($facet) . '</strong>: ' . esc_html(implode(', ', $values)) . '</li>';
            }
            echo '</ul>';
        }
    }
    
    // Get available filter managers
    $managers = CP_Library\Filters\FilterManager::get_registered_managers();
    echo '<p>Registered filter managers: ' . (empty($managers) ? 'None' : '') . '</p>';
    
    if (!empty($managers)) {
        echo '<ul>';
        foreach ($managers as $post_type => $manager) {
            echo '<li><strong>' . esc_html($post_type) . '</strong>: ' . get_class($manager) . '</li>';
        }
        echo '</ul>';
    }
    
    // Current post type
    echo '<p>Current post type: ' . get_post_type() . '</p>';
    
    // Current filter manager
    $current_manager = CP_Library\Filters\TemplateHelpers::get_current_manager();
    echo '<p>Current filter manager: ' . ($current_manager ? get_class($current_manager) : 'None') . '</p>';
    
    // GET parameters
    echo '<p>GET parameters:</p>';
    echo '<ul>';
    foreach ($_GET as $key => $value) {
        if (is_array($value)) {
            echo '<li><strong>' . esc_html($key) . '</strong>: ' . esc_html(implode(', ', $value)) . '</li>';
        } else {
            echo '<li><strong>' . esc_html($key) . '</strong>: ' . esc_html($value) . '</li>';
        }
    }
    echo '</ul>';
    
    echo '</div>';
}

// Add to footer for debugging
add_action('wp_footer', 'my_debug_filter_state');
?>
```

### Filter System Test Shortcode

```php
<?php
/**
 * Test shortcode for the filter system
 */
function my_filter_test_shortcode($atts) {
    $atts = shortcode_atts([
        'post_type' => 'cpl_item',
        'context' => 'archive',
        'test_filters' => 'topics,scripture,speaker'
    ], $atts);
    
    // Start output buffer
    ob_start();
    
    // Available filters to test
    $test_filters = explode(',', $atts['test_filters']);
    
    echo '<div class="filter-test">';
    echo '<h3>Filter System Test</h3>';
    
    // Test filter manager
    $manager = CP_Library\Filters\FilterManager::get_filter_manager($atts['post_type']);
    if (!$manager) {
        echo '<p class="error">Error: No filter manager found for post type: ' . esc_html($atts['post_type']) . '</p>';
        echo '</div>';
        return ob_get_clean();
    }
    
    echo '<p>Filter manager found for ' . esc_html($atts['post_type']) . ': ' . get_class($manager) . '</p>';
    
    // Test filters
    echo '<p>Testing filters: ' . esc_html(implode(', ', $test_filters)) . '</p>';
    
    // Get all facets
    $facets = $manager->get_facets();
    echo '<p>Available facets: ' . count($facets) . '</p>';
    
    $disabled_filters = array_keys($facets);
    
    // Enable only test filters
    foreach ($test_filters as $filter) {
        $filter = trim($filter);
        $key = array_search($filter, $disabled_filters);
        
        if ($key !== false) {
            unset($disabled_filters[$key]);
        }
    }
    
    // Render filter form with only test filters enabled
    echo $manager->render_filter_form([
        'context' => $atts['context'],
        'disabled_filters' => $disabled_filters,
        'show_search' => true,
        'container_class' => 'filter-test-container'
    ]);
    
    // Render selected filters
    echo $manager->render_selected_filters([
        'context' => $atts['context']
    ]);
    
    // Test active filters
    if (function_exists('cpl_has_active_filters') && cpl_has_active_filters()) {
        echo '<div class="filter-test-active">';
        echo '<h4>Active Filters</h4>';
        
        $active_filters = cpl_get_active_filters();
        echo '<ul>';
        foreach ($active_filters as $facet => $values) {
            echo '<li><strong>' . esc_html($facet) . '</strong>: ' . esc_html(implode(', ', $values)) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
    
    echo '</div>';
    
    return ob_get_clean();
}
add_shortcode('filter_test', 'my_filter_test_shortcode');
?>
```

These examples should provide practical guidance for implementing and extending the filter system in various scenarios.