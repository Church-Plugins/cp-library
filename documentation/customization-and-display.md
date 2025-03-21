# Customization & Display Options

## Changing Permalinks & Post Names

You can customize how the sermon URLs and labels appear on your site:

1. Navigate to Library → Settings → General
2. Change the "Item Slug" field to customize the URL structure (e.g., "podcasts" instead of "sermons")
3. Customize the singular and plural labels for the content types
4. Save changes

After changing permalinks, you may need to flush your site's rewrite rules by going to Settings → Permalinks and clicking "Save Changes".

## Using Gutenberg Blocks for Sermons & Series

CP Sermon Library includes custom blocks for the WordPress block editor:

### Sermon Content Blocks

- **Item Title** - Display the sermon title
- **Item Date** - Show the sermon date
- **Item Description** - Display the sermon description
- **Item Graphic** - Show the sermon/series image
- **Sermon Scripture** - Display scripture references
- **Sermon Series** - Show the sermon's series
- **Sermon Speaker** - Display the sermon speaker
- **Sermon Topics** - Show the sermon topics
- **Sermon Actions** - Display sermon action buttons (download, share, etc.)

### Sermon Query Blocks

- **Query** - Create custom sermon lists with filtering options
- **Pagination** - Add pagination to sermon lists
- **Sermon Template** - Control the sermon display template

To use these blocks:

1. Create or edit a page in the WordPress block editor
2. Click the "+" button to add a block
3. Search for "sermon" to see all available sermon blocks
4. Select the desired block and configure its settings

## Customizing Sermon Layouts & Templates

### Using Built-in Templates

CP Sermon Library includes several built-in templates for displaying sermons:

1. **Default** - Standard sermon display
2. **Grid** - Display sermons in a grid layout
3. **List** - Show sermons in a list format
4. **Vertical** - Vertical card-style display

To change the default template:

1. Navigate to Library → Settings → Item
2. Select your preferred default template
3. Save changes

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
- `series` - Filter by series ID
- `speaker` - Filter by speaker ID
- `template` - Specify template style
- `order` - Sort order (ASC or DESC)
- `orderby` - Sort field (date, title, etc.)

Example shortcode usage:
```
[cpl_item_list count="4" columns="2" series="12" template="grid"]
```

## Theme Compatibility & Styling Options

### Basic Styling Options

CP Sermon Library integrates with your theme's styles while providing some basic customization options:

1. Navigate to Library → Settings → Item
2. Adjust color settings for the sermon player
3. Configure basic layout options
4. Save changes

### Advanced Styling

For advanced styling, you can use:

1. **Custom CSS** - Add custom CSS to your theme to style sermon elements
2. **Theme Templates** - Override plugin templates in your theme
3. **Block Editor** - Use the block editor's style options for sermon blocks

### Template Overrides

You can override the plugin's templates in your theme by creating properly named template files:

1. Create a `/cp-library/` directory in your theme
2. Copy template files from the plugin's `/templates/` directory to your theme's `/cp-library/` directory
3. Modify the copied templates as needed

The plugin will automatically use your theme's template files instead of the plugin defaults.