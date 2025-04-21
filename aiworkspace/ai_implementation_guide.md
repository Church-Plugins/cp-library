# CP Library Filter System: AI Implementation Guide

This guide structures the filter system refactoring into discrete, AI-friendly implementation tasks. Each task defines specific inputs, expected outputs, and validation criteria.

## Phase 1: Foundation Classes and Interfaces

### Task 1.1: Create Directory Structure
- **Input**: Current codebase
- **Action**: Create necessary directories for new filter system
- **Output**: Directory structure matching project organization
- **Validation**: Directories exist and follow WordPress conventions

### Task 1.2: Create FilterManager Registry Class
- **Input**: Class requirements from scope document
- **Action**: Implement core registry/factory pattern class
- **Output**: FilterManager.php with static methods for registration
- **Validation**: Registry can register and retrieve filter managers

### Task 1.3: Define AbstractFilterManager Base Class
- **Input**: Interface requirements from scope document
- **Action**: Create abstract base class with method signatures
- **Output**: AbstractFilterManager.php with abstract and concrete methods
- **Validation**: Class contains all required methods with appropriate type hints

### Task 1.4: Define FacetType Interface
- **Input**: Requirements for facet structure
- **Action**: Create interface defining facet contract
- **Output**: FacetType.php interface
- **Validation**: Interface includes all necessary methods for facets

## Phase 2: Sermon Filter Manager Implementation

### Task 2.1: Implement SermonFilterManager Class
- **Input**: AbstractFilterManager and current Filters.php
- **Action**: Create sermon-specific implementation
- **Output**: SermonFilterManager.php extending AbstractFilterManager
- **Validation**: Class properly handles sermon filtering

### Task 2.2: Migrate Taxonomy Facet Registration
- **Input**: Current taxonomy facet registration code
- **Action**: Move and adapt code to new architecture
- **Output**: Updated facet registration for sermon (cpl_item) and series (cpl_item_type) taxonomies
- **Validation**: All taxonomy facets properly registered and functioning

### Task 2.3: Migrate Source Facet Handling (Speakers)
- **Input**: Current speaker facet handling code
- **Action**: Move and adapt code to new architecture
- **Output**: Speaker facet implementation
- **Validation**: Speaker filtering works correctly

### Task 2.4: Update Usage in Codebase
- **Input**: Existing code using the filter system
- **Action**: Update to use new FilterManager API directly
- **Output**: Updated code with direct references to new system
- **Validation**: All filter functionality works with new implementation

## Phase 3: Series Filter Manager Implementation

### Task 3.1: Implement SeriesFilterManager Class
- **Input**: AbstractFilterManager and series requirements
- **Action**: Create series-specific implementation
- **Output**: SeriesFilterManager.php extending AbstractFilterManager
- **Validation**: Class handles series filtering correctly

### Task 3.2: Implement Series Facets
- **Input**: Series facet requirements (taxonomies, year facet)
- **Action**: Register series-specific facets
- **Output**: Facet registration for series filtering
- **Validation**: Series can be filtered by appropriate facets

### Task 3.3: Implement Series-Specific Queries
- **Input**: Series query requirements
- **Action**: Implement query modification for series
- **Output**: Series-specific query modification methods
- **Validation**: WP_Query objects correctly modified for series filtering

### Task 3.4: Series Template Integration
- **Input**: Series template specifications
- **Action**: Connect filter system to series templates
- **Output**: Series templates with filter integration
- **Validation**: Series archives display filters correctly

## Phase 4: JavaScript and Frontend Implementation

### Task 4.1: Filter Form JavaScript Refactoring
- **Input**: Current filter JavaScript
- **Action**: Update to support multiple post types
- **Output**: Updated JavaScript with post type awareness
- **Validation**: JavaScript properly handles different filter managers

### Task 4.2: AJAX Handler Implementation
- **Input**: Current AJAX handling code
- **Action**: Update for post type support
- **Output**: Updated AJAX handlers for filter options
- **Validation**: AJAX requests return correct options for each post type

### Task 4.3: Frontend Accessibility Improvements
- **Input**: Accessibility requirements
- **Action**: Add ARIA attributes and keyboard support
- **Output**: Accessible filter components
- **Validation**: Passes basic accessibility tests

## Phase 5: Error Handling and SEO

### Task 5.1: Error Handling Implementation
- **Input**: Error handling requirements
- **Action**: Implement standardized error system
- **Output**: Error codes, messages, and exception handling
- **Validation**: System gracefully handles error conditions

### Task 5.2: SEO Enhancement Implementation
- **Input**: SEO requirements
- **Action**: Implement SEO improvements
- **Output**: Schema markup and canonical URL handling
- **Validation**: Filtered pages have proper SEO elements

### Task 5.3: Code Migration Documentation
- **Input**: New filter system implementation
- **Action**: Create developer documentation
- **Output**: Usage guides, examples, and migration instructions
- **Validation**: Documentation covers all common use cases

## Implementation Details

For each task, the AI should follow these implementation guidelines:

### Class Implementation Pattern

1. Start with file header comment explaining purpose
2. Define namespace
3. Import dependencies
4. Add class/method documentation
5. Implement method signatures
6. Implement method bodies
7. Add appropriate error handling
8. Include inline documentation

### Example Implementation Structure

```php
<?php
/**
 * Filter Manager Class
 *
 * Provides registry and factory functionality for filter implementations.
 *
 * @package CP_Library\Filters
 * @since 1.6.0
 */

namespace CP_Library\Filters;

/**
 * FilterManager class - Registry and factory for filter implementations.
 *
 * This class manages different filter implementations for various post types.
 * It follows a registry pattern where filter managers are registered and retrieved
 * for specific post types.
 *
 * @since 1.6.0
 */
class FilterManager {

    /**
     * Registered filter managers by post type.
     *
     * @var array
     */
    private static $managers = [];

    /**
     * Register a filter manager for a post type.
     *
     * @param string $post_type The post type identifier
     * @param string $class     The fully qualified class name
     * @param array  $args      Optional arguments for the filter manager
     *
     * @return bool True if registration succeeded, false otherwise
     */
    public static function register_filter_manager( $post_type, $class, $args = [] ) {
        // Implementation here
    }

    // Additional methods...
}
```

## Testing Approach

For each implementation task, include unit tests that verify:

1. **Method Behavior**: Test that methods work as expected with various inputs
2. **Error Handling**: Test that invalid inputs are handled appropriately
3. **Integration**: Test that components work together correctly

Example test structure:

```php
/**
 * Test filter manager registration.
 */
public function test_register_filter_manager() {
    // Setup
    $post_type = 'cpl_item';
    $class = 'CP_Library\\Filters\\Types\\SermonFilterManager';

    // Execute
    $result = FilterManager::register_filter_manager( $post_type, $class );

    // Verify
    $this->assertTrue( $result );
    $this->assertInstanceOf( $class, FilterManager::get_filter_manager( $post_type ) );
}
```

## Code Migration Strategy

When updating existing code to use the new system:

1. **Direct Calls**
   - Replace `cp_library()->filters->method()` with `CP_Library\Filters\FilterManager::get_filter_manager('post_type')->method()`
   - Update templates to use the appropriate filter manager
   - Use static helper methods when appropriate for common operations

2. **Template Changes**
   - Update template part calls to pass the post_type parameter
   - Modify filter form rendering to use the correct manager
   - Update AJAX endpoints to specify post type

3. **Front-end Updates**
   - Ensure JavaScript initializes filters with correct post type
   - Update data attributes in templates to include post type
   - Modify filter submission to include post type context

## Validation Criteria

All implementations must meet these criteria:

1. **Functionality**: Correctly implements specified behavior
2. **Performance**: Efficient implementation, particularly for queries
3. **Directness**: Uses new system directly without legacy compatibility layers
4. **Error Handling**: Gracefully handles error conditions
5. **Documentation**: Well-documented with PHPDoc comments
6. **Coding Standards**: Follows WordPress coding standards
