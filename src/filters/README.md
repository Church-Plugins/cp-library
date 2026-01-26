# CP Library Filter System

This directory contains the JavaScript code for the CP Library filter system, which provides faceted filtering functionality for sermons and series content types.

## Overview

The filter system allows users to filter content by various facets such as:
- Taxonomies (Scripture, Topics, Seasons)
- Speakers
- Service Types
- Year
- Sermon Count (for series)

## How It Works

The core of the filter system is the `CPLibraryFilter` class, which creates instances for different filter contexts and post types. The system handles:

1. **Filter Initialization** - Creating filter instances for different contexts
2. **AJAX Loading** - Loading filter options via AJAX based on current state
3. **Form Submission** - Handling form submission when filter selections change
4. **Post Type Awareness** - Supporting different post types (cpl_item, cpl_item_type)

## Key Features

- **Multi-post type support**: Different filter managers based on post type (sermons vs. series)
- **Context-specific filters**: Different contexts (archive, service-type, speaker, season, topic)
- **Dynamic AJAX loading**: Filter options load via AJAX based on current selections
- **AJAX pagination**: Pagination that loads content without page refresh
- **Backward compatibility**: Support for existing filter implementations

## Core Concepts

### Filter Instances

The system creates filter instances for various filter contexts:

```javascript
window.cpLibraryFilters['sermon-archive'] = new CPLibraryFilter({
  context: 'archive',
  container: filter[0],
  contextArgs: {},
  postType: 'cpl_item', // Sermon post type
  debug: false
});
```

### Post Type Support

Each filter instance is associated with a specific post type:
- `cpl_item` - Sermons
- `cpl_item_type` - Series

The post type determines which filter manager handles the requests on the server side.

### AJAX Requests

The system makes AJAX requests to load filter options and filtered content:
- `cpl_filter_options` - Gets filter options for a facet
- `cpl_filter_sermons` - Filters sermon content
- `cpl_filter_series` - Filters series content

## File Structure

- `index.js` - Main filter implementation (CPLibraryFilter class and initialization)

## Integration

The filter system integrates with the PHP-side filter managers:
- `SermonFilterManager` for sermon content
- `SeriesFilterManager` for series content

## Usage

To use the filter system in templates:

```php
// For sermons
echo \CP_Library\Filters\TemplateHelpers::render_sermon_filters([
  'context' => 'archive',
  'show_search' => true,
  'taxonomies' => ['cpl_scripture', 'cpl_topics', 'cpl_season'],
  'speaker' => true,
  'service_type' => true,
  'year' => true
]);

// For series
echo \CP_Library\Filters\TemplateHelpers::render_series_filters([
  'context' => 'archive',
  'show_search' => true,
  'taxonomies' => ['cpl_season', 'cpl_topics'],
  'sermon_count' => true,
  'year' => true
]);
```

## Customization

The filter system can be customized by:
1. Creating new filter managers for other post types
2. Adding new facet types
3. Creating custom filter contexts
4. Modifying the filter templates