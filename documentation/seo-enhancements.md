# CP Library Filter System: SEO Enhancements

This document describes the SEO enhancement features for the CP Library filter system.

## Overview

The SEO enhancement system in the CP Library filter system is designed to ensure that filtered archive pages follow SEO best practices, provide structured data to search engines, and prevent duplicate content issues. Key features include:

- Canonical URL management for filtered pages
- Schema.org structured data markup
- Meta tag optimization
- Search engine indexing controls
- User-friendly filter information display

## Features

### Canonical URL Management

Filtered pages use canonical URLs to point to their unfiltered parent archive page. This helps prevent search engines from indexing duplicate content that may arise from numerous filter combinations.

```php
// Get the canonical URL for a filtered page
$canonical_url = cpl_get_canonical_url();

// Output the canonical tag
cpl_output_canonical();
```

### Schema.org Structured Data

Filtered results include structured data markup to help search engines understand the content better. Each item in the filtered results includes its own schema, and the page itself uses appropriate CollectionPage schema.

```php
// Output structured data for an item in filtered results
echo cpl_item_structured_data($post, $position);
```

### Meta Tags for SEO

The SEO system automatically adds or modifies meta tags for filtered pages, including:

- Title tags with filter information
- Meta descriptions with filter parameters
- Robots meta tags to control indexing

### Search Engine Indexing Controls

Filtered pages are set to "noindex, follow" to prevent search engines from indexing filter variations while still allowing them to follow links.

```php
// Output robots meta tag
cpl_output_robots_meta();
```

### User-Friendly Filter Information

Users see a clear indication of active filters with options to clear them.

```php
// Display a filter notice with active filters
cp_library()->templates->get_template_part('parts/filter-notice');
```

## Implementation Details

### Template Hooks

The SEO system uses the following action hooks to inject SEO elements:

- `cpl_before_archive_list`: Outputs the filter notice showing active filters
- `cpl_before_archive_{type}_list`: Type-specific filter notice output
- `wp_head`: Outputs canonical URLs and meta tags

### Programmatic Access

Functions for programmatic access include:

```php
// Check if the current page has active filters
cpl_has_active_filters();

// Get the canonical URL for the current filtered page
cpl_get_canonical_url();

// Output structured data for an item
cpl_item_structured_data($post, $position);

// Output canonical tag
cpl_output_canonical();

// Output robots meta tag
cpl_output_robots_meta();

// Get active filters
cpl_get_active_filters($post_type);

// Add filters to a query object
cpl_add_filters_to_query($query, $filters, $post_type);
```

### SEO Plugin Integration

The system integrates with popular SEO plugins:

- **Yoast SEO**: Modifies title, description, canonical, and schema
- **Rank Math**: Modifies title, description, canonical, and schema
- **All in One SEO Pack**: Modifies title, description, and canonical
- **The SEO Framework**: Modifies title, description, and canonical

## Using Structured Data

### Item Structured Data

Each item in filtered results includes structured data based on its post type:

#### Sermons

```json
{
  "@type": "CreativeWork",
  "position": 1,
  "url": "https://example.com/sermons/sermon-title/",
  "name": "Sermon Title",
  "image": "https://example.com/wp-content/uploads/sermon-image.jpg",
  "description": "Sermon description text",
  "datePublished": "2023-01-01",
  "author": {
    "@type": "Person",
    "name": "Speaker Name",
    "url": "https://example.com/speakers/speaker-name/"
  },
  "isPartOf": {
    "@type": "CreativeWorkSeries",
    "name": "Series Title",
    "url": "https://example.com/series/series-title/"
  },
  "audio": {
    "@type": "AudioObject",
    "contentUrl": "https://example.com/wp-content/uploads/sermon-audio.mp3",
    "name": "Sermon Title - Audio"
  }
}
```

#### Series

```json
{
  "@type": "CreativeWorkSeries",
  "position": 1,
  "url": "https://example.com/series/series-title/",
  "name": "Series Title",
  "image": "https://example.com/wp-content/uploads/series-image.jpg",
  "description": "Series description text",
  "numEpisodes": 5,
  "keywords": "keyword1, keyword2, keyword3"
}
```

### Page Structured Data

Filtered pages include a CollectionPage schema:

```json
{
  "@context": "https://schema.org",
  "@type": "CollectionPage",
  "url": "https://example.com/sermons/?facet-topic=faith",
  "name": "Sermons - Faith",
  "description": "Sermons filtered by topic: Faith",
  "breadcrumb": {
    "@type": "BreadcrumbList",
    "itemListElement": [
      {
        "@type": "ListItem",
        "position": 1,
        "name": "Home",
        "item": "https://example.com/"
      },
      {
        "@type": "ListItem",
        "position": 2,
        "name": "Sermons",
        "item": "https://example.com/sermons/"
      },
      {
        "@type": "ListItem",
        "position": 3,
        "name": "Faith",
        "item": "https://example.com/sermons/?facet-topic=faith"
      }
    ]
  }
}
```

## Best Practices

1. **Canonical URLs**: Always use canonical URLs on filtered pages to prevent duplicate content issues.

2. **Structured Data**: Include structured data for all items in filtered results.

3. **Robots Meta**: Use "noindex, follow" for filtered pages to prevent search engines from indexing them.

4. **Filter URLs**: Create clean, descriptive filter URLs that are user-friendly.

5. **User Interface**: Clearly show users which filters are active and how to clear them.

6. **Performance**: Use caching for structured data generation to minimize overhead.

7. **Testing**: Regularly test your filtered pages with search engine testing tools.

## Debugging

For developers, the system provides debugging information:

- When `WP_DEBUG` is enabled, SEO information is shown to administrators.
- The canonical URL is displayed to logged-in users with `edit_posts` capability.
- Schema markup can be inspected in the page source.

## Future Enhancements

Planned enhancements include:

- Sitemaps for filtered archives
- Breadcrumb integration improvements
- Additional schema types for specific content categories
- Filter URL parameter optimization
- Analytics integration for filter usage tracking