# CP Library Filter System Refactoring
## Scope of Work

This document outlines the scope of work for refactoring the CP Library filter system to support multiple content types (initially Sermons and Series, with provision for future expansion).

## Current System Analysis

The current filter system is built around a singleton `Filters` class that is hardcoded to work with sermons (`cpl_item` post type). It provides faceted filtering capabilities but lacks flexibility to work with different content types.

**Key limitations:**
- Hardcoded `cpl_item` post type in query building
- Singleton pattern prevents multiple filter instances for different content types
- No way to specify which facets apply to which content types
- Query modification tied specifically to sermon content structure

## Proposed Architecture

### 1. Core Components

#### 1.1 FilterManager Registry/Factory
- Create a primary `FilterManager` class to serve as a registry and factory
- Register filter implementations for different post types
- Provide discovery methods to find appropriate implementations
- Handle common resource loading (CSS/JS)

#### 1.2 AbstractFilterManager Base Class
- Create an abstract base class for all filter manager implementations
- Define standard interface for filter system functionality
- Implement common utilities (caching, rendering, ajax handling)
- Define abstract methods that implementations must provide

#### 1.3 Content-Specific Filter Managers
- `SermonFilterManager` for handling cpl_item
- `SeriesFilterManager` for handling cpl_item_type
- Future implementations for additional content types as needed

#### 1.4 FacetType Interface/Abstract Class
- Define interface for facet implementations
- Allow facets to specify compatible content types
- Support content-specific query modifications

### 2. Database & Data Structures

#### 2.1 Facet Configuration Schema
- `id` - Unique identifier
- `label` - Display label
- `param` - URL parameter
- `query_var` - WP_Query variable
- `type` - Facet type (taxonomy, meta, etc.)
- `post_types` - Compatible post types array
- Callbacks with post_type parameter

#### 2.2 Context Configuration Schema
- `id` - Context identifier
- `label` - Display label
- `post_type` - Associated post type
- Content-specific query modifications

#### 2.3 Caching Strategy
- Per post-type facet caching
- Cache key construction accounting for post_type
- Shared cache clearing mechanisms
- Ensure that the current query parameters are taken into account when caching

### 3. Functional Requirements

#### 3.1 Registration APIs
- Register filter managers for post types
- Register facets with post type compatibility
- Register contexts with post type association

#### 3.2 Query Handling
- Content-specific query modification
- Support for different database queries
- Handle relationships between content types

#### 3.3 UI Components
- Filter form generation per content type
- Selected filters display
- AJAX filter updates
- Support for different filter UIs by content type

#### 3.4 Render Methods
- Unified rendering API
- Content-specific template loading
- Configuration options to customize display

## Planned Improvements

The following improvements are planned for this refactoring:

### Code Optimization
- **Remove Code Duplication** - Eliminate duplicated code between the filter implementation and the `get_terms()` method to improve maintainability and reduce technical debt
- **Refactor Query Building** - Create a more modular approach to building query parameters across different filter types

### Error Handling Enhancements
- **Detailed Error Messages** - Improve error handling in AJAX responses with more specific messages
- **Error Codes** - Add standardized error codes to help identify and troubleshoot issues
- **Context Information** - Include context data in error responses for easier debugging
- **Exception Handling** - Implement try/catch blocks around critical filter operations

### Accessibility Improvements
- **ARIA Attributes** - Add ARIA roles, states, and properties to filter elements
- **Keyboard Navigation** - Ensure proper focus management and keyboard navigation for dropdown menus
- **Screen Reader Support** - Improve screen reader announcements for filter state changes
- **Focus Management** - Implement better focus handling when filters are applied or removed

### SEO Enhancements
- **Schema.org Markup** - Add structured data to improve search engine visibility of filtered content
- **Metadata Tags** - Include appropriate meta tags for sharing filtered content
- **URL Structure** - Optimize URL patterns for filtered content to improve SEO
- **Canonical URLs** - Implement proper canonical URL handling for filtered pages

## Implementation Plan

### Phase 1: Architecture Foundation

1. **Create Base Classes**
   - Abstract `AbstractFilterManager` class
   - `FilterManager` registry/factory
   - `FacetType` interface/abstract class

2. **Directory Structure**
   - `/Filters/` base directory
   - `/Filters/Types/` for filter managers
   - `/Filters/Facets/` for facet implementations

3. **Configuration System**
   - Convert existing settings to support multiple post types

### Phase 2: Sermon Implementation

1. **Migrate Existing Functionality**
   - Create `SermonFilterManager` extending `AbstractFilterManager`
   - Move sermon-specific code from `Filters` to `SermonFilterManager`
   - Update references throughout the codebase

2. **Facet Implementations**
   - Create standard facet implementations
   - Migrate existing facet registration to new system

3. **UI Templates**
   - Update templates to support the new structure
   - Update existing code to use new system directly (no backward compatibility)

### Phase 3: Series Implementation

1. **Create Series Filter Manager**
   - Implement `SeriesFilterManager` for cpl_item_type
   - Define series-specific facets
   - Create series-specific queries

2. **Integration Points**
   - Add filter hooks to series templates
   - Connect series archive to filter system

3. **Testing & Refinement**
   - Test cross-filter interactions
   - Optimize performance

### Phase 4: JavaScript Refactoring

1. **AJAX Integration**
   - Update JS to support multiple filter managers
   - Create endpoints for content-specific filters

2. **UI Enhancements**
   - Responsive improvements
   - Content-specific UI variations

3. **Accessibility Implementation**
   - Add ARIA attributes to filter elements
   - Implement keyboard navigation improvements
   - Test with screen readers

### Phase 5: Error Handling, SEO & Documentation

1. **Error Handling Implementation**
   - Add error codes and detailed error messages
   - Implement try/catch blocks
   - Add logging for critical operations

2. **SEO Optimization**
   - Implement schema.org markup
   - Set up canonical URLs for filtered content
   - Optimize filter URL structure

3. **Code Migration Documentation**
   - Document how to use the new filter system
   - Create examples for common filter operations
   - Provide guidance for plugin extensions

## Code Structure

### New Class Hierarchy

```
Filters/
|- FilterManager.php (Registry/factory)
|- AbstractFilterManager.php
|- Types/
   |- SermonFilterManager.php
   |- SeriesFilterManager.php
|- Facets/
   |- AbstractFacet.php
   |- TaxonomyFacet.php
   |- MetaFacet.php
   |- SourceFacet.php
|- Contexts/
   |- AbstractContext.php
   |- ArchiveContext.php
   |- ServiceTypeContext.php
```

### Key Methods

#### FilterManager

```php
// Registration
register_filter_manager( $post_type, $class )
get_filter_manager( $post_type )

// Factory methods
get_current_manager()
get_active_managers()

// Resource handling
enqueue_scripts()
```

#### AbstractFilterManager

```php
// Facet registration
register_facet( $id, $args )
register_taxonomy_facet( $taxonomy, $args )
register_meta_facet( $id, $meta_key, $args )

// Context handling
register_context( $id, $args )
get_context( $id )

// Facet retrieval
get_facet( $id )
get_facets( $args )

// Filter options
get_filter_options( $facet_id, $context, $args )
ajax_get_filter_options()

// UI rendering
render_filter_form( $args )
render_selected_filters( $args )

// Query modification
apply_facet_filters( $query )
```

#### Content-Specific Implementations

```php
// SermonFilterManager
register_default_facets()
register_default_contexts()
query_taxonomy_facet( $query, $values, $facet_config )
get_terms( $args )
get_sources( $args )

// SeriesFilterManager
register_default_facets()
register_default_contexts()
query_taxonomy_facet( $query, $values, $facet_config )
// Series-specific methods
```

## Integration Points

1. **Template Integration**
   - Update existing templates to use new filter system directly
   - Create series-specific filter templates
   - Add hooks for custom templates

2. **Admin Integration**
   - Update settings to configure filters by post type
   - Add UI for managing filter display options

3. **Query Integration**
   - Hook into pre_get_posts for different post types
   - Support filtering in shortcodes and blocks

## Development Timeline

### Week 1: Foundation
- Create base classes and interfaces
- Refactor existing code to follow new structure
- Build registry/factory pattern

### Week 2: Sermon Transition
- Migrate sermon filter functionality
- Update existing code to use new system
- Test with updated implementation

### Week 3: Series Implementation
- Implement series-specific filter manager
- Create series-specific facets
- Connect to series templates and archives

### Week 4: JavaScript & UI Enhancements
- Update JavaScript for multiple content types
- Implement ARIA attributes and keyboard navigation
- Test with screen readers

### Week 5: Error Handling & Documentation
- Implement error codes and detailed messages
- Add schema.org markup for filtered content
- Create usage documentation and code examples

## Future Considerations

1. **Additional Content Types**
   - Support for Service Types or other custom post types
   - Framework for third-party extensions

2. **Enhanced Filter Types**
   - Date range filters
   - Numeric range filters
   - Search integration

3. **Performance Optimization**
   - Advanced caching strategies
   - Query optimization for large content libraries
