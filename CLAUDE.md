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

### Important Development Notes

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
- `cpl_topic` - Sermon topics

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

#### Testing

`vendor/` is not in git — run `composer install` after cloning.

```bash
composer test              # both suites
composer test:unit         # fast, WP-free (Brain Monkey for WP stubs)
composer test:integration   # boots real WordPress against a real database
composer verify            # everything that must pass before a commit
```

The unit suite does not boot WordPress. Tests exercise logic directly and stub WP
functions with Brain Monkey, which keeps that run well under a second.

New PHP logic should ship with a test in `tests/Unit/`. See `DiffAssociationsTest`
for the pure-logic pattern and `UnresolvedSpeakerGuardTest` for the Brain Monkey
one (subclass the unit, build it with `newInstanceWithoutConstructor()`, stub the
WP calls). Name the test after the behavior it protects and open the file with a
docblock explaining the bug it locks down — not what the method does.

**The unit suite cannot reach `$wpdb`.** Much of the relationship logic is raw
SQL against the `cpl_*` custom tables, so pull the decision out of the query and
unit test the decision — `Models\Item::diff_associations()` is the shape to copy.
Then cover the query itself in `tests/Integration/`.

Pure WP helpers (`absint`, `trailingslashit`, ...) are defined for real in
`tests/wp-polyfills.php`. Anything with behavior — `get_option`, `apply_filters`,
`get_post_meta` — gets stubbed per test so the test states its own assumptions.

##### Integration tests

One-time setup per machine:

```bash
bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-db-create]
```

**The WordPress test suite drops every table in the database it is given, on
every run.** Point it at a dedicated database — never at the one behind a site
you care about. On Local, MySQL only accepts socket connections, so pass the host
as `localhost:/path/to/mysqld.sock` and create the database yourself first
(the script's own creation step can't handle a socket path containing spaces):

```bash
SOCK="$HOME/Library/Application Support/Local/run/<site-id>/mysql/mysqld.sock"
mysql --socket="$SOCK" -uroot -proot -e "CREATE DATABASE cpl_tests"
bin/install-wp-tests.sh cpl_tests root root "localhost:$SOCK" latest true
```

Tests extend `CP_Library\Tests\Integration\TestCase`, which adds fixtures for the
custom-table models. `WP_UnitTestCase` wraps each test in a transaction that
covers the `cpl_*` tables too, so fixtures need no teardown — but never issue DDL
from inside a test, as the implicit commit breaks that rollback.

When a test is meant to lock down a fix, confirm it actually fails against the
old behavior before trusting it. Both `SurplusAssociationDeleteTest` and
`Migration163Test` were checked that way.

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

## Documentation Sync System

This plugin uses an automated documentation sync system that publishes markdown files to https://docs.churchplugins.com

### Documentation Structure

```
cp-library/
├── docs/                    # SYNCED to WordPress
│   ├── config.json         # Plugin metadata & category mapping
│   ├── README.md           # Documentation guide (not synced)
│   ├── .image-cache.json   # Image upload cache (auto-generated)
│   ├── assets/             # Images (auto-uploaded to WP)
│   └── *.md                # Documentation articles (synced)
└── documentation/          # LEGACY docs (for reference only)
```

### Quick Reference

**Sync all documentation:**
```bash
php sync-docs.php --plugin=cp-library
```

**Preview changes (dry run):**
```bash
php sync-docs.php --plugin=cp-library --dry-run
```

**Add new documentation:**
1. Create markdown file in `docs/` folder
2. Add YAML frontmatter (title, slug, status)
3. Place images in `docs/assets/`
4. Run sync script

### Important Notes

- Documentation syncs to WordPress category: "CP Sermons" (ID: 7)
- Articles matched by `slug` field in frontmatter
- Images automatically upload to WordPress Media Library
- See `/DOCS-SYNC-GUIDE.md` for complete documentation

This document will be updated as the plugin evolves.
