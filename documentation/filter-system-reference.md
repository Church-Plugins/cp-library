# CP Library Filter System Reference

This is a quick reference guide for the CP Library Filter System (v1.6.0+)

## Global Functions

```php
// Check if filters are active
cpl_has_active_filters();

// Get active filters
cpl_get_active_filters($post_type = '');

// Add filters to a query
cpl_add_filters_to_query($query, $filters, $post_type = 'cpl_item');

// SEO Functions
cpl_get_canonical_url();
cpl_output_canonical();
cpl_output_robots_meta();
cpl_item_structured_data($post, $position);
```

## Filter Managers

```php
// Get filter manager by post type
$manager = CP_Library\Filters\FilterManager::get_filter_manager('cpl_item');

// Get current filter manager (auto-detects post type)
$manager = CP_Library\Filters\TemplateHelpers::get_current_manager();

// Register a filter manager
CP_Library\Filters\FilterManager::register_filter_manager(
    'custom_post_type',
    YourNamespace\CustomFilterManager::class
);
```

## Template Helpers

```php
// Render sermon filters
echo CP_Library\Filters\TemplateHelpers::render_sermon_filters([
    'context' => 'archive',
    'show_search' => true
]);

// Render series filters
echo CP_Library\Filters\TemplateHelpers::render_series_filters([
    'context' => 'archive',
    'show_search' => true
]);

// Auto-detect and render appropriate filters
echo CP_Library\Filters\TemplateHelpers::render_current_filters([
    'context' => 'archive',
    'show_search' => true
]);
```

## Template Parts

```php
// Display filter notice
cp_library()->templates->get_template_part('parts/filter-notice');

// Display filter form
cp_library()->templates->get_template_part('parts/filters/form', [
    'post_type' => 'cpl_item'
]);

// Display selected filters
cp_library()->templates->get_template_part('parts/filters/selected', [
    'post_type' => 'cpl_item'
]);
```

## JavaScript Initialization

```javascript
// Initialize sermon filters
const sermonFilter = new CPLibraryFilter({
    context: 'archive',
    container: document.querySelector('.cpl-filter'),
    postType: 'cpl_item',
    autoSubmit: true
});

// Initialize series filters
const seriesFilter = new CPLibraryFilter({
    context: 'archive',
    container: document.querySelector('.cpl-filter'),
    postType: 'cpl_item_type',
    autoSubmit: true
});
```

## Error Handling (PHP)

```php
use CP_Library\Filters\FilterException;
use CP_Library\Filters\ErrorCodes;
use CP_Library\Filters\ErrorHandler;

try {
    // Code that might fail
    if (!$facet) {
        throw new FilterException(
            'Invalid facet',
            ErrorCodes::FACET_NOT_FOUND,
            ['facet_id' => $facet_id]
        );
    }
} catch (FilterException $e) {
    // Handle exception
    ErrorHandler::get_instance()->handle_exception($e);
}
```

## Error Handling (JavaScript)

```javascript
import * as ErrorHandler from './errorHandler';

try {
    // Code that might fail
} catch (error) {
    ErrorHandler.handleCaughtError(error, container);
}

// AJAX error handling
jQuery.ajax({
    // ...
    error: function(jqXHR, textStatus, errorThrown) {
        ErrorHandler.handleAjaxError(jqXHR, textStatus, errorThrown, container);
    }
});
```

## Facet Registration

```php
// Register a taxonomy facet
$manager->register_taxonomy_facet('cpl_topics');

// Register a custom facet
$manager->register_facet('custom_facet', [
    'label' => 'Custom Facet',
    'param' => 'facet-custom',
    'type' => 'custom',
    'query_callback' => [$this, 'query_custom_facet'],
    'options_callback' => [$this, 'get_custom_facet_options']
]);

// Register a meta facet
$manager->register_meta_facet('release_year', 'release_year', [
    'label' => 'Release Year'
]);
```

## Filter Actions & Filters

### Actions

```php
// Register filter managers
do_action('cpl_register_filter_managers');

// Register facets for a specific post type
do_action("cpl_register_facets_{$post_type}", $filter_manager);

// Before archive list
do_action('cpl_before_archive_list');
do_action('cpl_before_archive_{$type}_list');
```

### Filters

```php
// Filter query variables for a specific post type
add_filter("cpl_filter_query_vars_{$post_type}", function($query_vars, $facet_id, $context) {
    // Modify query vars
    return $query_vars;
}, 10, 3);

// Filter query arguments for a specific post type
add_filter("cpl_filter_query_args_{$post_type}", function($query_args, $facet_id, $context, $args) {
    // Modify query args
    return $query_args;
}, 10, 4);
```

## Common Patterns

### Creating a Custom Filter Manager

```php
class CustomFilterManager extends CP_Library\Filters\AbstractFilterManager {
    
    protected function register_default_contexts() {
        $this->register_context('archive', [
            'label' => 'Archive'
        ]);
    }
    
    protected function register_default_facets() {
        // Register taxonomies
        $taxonomies = get_object_taxonomies($this->post_type, 'objects');
        foreach ($taxonomies as $taxonomy) {
            $this->register_taxonomy_facet($taxonomy->name);
        }
        
        // Register other facets
    }
}
```

### Filtering Queries Programmatically

```php
// Get a query with filters applied
function get_filtered_items($filters = null) {
    // If no filters provided, get from request
    if (null === $filters) {
        $filters = cpl_get_active_filters('cpl_item');
    }
    
    // Create query
    $args = [
        'post_type' => 'cpl_item',
        'posts_per_page' => 10
    ];
    
    $query = new WP_Query($args);
    
    // Apply filters
    $query = cpl_add_filters_to_query($query, $filters, 'cpl_item');
    
    return $query;
}
```

### Detecting and Using Active Filters

```php
// Check if specific filter is active
function is_topic_filter_active($topic_slug) {
    $active_filters = cpl_get_active_filters();
    
    if (empty($active_filters['cpl_topics'])) {
        return false;
    }
    
    return in_array($topic_slug, $active_filters['cpl_topics']);
}

// Get active filter values
function get_active_topic_filters() {
    $active_filters = cpl_get_active_filters();
    return $active_filters['cpl_topics'] ?? [];
}
```

## Further Resources

- [Filter System Migration Guide](filter-system-migration.md)
- [SEO Enhancements Documentation](seo-enhancements.md)
- [Error Handling Documentation](error-handling.md)