# Service Types

This document provides a comprehensive overview of the Service Types feature in CP Library, covering both user functionality and technical implementation details.

## User Guide

### Overview

Service Types allow you to categorize sermons by the type of service they were delivered in (e.g., Sunday Morning, Youth Service, etc.). This feature is particularly useful for churches that hold multiple services with different formats.

### Enabling Service Types

Service Types are disabled by default. To enable them:

1. Navigate to Library → Settings → Post Types
2. Find the "Service Types" section
3. Toggle "Enable Service Types" to On
4. Customize the singular and plural labels if desired
5. Save changes

### Creating Service Types

Once enabled, you can create service types:

1. Navigate to Library → Service Types
2. Click "Add New"
3. Enter a name for the service type (e.g., "Sunday Morning")
4. Add a description if desired
5. Upload a featured image (optional)
6. Click "Publish"

### Assigning Service Types to Sermons

To assign a service type to a sermon:

1. Edit a sermon
2. Find the "Service Type" box in the sidebar
3. Select one or more service types
4. Update the sermon

### Service Type Archives

Each service type has its own archive page that displays all sermons for that service type. The URL format is:

```
/sermons/service-type/[service-type-slug]/
```

### Filtering by Service Type

The sermon archive includes a Service Type filter that allows visitors to filter sermons by service type.

### Service Type Templates

Service Type archives use a dedicated template that:

1. Displays the service type title and description
2. Shows all sermons assigned to that service type
3. Provides filtering options specific to that service type's sermons
4. Excludes the Service Type filter (since you're already viewing by service type)

### Visibility Control

Service Types include visibility control options:

1. Edit a service type
2. Find the "Service Type Options" meta box
3. Check "Exclude from Main List" to hide sermons with this service type from the main sermon list
4. Save changes

Sermons with hidden service types will:
- Not appear in the main sermon list
- Still appear in the service type's own archive
- Be accessible via direct links
- Appear in series and other taxonomy archives

### Advanced Filter Controls

When viewing a Service Type archive, the filter system automatically:

1. Only shows filter options relevant to sermons in that service type
2. Disables the Service Type filter
3. Applies any selected filters only to the current service type's sermons

For more details on the filter system, see the [Filter System Documentation](filter-system.md).

## Technical Implementation

### Database Structure

The Service Type functionality uses a hybrid data architecture combining WordPress posts with custom tables:

#### Custom Tables

1. **cp_source**
   - Primary table storing basic information about service types
   - Key fields: `id`, `origin_id`, `title`, `status`, `published`, `updated`
   - `origin_id` connects to the WordPress post ID

2. **cp_source_meta**
   - Stores metadata for service types
   - Key fields: `id`, `key`, `value`, `source_id`, `source_type_id`, `item_id`, `order`, `published`, `updated`
   - Supports ENUM key types like 'name', 'title', 'url', 'source_type', 'source_item'
   - Used to connect sermons to service types

3. **cp_source_type**
   - Categorizes different source types
   - Key fields: `id`, `origin_id`, `title`, `parent_id`, `published`, `updated`
   - Each service type is associated with a type_id for 'service_type'

#### WordPress Integration

- Each service type has a WordPress post with post type `cpl_service_type`
- The `origin_id` field in the custom table links to the WordPress post ID
- This integration provides both custom data storage and WordPress admin UI

### Model Implementation

The `ServiceType` model extends the base `Source` model from the Church Plugins framework:

```php
class ServiceType extends Source {
    static $type_key = 'service_type';
    static $post_type = 'cpl_service_type';
    
    // Methods for managing service types
}
```

Key methods in the model include:

- `get_all_service_types()` - Retrieves all service types
- `get_type_id()` - Gets or creates the source type ID for 'service_type'
- `get_all_items()` - Gets all items associated with this service type
- `update()`, `insert()` - Creates/updates service type records
- `add_type()` - Adds type metadata to associate with 'service_type'

### Controller Implementation

Service Types now use the MVC pattern with dedicated controllers:

```php
namespace CP_Library\Controllers;

class ServiceType extends Controller {
    // Methods for accessing service type data
    public function get_content() {...}
    public function get_title() {...}
    public function get_permalink() {...}
    public function get_thumbnail() {...}
    public function get_items() {...}
    public function get_items_count() {...}
    public function get_api_data() {...}
}
```

The controller provides a clean API for templates to access service type data without directly interacting with the model.

### Data Relationships

#### Relationship to Sermons (Items)

Service Types can be assigned to sermon items through a many-to-many relationship:

1. Each sermon item can be associated with multiple service types
2. Associations are stored in the `cp_source_meta` table
3. The relationship uses the key 'source_item' to connect items to service types
4. Items can query their service types using `get_service_types()`

#### Service Types as Variation Sources

Service Types serve as the foundation for sermon variations:

1. When enabled, variations use service types to distinguish different versions
2. Each sermon can have multiple variations, each linked to a different service type
3. Variations track different speakers, media files, and timestamps
4. The relationship is managed through the `variation_source()`, `variation_source_items()`, `variation_item_source()`, and `variation_item_save()` methods

### Filter Integration

Service Types are fully integrated with the new context-aware filter system:

1. Service Type archives use the 'service-type' context for filters
2. The system automatically adjusts filter options based on the current service type
3. The Service Type filter is automatically disabled in service type contexts
4. Custom SQL queries efficiently retrieve filter options relevant to the service type

### Best Practices for Implementation

When working with Service Types in custom code, follow these best practices:

1. **Retrieving Service Types**:
   ```php
   $service_types = \CP_Library\Models\ServiceType::get_all_service_types();
   ```

2. **Getting a Single Service Type**:
   ```php
   // Using the controller (preferred for templates)
   $service_type = new \CP_Library\Controllers\ServiceType($id);
   
   // Using the model (for data manipulation)
   $service_type_model = new \CP_Library\Models\ServiceType($id);
   ```

3. **Getting Items for a Service Type**:
   ```php
   $service_type = new \CP_Library\Controllers\ServiceType($id);
   $items = $service_type->get_items();
   ```

4. **Getting Service Types for an Item**:
   ```php
   $item = new \CP_Library\Controllers\Item($item_id);
   $service_types = $item->get_service_types();
   ```

5. **Updating Service Types for an Item**:
   ```php
   $item = new \CP_Library\Models\Item($item_id);
   $item->update_service_types([$service_type_id]);
   ```

### Performance Considerations

When working with Service Types, consider these performance factors:

1. **Caching**: Service type relationships are cached for better performance
2. **Query Optimization**: Custom queries use the relationship tables directly for complex operations
3. **Variations Impact**: Using many variations increases database load and should be monitored
4. **Filter Optimization**: The filter system uses caching and lazy loading to maintain performance