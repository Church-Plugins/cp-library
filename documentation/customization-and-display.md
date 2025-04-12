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
- /sermons/
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

1. **Global Template Settings**
   - Navigate to Library → Settings → Templates
   - Adjust default settings for all sermon displays

2. **Per-Instance Settings**
   - When using blocks or shortcodes, customize each instance
   - Override defaults for specific sections of your site

3. **Custom CSS**
   - Use the custom CSS option in Settings → Templates
   - Target specific elements with CSS selectors

## Sermon Block Options

CP Sermon Library includes Gutenberg blocks for displaying sermon content:

### Core Blocks

- **Sermon Grid/List** - Display multiple sermons in grid or list format
- **Series Grid/List** - Display series in grid or list format
- **Latest Sermon** - Display the most recent sermon
- **Latest Series** - Display the most recent series
- **Sermon Template** - Display a sermon using a custom template

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

### Creating Custom Templates

You can create custom templates for displaying sermons:

1. Navigate to Library → Templates → Add New
2. Give your template a name
3. Use the block editor to design your template with sermon blocks
4. Publish your template
5. Use the template in shortcodes or select it as the default template

## Using the Shortcode Builder for Custom Displays

CP Sermon Library provides several shortcodes for displaying sermon content:

### Core Shortcodes

- `[cpl_item_list]` - Display a list of sermons
- `[cpl_item]` or `[cp-sermon]` - Display a single sermon
- `[cpl_item_widget]` - Display a sermon widget
- `[cpl_video_widget]` - Display a video widget
- `[cp-sermons]` - Display the sermons archive
- `[cpl_template]` - Display a specific template

### Shortcode Parameters

Common parameters for sermon shortcodes include:

- `id` - Specify a sermon ID
- `count` - Number of sermons to display
- `columns` - Number of columns for grid layouts
- `template` - Template to use (grid, list, vertical)
- `series` - Filter by series slug
- `speaker` - Filter by speaker slug
- `topic` - Filter by topic slug
- `scripture` - Filter by scripture reference
- `season` - Filter by season slug
- `pagination` - Show pagination (true/false)

Example shortcode with multiple parameters:
```
[cpl_item_list count="10" columns="3" template="grid" series="easter-2023" pagination="true"]
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

Navigate to Library → Settings → Advanced to adjust:
- Filter sorting (by count or alphabetically)
- Minimum count threshold for filters
- Show/hide count numbers
- Control filter display on mobile

### Customizing Filter Labels

Customize the labels used in filters:
1. Navigate to Library → Settings → Post Types
2. Adjust the labels for Series, Speaker, Topics, etc.
3. These changes will be reflected in the filter UI

## Widget Areas

CP Sermon Library adds widget areas you can use with any theme:

- Sermon Single - Above content
- Sermon Single - Below content
- Sermon Archive - Above content
- Sermon Archive - Below content

To use these widget areas:
1. Navigate to Appearance → Widgets
2. Add widgets to the sermon widget areas
3. Custom content will appear in the specified locations