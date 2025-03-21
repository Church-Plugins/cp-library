# CP Sermon Library Development Guide

This document provides key information about the CP Library WordPress plugin to help with development and Claude requests.

## Plugin Overview

CP Sermon Library is a WordPress plugin that provides functionality for managing and displaying church sermons, sermon series, speakers, and related media. It includes features for importing sermons, creating podcast feeds, analytics, and integration with various page builders.

## Development Environment Setup

### First-time Installation

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

## Plugin Structure

### Core Files

- `cp-library.php` - Main plugin file
- `includes/Constants.php` - Plugin constants
- `includes/Init.php` - Main initialization class

### Key Directories

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

### Post Types

- `cpl_item` - Sermons (main content type)
- `cpl_item_type` - Sermon Series
- `cpl_speaker` - Sermon Speakers
- `cpl_service_type` - Service Types
- `cpl_template` - Custom templates

### Taxonomies

- `cpl_scripture` - Bible scripture references
- `cpl_season` - Sermon seasons
- `cpl_topics` - Sermon topics

## Shortcodes

- `[cpl_item_list]` - Display a list of sermons
- `[cpl_item]` or `[cp-sermon]` - Display a single sermon
- `[cpl_item_widget]` - Display a sermon widget
- `[cpl_video_widget]` - Display a video widget
- `[cp-sermons]` - Display sermons archive
- `[cpl_template]` - Display a template
- `[cp-template]` - Display the current template

## Common CLI Commands

### WordPress Environment

```bash
# Activate the plugin
wp plugin activate cp-library

# Check plugin status
wp plugin list
```

### Development Commands

```bash
# Install dependencies
composer install
npm install

# Build assets
npm run build

# Create distribution package
npm run plugin-zip
```

## Common Configuration Settings

The plugin uses WordPress Settings API with these option groups:

- `cpl_item_options` - Sermon settings
- `cpl_item_type_options` - Series settings
- `cpl_advanced_options` - Advanced settings

## Integration Points

- **Page Builders**: Supports Beaver Builder, Divi, and Elementor
- **External Services**: SermonAudio, YouTube, OpenAI (for transcripts)
- **Other Plugins**: CP Locations, CP Resources, The Events Calendar, SearchWP

## Debugging

- Enable debug mode in Advanced settings or define `CP_LIBRARY_DEBUG` constant
- Logging functionality available through `$cp_library->logging`

## Code Patterns

- Singleton pattern used extensively (`get_instance()` method)
- Namespaced classes following PSR-4
- WordPress hooks and filters follow standard naming conventions

## Database Tables

Custom tables are used alongside WordPress tables:
- `cpl_items` - Main items table
- `cpl_item_meta` - Item metadata
- `cpl_item_types` - Item types

## Common Troubleshooting

- If shortcodes don't render, ensure the plugin is properly activated
- For template issues, check theme compatibility
- Import issues may require checking CSV format or external API connectivity

## Build Process Resources

- Uses webpack for bundling
- PostCSS for CSS processing
- React for frontend components
- Material UI for UI components

This document will be updated as the plugin evolves.