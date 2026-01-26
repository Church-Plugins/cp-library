# CP Library Filter System: Migration Guide

This document provides comprehensive guidance for migrating from the legacy filter system to the new multi-post-type filter architecture introduced in version 1.6.0.

## Overview of Changes

The CP Library filter system has been refactored from a single-post-type implementation focused on sermons (cpl_item) to a flexible architecture that supports multiple post types, including sermons (cpl_item) and series (cpl_item_type). Key changes include:

- **Registry Pattern**: Filter managers are now registered by post type
- **Abstract Base Class**: Common functionality lives in AbstractFilterManager
- **Post Type Specialization**: Type-specific filter managers handle unique requirements
- **Enhanced JavaScript**: Client-side code now supports multiple post types
- **Improved Accessibility**: Full keyboard navigation and screen reader support
- **SEO Enhancements**: Structured data and canonical URL management
- **Error Handling**: Standardized error handling throughout

## Upgrade Path

### For Theme Developers

If you've built custom themes that integrate with CP Library's filter system, you'll need to make the following changes:

1. **Update Filter Calls**: Use the new post-type-aware filter functions
2. **Update Templates**: Use the new template helper functions
3. **Update JavaScript Initialization**: Use the post-type-aware initialization

### For Plugin Developers

If you've created extensions or plugins that integrate with the filter system, you'll need to:

1. **Register Filter Managers**: For any custom post types you want to support
2. **Implement Custom Managers**: For specialized filter behavior
3. **Update AJAX Handlers**: Use the new centralized router

## Migration Examples

### Legacy Code vs. New Code

#### Getting a Filter Manager

**Legacy Code:**
```php
// Get the filter manager (always sermons)
$filter_manager = cp_library()->filters;
```

**New Code:**
```php
// Sermon filter manager
$sermon_manager = CP_Library\Filters\FilterManager::get_filter_manager('cpl_item');

// Series filter manager
$series_manager = CP_Library\Filters\FilterManager::get_filter_manager('cpl_item_type');

// Current post type manager (auto-detect)
$current_manager = CP_Library\Filters\TemplateHelpers::get_current_manager();
```

#### Rendering Filter Forms

**Legacy Code:**
```php
// Render sermon filters
echo cp_library()->filters->render_filter_form([
    'context' => 'archive',
    'show_search' => true
]);
```

**New Code:**
```php
// For sermons
echo CP_Library\Filters\TemplateHelpers::render_sermon_filters([
    'context' => 'archive',
    'show_search' => true
]);

// For series
echo CP_Library\Filters\TemplateHelpers::render_series_filters([
    'context' => 'archive',
    'show_search' => true
]);

// Auto-detect post type
echo CP_Library\Filters\TemplateHelpers::render_current_filters([
    'context' => 'archive',
    'show_search' => true
]);
```

#### Checking for Active Filters

**Legacy Code:**
```php
// Check if a filter is active
$is_active = isset($_GET['facet-topic']) && !empty($_GET['facet-topic']);
```

**New Code:**
```php
// Using the helper function
$has_active_filters = cpl_has_active_filters();

// Getting specific active filters
$active_filters = cpl_get_active_filters();
$has_topic_filter = !empty($active_filters['cpl_topics']);
```

#### Adding Filters to Queries

**Legacy Code:**
```php
// Modify a query with filters
$query = cp_library()->filters->filter_query($query);
```

**New Code:**
```php
// Using the helper function - automatically gets filters from request
$query = cpl_add_filters_to_query($query, cpl_get_active_filters(), 'cpl_item');

// Manually specifying filters
$filters = [
    'cpl_topics' => ['faith', 'love'],
    'speaker' => ['john-doe']
];
$query = cpl_add_filters_to_query($query, $filters, 'cpl_item');
```

### Template Changes

#### Filter Form in Templates

**Legacy Code:**
```php
<div class="filter-form">
    <?php echo cp_library()->filters->render_filter_form(); ?>
</div>
```

**New Code:**
```php
<div class="filter-form">
    <?php 
    $post_type = get_post_type() ?: 'cpl_item';
    $filter_manager = CP_Library\Filters\FilterManager::get_filter_manager($post_type);
    
    if ($filter_manager) {
        echo $filter_manager->render_filter_form([
            'context' => 'archive',
            'post_type' => $post_type
        ]);
    }
    ?>
</div>
```

#### Selected Filters in Templates

**Legacy Code:**
```php
<div class="selected-filters">
    <?php echo cp_library()->filters->render_selected_filters(); ?>
</div>
```

**New Code:**
```php
<div class="selected-filters">
    <?php 
    $post_type = get_post_type() ?: 'cpl_item';
    $filter_manager = CP_Library\Filters\FilterManager::get_filter_manager($post_type);
    
    if ($filter_manager) {
        echo $filter_manager->render_selected_filters([
            'context' => 'archive',
            'post_type' => $post_type
        ]);
    }
    ?>
</div>
```

#### Active Filter Detection

**Legacy Code:**
```php
<?php if (isset($_GET['facet-topic'])): ?>
    <div class="filter-active-notice">
        Filtering by topic
    </div>
<?php endif; ?>
```

**New Code:**
```php
<?php if (function_exists('cpl_has_active_filters') && cpl_has_active_filters()): ?>
    <?php cp_library()->templates->get_template_part('parts/filter-notice'); ?>
<?php endif; ?>
```

### JavaScript Changes

#### Initializing Filters

**Legacy Code:**
```javascript
// Initialize filter directly
var filter = new CPLibraryFilter({
    context: 'archive',
    container: document.querySelector('.cpl-filter')
});
```

**New Code:**
```javascript
// Initialize filter with post type
var filter = new CPLibraryFilter({
    context: 'archive',
    container: document.querySelector('.cpl-filter'),
    postType: 'cpl_item' // 'cpl_item' for sermons, 'cpl_item_type' for series
});
```

#### AJAX Requests

**Legacy Code:**
```javascript
// AJAX request for filter options
jQuery.ajax({
    url: cplVars.ajax_url,
    type: 'POST',
    data: {
        action: 'cpl_filter_options',
        filter_type: facetType,
        selected: selected
    },
    success: function(response) {
        // Handle response
    }
});
```

**New Code:**
```javascript
// AJAX request for filter options
jQuery.ajax({
    url: cplVars.ajax_url,
    type: 'POST',
    data: {
        action: 'cpl_filter_options',
        filter_type: facetType,
        selected: selected,
        post_type: 'cpl_item', // Specify post type
        nonce: cplVars.nonce
    },
    success: function(response) {
        // Handle response
    },
    error: function(jqXHR, textStatus, errorThrown) {
        // Use error handler
        ErrorHandler.handleAjaxError(jqXHR, textStatus, errorThrown, container);
    }
});
```

## Custom Filter Managers

### Creating a Custom Filter Manager

If you need to create a filter manager for a custom post type, follow this pattern:

```php
<?php
/**
 * Custom Post Type Filter Manager
 *
 * @package YourPlugin\Filters
 */

namespace YourPlugin\Filters;

use CP_Library\Filters\AbstractFilterManager;

class CustomFilterManager extends AbstractFilterManager {
    
    /**
     * Register default contexts
     */
    protected function register_default_contexts() {
        // Register the archive context
        $this->register_context('archive', [
            'label' => __('Archive', 'your-plugin'),
        ]);
        
        // Add additional contexts as needed
    }
    
    /**
     * Register default facets
     */
    protected function register_default_facets() {
        // Register taxonomies
        $taxonomies = get_object_taxonomies($this->post_type, 'objects');
        foreach ($taxonomies as $taxonomy) {
            $this->register_taxonomy_facet($taxonomy->name);
        }
        
        // Register custom facets
        $this->register_facet('custom_facet', [
            'label' => __('Custom Facet', 'your-plugin'),
            'type' => 'custom',
            'query_callback' => [$this, 'query_custom_facet'],
            'options_callback' => [$this, 'get_custom_facet_options']
        ]);
    }
    
    /**
     * Query callback for custom facet
     */
    public function query_custom_facet($query, $values, $facet_config) {
        // Implement query modification logic
    }
    
    /**
     * Options callback for custom facet
     */
    public function get_custom_facet_options($args) {
        // Implement options retrieval logic
        return [];
    }
}
```

### Registering a Custom Filter Manager

Register your custom filter manager in a plugin or theme:

```php
add_action('cpl_register_filter_managers', function() {
    // Register filter manager for custom post type
    CP_Library\Filters\FilterManager::register_filter_manager(
        'your_custom_post_type',
        YourPlugin\Filters\CustomFilterManager::class
    );
});
```

## Facet Registration

### Creating Custom Facets

To create a custom facet type:

```php
// Register a custom facet
$filter_manager->register_facet('release_year', [
    'label' => __('Release Year', 'your-plugin'),
    'param' => 'facet-year',
    'type' => 'custom',
    'query_callback' => function($query, $values, $config) {
        // Extract years and build a meta query
        if (!empty($values)) {
            $query->set('meta_query', [
                [
                    'key' => 'release_year',
                    'value' => $values,
                    'compare' => 'IN',
                ]
            ]);
        }
    },
    'options_callback' => function($args) {
        global $wpdb;
        
        // Get unique years from post meta
        $query = $wpdb->prepare(
            "SELECT DISTINCT meta_value AS value, meta_value AS title, COUNT(*) AS count
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = %s
            AND p.post_type = %s
            AND p.post_status = 'publish'
            GROUP BY meta_value
            ORDER BY meta_value DESC",
            'release_year',
            $args['post_type']
        );
        
        return $wpdb->get_results($query) ?: [];
    }
]);
```

## Error Handling

### Using Error Handler in PHP

```php
use CP_Library\Filters\ErrorHandler;
use CP_Library\Filters\ErrorCodes;
use CP_Library\Filters\FilterException;

try {
    // Operation that might fail
    if (!$facet) {
        throw new FilterException(
            sprintf(__('Invalid facet ID: %s', 'your-plugin'), $facet_id),
            ErrorCodes::FACET_NOT_FOUND,
            ['facet_id' => $facet_id]
        );
    }
} catch (FilterException $e) {
    // Handle the filter exception
    ErrorHandler::get_instance()->handle_exception($e);
} catch (\Exception $e) {
    // Handle generic exceptions
    ErrorHandler::get_instance()->handle_exception($e);
}
```

### Using Error Handler in JavaScript

```javascript
import * as ErrorHandler from './errorHandler';

try {
    // Operation that might fail
    if (!container) {
        throw new Error('Container element not found');
    }
} catch (error) {
    // Handle the error
    ErrorHandler.handleCaughtError(error, container);
}

// For AJAX errors
jQuery.ajax({
    // ...
    error: function(jqXHR, textStatus, errorThrown) {
        ErrorHandler.handleAjaxError(jqXHR, textStatus, errorThrown, container);
    }
});
```

## SEO Enhancements

### Adding Structured Data

```php
// In your template file
<?php if (function_exists('cpl_item_structured_data')): ?>
    <?php echo cpl_item_structured_data(get_post(), $position); ?>
<?php endif; ?>
```

### Managing Canonical URLs

```php
// In your header template
<?php if (function_exists('cpl_output_canonical') && cpl_has_active_filters()): ?>
    <?php cpl_output_canonical(); ?>
<?php endif; ?>
```

### Handling Robots Meta

```php
// In your header template
<?php if (function_exists('cpl_output_robots_meta') && cpl_has_active_filters()): ?>
    <?php cpl_output_robots_meta(); ?>
<?php endif; ?>
```

## Troubleshooting

### Common Migration Issues

1. **Missing Post Type**: If filters aren't working, check that you're specifying the correct post type.

```php
// Debug post type
$post_type = get_post_type();
echo "Current post type: " . $post_type;

// Check if filter manager exists
$manager = CP_Library\Filters\FilterManager::get_filter_manager($post_type);
var_dump($manager !== null);
```

2. **JavaScript Not Initialized**: Ensure JavaScript is initialized with the correct post type.

```javascript
console.log('Available filter managers:', cplFilters.postTypes);
console.log('Active post type:', cplFilters.activePostType);
```

3. **AJAX Not Working**: Check that AJAX requests include the post type parameter.

```javascript
// Add debug to AJAX data
data.debug = true;
console.log('AJAX request data:', data);
```

### Compatibility Layer

A compatibility layer exists to support legacy code that uses the old filter system. This layer routes calls through the new system, but it's recommended to update your code to use the new API directly.

```php
// Legacy code still works but uses the new system internally
cp_library()->filters->render_filter_form();

// But you should use this instead
CP_Library\Filters\TemplateHelpers::render_sermon_filters();
```

## Conclusion

The new filter system provides a more flexible, maintainable, and feature-rich foundation for content filtering in CP Library. By following this migration guide, you can update your code to take advantage of new features while ensuring backward compatibility with existing integrations.

For additional help or to report issues with migration, please contact the CP Library support team or submit an issue on GitHub.