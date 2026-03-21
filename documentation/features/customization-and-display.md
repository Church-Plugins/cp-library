# Customization and Display

This guide covers the various ways to display and customize sermons, series, and other content in CP Sermon Library.

## Display Options for Sermons

### Archive Pages

CP Sermon Library automatically creates archive pages for:

- Sermons - All sermons in your library
- Series - All sermon series
- Speakers - All sermon speakers
- Topics - Sermons by topic
- Scripture - Sermons by Bible reference
- Seasons - Sermons by seasons

These archives are accessible at URLs like:
- /messages/ (or your custom slug)
- /series/
- /speakers/
- /topic/faith/
- /scripture/john-3/
- /season/summer-2023/

### Layout Options

The plugin includes multiple layout options for displaying sermons:

1. **Grid Layout**
   - Displays sermons in a responsive grid
   - Shows featured images prominently
   - Great for visual impact

2. **List Layout**
   - Displays sermons in a vertical list
   - Shows more metadata for each sermon
   - Ideal for chronological browsing

3. **Vertical Layout**
   - A hybrid layout with larger images
   - Shows more details than grid, less than list
   - Good balance of visual appeal and information

### Template Customization

Templates can be customized in several ways:

1. **Settings**
   - Navigate to Messages → Settings → Messages tab
   - Choose Single Page Template (default or vertical)
   - Set Image Aspect Ratio for thumbnails
   - Configure Info Items and Meta Items display

2. **Theme Overrides**
   - Copy template files from the plugin's `/templates/` directory
   - Place them in `/wp-content/themes/your-theme/cp-library/`
   - Your customizations are preserved through plugin updates

3. **Custom CSS**
   - Add custom CSS via your theme or WordPress Customizer
   - Target specific elements with CSS selectors

## Sermon Block Options

CP Sermon Library includes Gutenberg blocks for displaying sermon content:

### Core Blocks

- **Sermon Query** - Display sermons with configurable filters, layout, and pagination
- **Sermon Template** - Display a sermon using a custom template
- **Shortcode Template** - Embed shortcode-based sermon displays within block layouts

Individual sermon detail blocks are also available for use within templates:
- **Sermon Actions** (play buttons), **Sermon Graphic** (thumbnail), **Sermon Title**, **Sermon Date**, **Sermon Description**, **Sermon Speaker**, **Sermon Series**, **Sermon Topics**, **Sermon Scripture**

### Block Customization

Each block includes customization options:

1. **Content Selection**
   - Filter by series, speaker, topics, etc.
   - Set number of items to display
   - Control sorting order

2. **Layout Options**
   - Choose grid or list layout
   - Set number of columns
   - Adjust image size and aspect ratio

3. **Style Options**
   - Customize colors and typography
   - Control spacing and padding
   - Show/hide specific elements

### Block Patterns

CP Sermon Library includes pre-built block patterns you can insert from the Gutenberg pattern inserter:

- **Latest Sermon** — Displays the most recent sermon
- **Latest Series** — Displays the most recent series
- **Latest Both** — Displays both the latest sermon and latest series
- **Sermon Grid** — Grid layout for sermons
- **Sermon List** — List layout for sermons
- **Series Grid** — Grid layout for series
- **Series List** — List layout for series

To use a pattern, click the "+" inserter in the block editor, switch to the "Patterns" tab, and look under the "Sermons" or "Series" categories.

## Sermon Templates (Custom Layouts)

CP Sermon Library includes a **Templates** post type that lets you build custom sermon layouts using the block editor.

### Creating a Template

1. Navigate to Messages → Templates in the admin
2. Click "Add New"
3. Build your layout using CP Sermon Library blocks (Sermon Query, Sermon Actions, Sermon Graphic, Sermon Title, etc.)
4. Publish the template

When editing a template, only CP Library blocks are available in the block inserter, ensuring your layout uses the correct sermon components.

### Using Templates

Once you've created a template, you can use it in several ways:

- **Shortcode** — Each template displays a `[cpl_template id="123"]` shortcode in a sidebar metabox. Copy this shortcode and paste it into any page or post.
- **Page Builders** — The Beaver Builder, Divi, and Elementor modules each provide a "CP Sermons Template" module that lets you select and embed any template you've created (see [Page Builder Integration](../advanced/integrations.md#page-builder-integration)).
- **Shortcode Template Block** — Use the "Shortcode Template" Gutenberg block to embed a template within other block layouts.

## Using Shortcodes for Custom Displays

CP Sermon Library provides several shortcodes for displaying sermon content:

### Core Shortcodes

- `[cpl_item_list]` - Display a list of sermons
- `[cpl_item]` or `[cp-sermon]` - Display a single sermon
- `[cpl_item_widget]` - Display a sermon widget
- `[cpl_video_widget]` - Display a video widget
- `[cp-sermons]` - Display the sermons archive
- `[cpl_player]` - Display the sermon player
- `[cpl_template id="123"]` - Display a sermon template (see Sermon Templates above)

### Shortcode Parameters

Common parameters for `[cpl_item_list]`:

- `id` - Specify a sermon ID
- `count` - Number of sermons to display
- `columns` - Number of columns for grid layouts
- `series` - Filter by series slug
- `speaker` - Filter by speaker slug
- `topic` - Filter by topic slug
- `scripture` - Filter by scripture reference
- `season` - Filter by season slug
- `pagination` - Show pagination (true/false)

For `[cpl_item]` / `[cp-sermon]`:

- `id` - Specify the sermon ID to display
- `template` - Use `alt` for the alternate widget layout (default: standard layout)

Example shortcode with multiple parameters:
```
[cpl_item_list count="10" columns="3" series="easter-2023" pagination="true"]
```

## Series Display Options

Series can be displayed in several ways:

### Series Archive

The series archive displays all series, sorted by date:
- Grid layout shows series thumbnails
- Clicking a series shows all sermons in that series

### Series Grid/List Block

The Series Grid/List block allows you to:
- Display selected series in grid or list format
- Filter series by specific criteria
- Customize the appearance of series items

### Series Single View

When viewing a single series:
- Shows the series description
- Lists all sermons in the series
- Displays series artwork prominently

## Controlling Content Visibility

### Sermon Visibility Control

You can control which sermons appear in the main sermon lists:

1. Edit a sermon
2. Find the "Visibility Settings" panel
3. Use the "Show in Main List" checkbox
4. Sermons hidden from the main list will still be accessible via their direct URL

### Series Visibility Control

Series can also be hidden from the main series list:

1. Edit a series
2. Find the "Visibility Settings" panel 
3. Use the "Exclude from Main List" checkbox
4. Hidden series will not appear in the main series list but can still be accessed directly

### Service Type Visibility Control

Sermons with specific service types can be excluded from main lists:

1. Edit a service type
2. Find the "Visibility Settings" panel
3. Use the "Exclude from Main List" checkbox
4. All sermons with this service type will be hidden from the main sermon list

### Visibility Inheritance

Sermon visibility can be inherited from parent entities:
- If a series is hidden, all sermons in that series inherit that setting
- If a service type is hidden, all sermons with that service type inherit that setting
- Sermons with inherited visibility will show a notice explaining why they're hidden

## Filter Display Options

Control how filters appear on your sermon pages:

### Filter Settings

Navigate to Messages → Settings → Advanced to adjust:
- Filter sorting (by count or alphabetically)
- Minimum count threshold for filters
- Show/hide count numbers
- Disable specific filters
- Control filter display on mobile

### Customizing Filter Labels

Customize the labels used in filters:
1. Navigate to Messages → Settings (Speaker tab, or Advanced tab for other post types)
2. Adjust the Singular and Plural Labels
3. These changes will be reflected in the filter UI

### Filter Contexts

Filters are now context-aware and work in multiple locations:
- Main sermon archive
- Service Type pages
- Custom templates

For detailed documentation on the filter system, see [Filter System Documentation](../developers/filter-system.md).

