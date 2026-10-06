# Sermon Variations

Sermon Variations allow you to create different versions of the same sermon for multiple service types, each with their own speaker and media files.

## Understanding Sermon Variations

### What Are Sermon Variations?

Sermon variations track different instances of the same sermon message that may have been delivered:
- At different service types (e.g., Sunday morning, Sunday evening, youth service)
- With different speakers (e.g., campus pastors at different locations)
- With different media files

Rather than creating entirely separate sermon entries, variations maintain the relationship between these different instances of the same core message.

### Benefits of Using Variations

- **Organizational Clarity** — Keep your sermon library organized by linking related content
- **Multi-Campus Management** — Ideal for churches with multiple campuses or service types
- **Media Flexibility** — Attach different audio/video files to each variation
- **Content Reuse** — Share sermon content while customizing media and details

## Prerequisites

Variations require Service Types to be enabled. See [Service Types](service-types.md) for setup instructions.

### Enabling Variations

1. Navigate to Series → Settings → Advanced
2. Enable **Enable Service Types** in the Modules section
3. Save Changes
4. Navigate to Series → Settings → Messages tab
5. Select **Enable** for **Enable Variations (beta)**
6. Select the **Variation Source** (for example, Service Types)
7. Save Changes

### Creating Service Types

Before creating variations, set up your service types:

1. Navigate to Series → Service Types
2. Click "Add New"
3. Enter the service type name (e.g., "Sunday Morning," "Youth Service")
4. Add a description if desired
5. Click "Publish"

## Creating and Managing Sermon Variations

### Adding Variations to a Sermon

When variations are enabled in settings, the sermon editor includes a variation checkbox:

1. Edit a sermon
2. Check **Add Variations** in **Message Details**
3. Update the sermon. A group then appears for each service type (or other variation source).
4. In each group, set **Speaker** (when Speakers are enabled), **Date**, **Video URL**, and **Audio URL**
5. Update the sermon again

Variations are stored as child posts of the parent sermon, maintaining the relationship between different instances of the same message.

### Variation Content

Each variation can have unique components:

#### Media Files

Each variation can have its own:
- Audio file
- Video file

#### Speakers

Assign different speakers to variations, useful for:
- Multi-campus churches where different pastors deliver the same message
- Guest speaker series
- Team-teaching approaches

## Displaying Sermon Variations

### Frontend Display

When a sermon has variations, visitors can switch between them on the sermon page. The variation selector shows the available service types.

## Using Variations with Podcast Feeds

Sermon variations work with the podcast feed system. Taxonomy-scoped feeds (e.g., by series or speaker) include variation content as configured.

See [Podcast Setup](podcast-setup.md) for feed configuration details.

## Best Practices

### Organization Strategies

- **Consistent Naming** — Use clear, descriptive service type names
- **Primary Content** — Keep the main sermon content in the primary sermon entry
- **Media Management** — Use consistent file naming for variation media

### Technical Considerations

- **Storage Planning** — Each variation with unique media increases storage requirements
- **Template Compatibility** — Ensure your theme templates support variation display

### User Experience Guidelines

- **Clear Navigation** — Make it obvious how to switch between variations
- **Consistency** — Maintain consistent media quality across variations

## Troubleshooting Variations

### Common Issues

- **Variation Fields Not Showing** — Verify Service Types are enabled in Series → Settings → Advanced and **Enable Variations (beta)** is set to **Enable** in Series → Settings → Messages tab
- **Variations Not Displaying on Frontend** — Check template compatibility with your theme
- **Media Not Loading** — Verify media file paths are correct for each variation

### Enable Debug

For troubleshooting variation issues:

1. Navigate to Series → Settings → Advanced
2. Set **Enable Debug** to **Enable**
3. Check the log at Series → Tools → Log for variation-related errors
