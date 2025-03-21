# Sermon Variations

This guide explores the Sermon Variations feature in CP Sermon Library, which allows you to create different versions of the same sermon for multiple service types, locations, or contexts.

## Understanding Sermon Variations

### What Are Sermon Variations?

Sermon variations allow you to track different instances of the same sermon message that may have been delivered:
- At different service types (e.g., Sunday morning, Sunday evening, youth service)
- With different speakers (e.g., campus pastors at different locations)
- With slightly different content or emphases
- With different media files and timestamps

Rather than creating entirely new sermon entries, variations maintain the relationship between these different instances of the same core message.

### Benefits of Using Variations

- **Organizational Clarity**: Keep your sermon library organized by linking related content
- **Multi-Campus Management**: Ideal for churches with multiple campuses or service types
- **Media Flexibility**: Attach different audio/video files to each variation
- **Analytics Insights**: Compare engagement across different variations of the same message
- **Content Reuse**: Share sermon content while customizing media and details

## Setting Up Service Types

Before using variations, you need to set up Service Types:

### Enabling Service Types

1. Navigate to Library → Settings → General
2. Find the "Post Types" section
3. Enable the "Service Types" option
4. Save changes

### Creating Service Types

1. Navigate to Library → Service Types → Add New
2. Enter the service type name (e.g., "Sunday Morning," "Youth Service")
3. Add a description if desired
4. Set a featured image (optional)
5. Click "Publish"

Create as many service types as your church requires. Common examples include:
- Sunday Morning
- Sunday Evening
- Midweek Service
- Youth Service
- Children's Service
- Campus-specific services (Downtown Campus, North Campus, etc.)

## Creating and Managing Sermon Variations

### Creating Your First Variation

1. Create a primary sermon as you normally would:
   - Add title, content, series, speaker, etc.
   - Upload media files
   - Publish the sermon

2. After publishing, navigate to the "Variations" tab in the sermon editor

3. Click "Add Variation"

4. Configure the variation details:
   - Select a Service Type
   - Choose a Speaker (can be different from the main sermon)
   - Set the date and time
   - Add audio/video files specific to this variation
   - Add variation-specific timestamps

5. Click "Save Variation"

### Managing Multiple Variations

To view and manage all variations for a sermon:

1. Edit the sermon
2. Navigate to the "Variations" tab
3. You'll see a list of all existing variations
4. Use the action buttons to:
   - Edit a variation
   - Delete a variation
   - Add a new variation

### Bulk Creating Variations

If you regularly create variations, you can streamline the process:

1. Navigate to Library → Tools → Variations
2. Select the sermons you want to create variations for
3. Choose the service type
4. Specify common details (like speaker)
5. Click "Create Variations"

This will create variations for all selected sermons with the specified service type.

## Displaying Sermon Variations

### Frontend Display Options

Variations can be displayed in different ways on your site:

#### Variation Selector

The default template includes a variation selector when variations are available:

1. Navigate to Library → Settings → Item
2. Find the "Variation Display" section
3. Configure the display options:
   - Variation selector style (dropdown, tabs, buttons)
   - Default variation to display
   - Information to show in the selector

#### Accessing Specific Variations

Each variation has its own unique URL, which you can share directly:

- Main sermon: `/sermons/sermon-title/`
- Variation: `/sermons/sermon-title/?variation=service-type-slug`

### Shortcode Options

When using sermon shortcodes, you can specify variations:

```
[cpl_item id="123" variation="sunday-morning"]
```

This will display the specific variation of the sermon.

## Customizing Variation Content

Each variation can have unique components:

### Media Files

Each variation can have its own:
- Audio file
- Video file
- Downloadable resources

### Timestamps

Timestamps are specific to each variation, allowing for:
- Different section markers
- Service-specific moments
- Variation-specific highlights

### Speakers

Assign different speakers to variations, useful for:
- Multi-campus churches
- Guest speaker series
- Team-teaching approaches

## Using Variations with Podcast Feeds

Sermon variations work seamlessly with podcast feeds:

### Service-Specific Podcast Feeds

Create service-specific podcast feeds:

1. Navigate to Library → Settings → Podcast
2. Enable "Service Type Feeds"
3. Configure feed-specific settings
4. Access feeds at: `/feed/podcast/service-type/service-type-slug/`

### Selecting Variations for the Main Feed

Choose which variations appear in your main podcast feed:

1. Navigate to Library → Settings → Podcast
2. Find the "Variation Selection" setting
3. Choose an option:
   - Use primary sermon only
   - Use specific service type
   - Use most recent variation
   - Include all variations as separate episodes

## Best Practices for Sermon Variations

### Organization Strategies

- **Consistent Naming**: Use consistent service type names
- **Primary Content**: Keep the main sermon content in the primary sermon
- **Media Management**: Use consistent file naming for variation media
- **Asset Sharing**: Share graphics and resources across variations when appropriate

### Technical Considerations

- **Performance Impact**: Variations increase database queries; use caching if you have many variations
- **Storage Planning**: Each variation with unique media increases storage requirements
- **Template Compatibility**: Ensure your theme templates support variation display

### User Experience Guidelines

- **Clear Navigation**: Make it obvious how to switch between variations
- **Consistency**: Maintain consistent media quality across variations
- **Mobile Experience**: Test variation selectors on mobile devices

## Troubleshooting Variations

### Common Issues

- **Variations Tab Missing**: Verify Service Types are enabled
- **Variations Not Displaying**: Check template compatibility
- **Media Not Loading**: Verify unique media paths for each variation

### Debug Mode

For developers, enable debug mode to troubleshoot variation issues:

1. Add to wp-config.php: `define('CP_LIBRARY_DEBUG', true);`
2. Check the developer console for variation-related logging

## Advanced Variation Features (Pro)

The Pro version includes additional variation features:

### Analytics Comparison

Compare engagement metrics across variations:

1. Navigate to Library → Analytics
2. Select a sermon with variations
3. Use the comparison view to see metrics side by side

### Batch Processing

Process multiple variations at once:

1. Navigate to Library → Tools → Batch Processing
2. Select the "Variations" task
3. Choose the operation (generate timestamps, pull transcripts, etc.)
4. Select target variations
5. Run the batch process