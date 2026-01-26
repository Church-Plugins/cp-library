# CP Library Filter System

This directory contains the PHP code for the CP Library Filter System, an extensible system for handling faceted filters across different post types.

## Core Components

### 1. FilterManager (FilterManager.php)
- Static registry/factory class for filter managers.
- Registers and retrieves filter managers by post type.
- Provides methods like `register_filter_manager()`, `get_filter_manager()`, and `get_current_manager()`.

### 2. AbstractFilterManager (AbstractFilterManager.php)
- Base class for all filter manager implementations.
- Implements common functionality for facet handling, context management, and AJAX operations.
- Provides methods for registering and retrieving facets, applying filters to queries, and rendering filter UI.

### 3. FacetType (FacetType.php)
- Interface defining the contract for facet implementations.
- Ensures consistent implementation of methods like `get_type()`, `apply_to_query()`, and `get_options()`.

### 4. Post Type-Specific Implementations (Types/ directory)
- `SermonFilterManager` - Handles filtering for sermon (cpl_item) post type.
- `SeriesFilterManager` - Handles filtering for series (cpl_item_type) post type.

### 5. Init (Init.php)
- Bootstrap class for the filter system.
- Registers filter managers, sets up AJAX handlers, and initializes assets.
- Provides central routing for AJAX requests to appropriate filter managers based on post type.

## AJAX Handlers

The filter system provides several AJAX endpoints:

### 1. `cpl_filter_options`
- Action: `wp_ajax_cpl_filter_options` and `wp_ajax_nopriv_cpl_filter_options`
- Handler: `Init::ajax_filter_options_router()`
- Purpose: Fetches available options for a specific facet type, taking into account current filter selections.
- Parameters:
  - `filter_type`: The facet ID to get options for
  - `context`: The filter context (e.g., 'archive', 'service-type')
  - `post_type`: The post type (e.g., 'cpl_item', 'cpl_item_type')
  - `selected`: Array of currently selected values
  - `args`: Additional context arguments
  - `query_vars`: Current query variables

### 2. `cpl_filter_sermons`
- Action: `wp_ajax_cpl_filter_sermons` and `wp_ajax_nopriv_cpl_filter_sermons`
- Handler: `Init::ajax_filter_sermons_router()` → `SermonFilterManager::ajax_filter_sermons()`
- Purpose: Filters sermon content and returns HTML for the filtered results.
- Parameters:
  - `filters`: Array of filter selections
  - `context`: The filter context
  - `context_args`: Additional context arguments
  - `paged`: Page number for pagination
  - `template`: Template to use for rendering results

### 3. `cpl_filter_series`
- Action: `wp_ajax_cpl_filter_series` and `wp_ajax_nopriv_cpl_filter_series`
- Handler: `Init::ajax_filter_series_router()` → `SeriesFilterManager::ajax_filter_series()`
- Purpose: Filters series content and returns HTML for the filtered results.
- Parameters: (same as `cpl_filter_sermons`)

## Template Helpers

The `TemplateHelpers` class provides convenience methods for rendering filters in templates:

- `render_sermon_filters()` - Renders sermon filters
- `render_series_filters()` - Renders series filters
- `render_current_filters()` - Renders filters based on the current post type

## Error Handling

The filter system includes robust error handling:

1. Error codes and descriptive messages
2. HTTP status codes for AJAX responses
3. Try/catch blocks around critical operations
4. Input validation and sanitization

## Post Type Awareness

The system is designed to handle multiple post types simultaneously:

1. Each post type has its own filter manager
2. AJAX handlers route requests to the appropriate filter manager
3. JavaScript system is aware of post types and uses the correct endpoints

## Usage Example

```php
// Render sermon filters in a template
echo \CP_Library\Filters\TemplateHelpers::render_sermon_filters([
    'context' => 'archive',
    'show_search' => true,
    'taxonomies' => ['cpl_scripture', 'cpl_topics', 'cpl_season'],
    'speaker' => true,
    'service_type' => true
]);

// Get filter manager for series
$series_filter_manager = \CP_Library\Filters\FilterManager::get_filter_manager('cpl_item_type');
if ($series_filter_manager) {
    // Use the filter manager
    $series_filter_manager->render_filter_form([
        'context' => 'archive',
        'show_search' => true
    ]);
}
```