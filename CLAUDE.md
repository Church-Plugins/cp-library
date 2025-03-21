# CP Sermon Library Documentation

This document provides comprehensive information about the CP Library WordPress plugin to help users and developers.

## Plugin Overview

CP Sermon Library is a WordPress plugin that provides functionality for managing and displaying church sermons, sermon series, speakers, and related media. It includes features for importing sermons, creating podcast feeds, analytics, and integration with various page builders.

## User Features

### Core Functionality
- Sermon management with audio/video media
- Series organization
- Speaker profiles
- Custom taxonomies (Scripture, Topics, Seasons)
- Responsive design for all devices

### Advanced Features
- **Sermon Variations** - Create different versions of the same sermon for multiple service types/locations
  - Requires Service Types post type to be enabled
  - Track different speakers, media files and timestamps across variations
  - Configure via Settings → Messages → Variations

- **Transcripts** - Manage sermon transcripts
  - Import from YouTube automatically
  - OpenAI integration for transcript formatting (requires API key)
  - Configure display settings in sermon settings

- **Timestamps** - Add navigation points in sermon videos
  - Format: mm:ss or hh:mm:ss
  - Add via quick edit or sermon details
  - Allows listeners to jump to specific sermon points

- **Downloadable Resources** - Attach multiple files to sermons
  - Support for PDFs, presentation slides, notes, etc.
  - Custom naming for each downloadable item

### Content Display
- Multiple layout options (grid, list, vertical)
- Responsive design for all devices
- Customizable template system
- Gutenberg blocks for flexible content placement

## Configuration Options

### General Settings
- Post type label customization (rename sermons, series, speakers)
- Permalink structure configuration
- Image aspect ratio settings
- Button text customization

### Podcast Settings
- Feed title, subtitle, author configuration
- iTunes category mapping
- Cover image upload (with size requirements)
- Episode limit controls
- Custom feed URLs (available at site.com/podcast)
- Taxonomy-specific podcast feeds

### Template Customization
- Control of "info items" and "meta items" displayed with sermons
- Single page template options (vertical vs. default layout)
- Image aspect ratio settings for series
- Custom CSS options

### Filter Display
- Control filter sorting (by sermon count, alphabetically, etc.)
- Filter count thresholds for showing taxonomy terms
- Option to show/hide count numbers in filters
- Mobile-friendly filter options

## Common Workflows

### Sermon Management
1. Create speakers (optional but recommended)
2. Create series (optional but recommended)
3. Add sermons with media files
4. Organize using taxonomies (Scripture, Topics, Seasons)
5. Configure display options and templates

### Speaker Management
- Create speaker profiles with bios and images
- Enable/disable speaker permalinks
- View speaker-specific sermon archives
- Manage speaker order in filtering system

### Series Organization
- Control sermons per series display count
- Configure sort order for sermons within series
- Series-specific image settings
- Manage series archives and displays

### Service Types & Variations
- Enable Service Types post type in settings
- Create service types (e.g., Sunday Morning, Youth Service)
- Create sermon variations linked to service types
- Track service-specific media files and speakers

### Analytics
- Built-in tracking of sermon views and interactions
- Analytics dashboard in admin area
- Filter by date ranges and content types
- Export options for detailed reporting

## Integrations

### Page Builders
- **Beaver Builder** - Custom modules for sermon display
- **Divi** - Custom modules and layouts
- **Elementor** - Widget integration

### External Services
- **SermonAudio** - Import/export sermon content
  - API key configuration
  - Mapping options for data synchronization
  - Batch import capabilities

- **YouTube** - Video integration and transcript import
  - Automatic transcript import from videos
  - Channel connection for simplified imports

- **OpenAI** - Transcript formatting and generation
  - API key required in settings
  - Quality controls for transcript formatting

### Other Plugins
- CP Locations - Link sermons to physical locations
- CP Resources - Associate resources with sermons
- The Events Calendar - Connect sermons with events
- SearchWP - Enhanced sermon search capabilities

## Shortcodes

- `[cpl_item_list]` - Display a list of sermons
- `[cpl_item]` or `[cp-sermon]` - Display a single sermon
- `[cpl_item_widget]` - Display a sermon widget
- `[cpl_video_widget]` - Display a video widget
- `[cp-sermons]` - Display sermons archive
- `[cpl_template]` - Display a template
- `[cp-template]` - Display the current template

### Shortcode Parameters
```
[cpl_item_list 
  count="10" 
  columns="3" 
  template="grid" 
  series="series-slug" 
  speaker="speaker-slug" 
  topic="topic-slug"
  scripture="john-3"
  season="season-slug"
  pagination="true"
]
```

## Best Practices

### Content Organization
- Use series for thematic grouping of sermons
- Apply consistent taxonomy terms (Topics, Scripture, Seasons)
- Consider URL structure when setting up permalinks
- Maintain consistent image aspect ratios

### Media Management
- Use direct file uploads for self-hosting
- Support for URLs and embed codes from external services
- Recommended audio formats: MP3
- Recommended video formats: MP4, or embed from YouTube/Vimeo

### Performance Optimization
- Enable caching for variations and complex queries
- Use debug mode for troubleshooting (Advanced settings)
- Consider image optimization for cover images
- Monitor analytics for popular content to feature

### URL Structure Planning
- Consider SEO when configuring post type and taxonomy slugs
- Plan permalink structure before adding significant content
- Note: changing URLs after publishing can impact SEO

## Troubleshooting

- If shortcodes don't render, ensure the plugin is properly activated
- For template issues, check theme compatibility
- Import issues may require checking CSV format or external API connectivity
- Enable debug mode in Advanced settings for detailed logging
- Use built-in logging functionality through `$cp_library->logging`

## Development Information

### Environment Setup

1. Clone the repository to `wp-content/plugins/cp-library/`
2. Run these commands:
```bash
composer install
npm install
cd app
npm install
npm run build
```

### Build Commands

- `npm run start` - Start development mode (watches for changes)
- `npm run build` - Build production assets
- `npm run plugin-zip` - Create a distribution zip file
- `npm run build:src` - Build only the src directory
- `npm run build:blocks` - Build only the blocks directory

### Important Development Notes

- Changes to React components require running `npm run build` (no automatic watcher in WordPress context)
- The plugin uses webpack for asset bundling
- SASS is used for styling

### Plugin Structure

#### Core Files
- `cp-library.php` - Main plugin file
- `includes/Constants.php` - Plugin constants
- `includes/Init.php` - Main initialization class

#### Key Directories
- `/includes` - Core PHP classes
  - `/Admin` - Admin-related functionality
  - `/API` - REST API endpoints
  - `/Adapters` - Integration adapters (SermonAudio, etc.)
  - `/Controllers` - Main business logic
  - `/Models` - Data models
  - `/Setup` - Registration of post types, taxonomies, etc.
  - `/Views` - Template views
  - `/ChurchPlugins` - Shared library code
- `/assets` - Static assets (images, etc.)
- `/blocks` - WordPress Gutenberg blocks
- `/src` - React components and main JavaScript
- `/templates` - Frontend templates
- `/pro` - Pro version features (if installed)

#### Post Types
- `cpl_item` - Sermons (main content type)
- `cpl_item_type` - Sermon Series
- `cpl_speaker` - Sermon Speakers
- `cpl_service_type` - Service Types
- `cpl_template` - Custom templates

#### Taxonomies
- `cpl_scripture` - Bible scripture references
- `cpl_season` - Sermon seasons
- `cpl_topics` - Sermon topics

### Common CLI Commands

#### WordPress Environment

```bash
# Activate the plugin
wp plugin activate cp-library

# Check plugin status
wp plugin list
```

#### Development Commands

```bash
# Install dependencies
composer install
npm install

# Build assets
npm run build

# Create distribution package
npm run plugin-zip
```

### Common Configuration Settings

The plugin uses WordPress Settings API with these option groups:

- `cpl_item_options` - Sermon settings
- `cpl_item_type_options` - Series settings
- `cpl_advanced_options` - Advanced settings

### Database Tables

Custom tables are used alongside WordPress tables:
- `cpl_items` - Main items table
- `cpl_item_meta` - Item metadata
- `cpl_item_types` - Item types

### Code Patterns

- Singleton pattern used extensively (`get_instance()` method)
- Namespaced classes following PSR-4
- WordPress hooks and filters follow standard naming conventions

### Build Process Resources

- Uses webpack for bundling
- PostCSS for CSS processing
- React for frontend components
- Material UI for UI components

This document will be updated as the plugin evolves.