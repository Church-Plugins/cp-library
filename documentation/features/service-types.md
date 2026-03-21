# Service Types

Service Types allow you to categorize sermons by the type of service they were delivered in (e.g., Sunday Morning, Youth Service, etc.). This feature is particularly useful for churches that hold multiple services with different formats.

## Enabling Service Types

Service Types are disabled by default. To enable them:

1. Navigate to Messages → Settings → Advanced
2. Find the "Modules" section
3. Enable "Service Types"
4. Save changes

## Creating Service Types

Once enabled, you can create service types:

1. Navigate to Messages → Service Types
2. Click "Add New"
3. Enter a name for the service type (e.g., "Sunday Morning")
4. Add a description if desired
5. Upload a featured image (optional)
6. Click "Publish"

## Assigning Service Types to Sermons

To assign a service type to a sermon:

1. Edit a sermon
2. Find the "Service Type" box in the sidebar
3. Select one or more service types
4. Update the sermon

## Service Type Archives

Each service type has its own archive page that displays all sermons for that service type. The URL format is:

```
/messages/service-type/[service-type-slug]/
```

## Filtering by Service Type

The sermon archive includes a Service Type filter that allows visitors to filter sermons by service type.

## Service Type Templates

Service Type archives use a dedicated template that:

1. Displays the service type title and description
2. Shows all sermons assigned to that service type
3. Provides filtering options specific to that service type's sermons
4. Excludes the Service Type filter (since you're already viewing by service type)

## Visibility Control

Service Types include visibility control options:

1. Edit a service type
2. Find the "Visibility Settings" meta box
3. Check "Exclude from Main List" to hide sermons with this service type from the main sermon list
4. Save changes

Sermons with hidden service types will:
- Not appear in the main sermon list
- Still appear in the service type's own archive
- Be accessible via direct links
- Appear in series and other taxonomy archives

## Advanced Filter Controls

When viewing a Service Type archive, the filter system automatically:

1. Only shows filter options relevant to sermons in that service type
2. Disables the Service Type filter
3. Applies any selected filters only to the current service type's sermons

For more details on the filter system, see the [Filter System Documentation](../developers/filter-system.md).

## Using Service Types with Variations

Service Types serve as the foundation for [sermon variations](sermon-variations.md). When variations are enabled:

1. Each sermon can have multiple variations, each linked to a different service type
2. Variations track different speakers, media files, and timestamps
3. Service-specific podcast feeds can be created

## Best Practices

- **Consistent Naming** — Use clear, descriptive names (e.g., "Sunday Morning" not "SM")
- **Visibility Planning** — Decide upfront which service types should appear in the main sermon list
- **Limit Service Types** — Only create service types you actively use to keep the library organized
