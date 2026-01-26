# CP Sermon Library Filter System

This document explains the CP Sermon Library's filter system, its capabilities, and how to use it effectively in different contexts.

> **New in Version 1.6.0**: The filter system has been completely refactored to support multiple post types (sermons and series), with improved accessibility, error handling, and SEO features. For migration from previous versions, see [Filter System Migration](filter-system-migration.md).

## New Features in 1.6.0

- **Multi-Post Type Support**: Filter both sermons and series content
- **Enhanced Accessibility**: Full keyboard navigation and screen reader support
- **SEO Optimization**: Structured data and canonical URL management
- **Improved Error Handling**: Standardized error handling throughout
- **Developer Friendly**: More extensible architecture

## Additional Documentation

For information specific to the new filter system features:

- [Filter System Reference](filter-system-reference.md) - Quick reference guide
- [Filter System Migration](filter-system-migration.md) - Upgrade from legacy filters
- [Filter System Examples](filter-system-examples.md) - Code examples
- [SEO Enhancements](seo-enhancements.md) - SEO features for filters
- [Error Handling](error-handling.md) - Error management system

## User Documentation

### Filter Functionality Overview

The filter system allows users to quickly narrow down sermon content by various criteria:

- **Scripture References** - Find sermons by Bible book or passage
- **Topic Tags** - Filter by sermon topics
- **Seasons** - Filter by church seasons or series themes
- **Speakers** - Find sermons by specific speakers
- **Service Types** - Filter by service formats (Sunday morning, midweek, etc.)
- **Search** - Text search across sermon titles and content

### Using Filters on Sermon Archives

When browsing the main sermon archive (/sermons/), you can:

1. Click the "Filter" button to view available filter options
2. Select criteria from any of the dropdown menus
3. Choose multiple filters across different categories
4. View the applied filters above your search results
5. Remove specific filters by clicking the filter tag
6. Use the search box for keyword searches

### Using Filters with Service Types

When viewing sermons within a specific service type:

1. Navigate to a service type page
2. Use the filter options to narrow down sermons within that service type
3. Filters will only apply to sermons in the current service type
4. The Service Type filter is automatically disabled in this context

### Filter Display Settings

To customize how filters appear and function:

1. Navigate to Library → Settings → Advanced
2. Find the "Filter Settings" section
3. Options include:
   - **Filter Sorting** - Sort filters by sermon count or alphabetically
   - **Count Threshold** - Minimum number of sermons for a filter to display
   - **Show/Hide Count Numbers** - Toggle display of sermon counts
   - **Disable Specific Filters** - Prevent certain filters from appearing
   - **Mobile Filter Display** - Control how filters appear on small screens

## Developer Documentation

### Filter System Architecture

The CP Sermon Library's flexible filter system is built with a context-aware approach that allows filters to work consistently across different parts of the site.

#### Key Components

1. **PHP Filter API Class** (`CP_Library\Filters`)
   - Manages filter registration
   - Renders filter forms and selected filters
   - Processes AJAX requests for filter options

2. **JavaScript Filter Component** (`CPLibraryFilter`)
   - Handles client-side filter behavior
   - Loads filter options via AJAX
   - Manages filter state and form submission

3. **Context System**
   - Allows filters to adapt to different contexts (archive, service type, etc.)
   - Provides context-specific query parameters
   - Supports extensibility for custom contexts

### Using Filters in Custom Templates

To add filters to a custom template:

```php
<?php
// Render filter form with context
echo cp_library()->filters->render_filter_form([
    'context' => 'custom-context', // Unique context identifier
    'context_args' => [
        'custom_param' => 'value',  // Context-specific parameters
    ],
    'disabled_filters' => ['service-type'], // Filters to disable
    'container_class' => 'my-custom-filter', // Custom CSS class
]);

// Render selected filters
echo cp_library()->filters->render_selected_filters([
    'context' => 'custom-context',
    'context_args' => [
        'custom_param' => 'value',
    ],
]);
?>
```

### Adding Custom Filter Types

To register a custom filter type:

```php
// Register your filter type
add_action('init', function() {
    cp_library()->filters->register_filter_type('my-custom-filter', [
        'label' => 'My Custom Filter',
        'type' => 'custom',
        'query_callback' => function($args) {
            // Query for filter options
            return [
                (object)[
                    'title' => 'Option 1',
                    'value' => 'option-1',
                    'count' => 5
                ],
                // Additional options...
            ];
        }
    ]);
});
```

### Creating Custom Filter Contexts

To register a custom context:

```php
// Register your custom context
add_action('init', function() {
    cp_library()->filters->register_context('my-custom-context', [
        'label' => 'My Custom Context',
        'query_callback' => function($args) {
            // Prepare query arguments for this context
            return $args;
        }
    ]);
});
```

### Filter Hooks and Actions

The filter system provides several hooks for customization:

```php
// Modify filter options for a specific filter type
add_filter('cpl_filter_options_scripture', function($options, $context, $args) {
    // Modify the options
    return $options;
}, 10, 3);

// Modify all filter options
add_filter('cpl_filter_options', function($options, $filter_type, $context, $args) {
    // Modify the options
    return $options;
}, 10, 4);

// Control filter display
add_filter('cpl_filters_display', function($display) {
    // Modify display
    return $display;
});
```

### JavaScript API

The JavaScript filter component can be initialized programmatically:

```javascript
// Initialize a custom filter
const myFilter = new CPLibraryFilter({
    context: 'my-custom-context',
    container: document.querySelector('.my-filter-container'),
    contextArgs: {
        custom_param: 'value'
    },
    debug: false  // Set to true for debugging output
});

// Access the filter instances
console.log(window.cpLibraryFilters);
```

### Performance Optimizations

The filter system includes several performance optimizations:

1. **Caching System**
   - Filter options are cached to reduce database queries
   - Caches respect content updates (invalidated when content changes)

2. **Lazy Loading**
   - Filter options are loaded on demand via AJAX
   - Initial page load is not impacted by filter complexity

3. **Query Optimization**
   - Custom SQL queries are used for efficient option retrieval
   - Threshold setting prevents excessive options

### Technical Implementation Details

#### Database Queries

The system uses custom SQL queries to efficiently retrieve filter options:

1. **Taxonomy Filters** - Query WordPress term relationships with proper joins
2. **Source Filters** - Query custom source tables for relationships
3. **Custom Filters** - Support custom query callbacks

#### State Management

Filter state is managed primarily through URL parameters, allowing:
- Bookmarkable filter states
- Sharable filtered views
- Compatibility with page reloads

#### AJAX Processing

The AJAX handler (`cpl_filter_options`) processes requests with:
1. Security nonce verification
2. Context-specific parameter processing
3. Filter-specific option retrieval
4. Consistent option formatting

### Extending the Filter System

To extend the filter system with new functionality:

1. **Add Filter Types** - Use `register_filter_type()` for new filter criteria
2. **Create Contexts** - Use `register_context()` for new integration points
3. **Custom Rendering** - Override the render methods using provided hooks
4. **Custom Behaviors** - Extend the JavaScript class for specialized behaviors

## Troubleshooting Filters

### Common Issues and Solutions

1. **Filters Not Appearing**
   - Check if filters are disabled in Settings → Advanced
   - Verify threshold setting isn't too high for your content volume
   - Ensure the context is properly registered

2. **Filter Options Not Loading**
   - Check browser console for JavaScript errors
   - Verify AJAX URL is correctly configured
   - Check server logs for PHP errors

3. **Filter Not Working in Custom Context**
   - Verify context registration
   - Check context arguments are passed correctly
   - Ensure JavaScript initialization occurs after DOM is ready

4. **Performance Issues**
   - Increase count threshold to reduce options
   - Check for inefficient custom query callbacks
   - Consider caching plugin for high-traffic sites