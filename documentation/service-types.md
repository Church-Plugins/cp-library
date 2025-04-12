# Service Types Data Architecture

This document provides a comprehensive overview of the Service Types feature in CP Library, focusing on its data architecture, relationships, and implementation details.

## Overview

Service Types in CP Library allow churches to categorize sermons based on different service contexts (such as Sunday Morning, Youth Service, etc.). They serve as the foundation for the sermon variations system, enabling different versions of the same sermon for multiple service contexts.

## Database Structure

The Service Type functionality uses a hybrid data architecture combining WordPress posts with custom tables:

### Custom Tables

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

### WordPress Integration

- Each service type has a WordPress post with post type `cpl_service_type`
- The `origin_id` field in the custom table links to the WordPress post ID
- This integration provides both custom data storage and WordPress admin UI

## Model Implementation

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

## Data Relationships

### Relationship to Sermons (Items)

Service Types can be assigned to sermon items through a many-to-many relationship:

1. Each sermon item can be associated with multiple service types
2. Associations are stored in the `cp_source_meta` table
3. The relationship uses the key 'source_item' to connect items to service types
4. Items can query their service types using `get_service_types()`

### Service Types as Variation Sources

Service Types serve as the foundation for sermon variations:

1. When enabled, variations use service types to distinguish different versions
2. Each sermon can have multiple variations, each linked to a different service type
3. Variations track different speakers, media files, and timestamps
4. The relationship is managed through the `variation_source()`, `variation_source_items()`, `variation_item_source()`, and `variation_item_save()` methods

## Data Flow

The data flow for Service Types follows this pattern:

1. **Creation**:
   - WordPress post is created in the `wp_posts` table
   - Entry added to `cp_source` table with matching `origin_id`
   - Entry added to `cp_source_meta` with 'source_type' key

2. **Assignment to Sermons**:
   - Service types are selected via the sermon edit screen
   - Relationship stored in `cp_source_meta` with 'source_item' key
   - Data accessible via `Item::get_service_types()`

3. **Variations**:
   - When using service types for variations, each sermon can have multiple versions
   - Each variation is a separate post linked to the parent sermon
   - Service type ID stored in the variation's metadata

4. **Querying**:
   - Custom query handlers modify `WP_Query` to filter sermons by service type
   - This applies to both the admin interface and frontend displays

## Configuration and Settings

Service Types are configurable through several settings:

- **Enable/Disable**: Service Types feature can be toggled
- **Labels**: Singular/plural labels are customizable
- **Default Service Type**: A default service type can be specified
- **Variations**: Service Types can be enabled as variation sources
- **Display Options**: Controls how service types appear in templates and filters

## Best Practices for Implementation

When working with Service Types in custom code, follow these best practices:

1. **Retrieving Service Types**:
   ```php
   $service_types = \CP_Library\Models\ServiceType::get_all_service_types();
   ```

2. **Getting a Single Service Type**:
   ```php
   $service_type = new \CP_Library\Models\ServiceType($id);
   ```

3. **Getting Items for a Service Type**:
   ```php
   $service_type = new \CP_Library\Models\ServiceType($id);
   $items = $service_type->get_all_items();
   ```

4. **Getting Service Types for an Item**:
   ```php
   $item = new \CP_Library\Models\Item($item_id);
   $service_types = $item->get_service_types();
   ```

5. **Updating Service Types for an Item**:
   ```php
   $item = new \CP_Library\Models\Item($item_id);
   $item->update_service_types([$service_type_id]);
   ```

## Performance Considerations

When working with Service Types, consider these performance factors:

1. **Caching**: Service type relationships can be cached for better performance
2. **Query Optimization**: Custom queries should use the relationship tables directly for complex operations
3. **Variations Impact**: Using many variations increases database load and should be monitored

## Conclusion

The Service Type architecture in CP Library uses a hybrid approach combining WordPress posts with custom tables. This provides both the familiar WordPress admin UI and optimized data storage for complex relationships. The system is designed to be flexible, allowing churches to organize sermons across different service contexts while maintaining relationships between sermon variations.