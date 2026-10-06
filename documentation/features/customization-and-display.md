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
- /topics/faith/
- /scripture/john-3/
- /seasons/summer-2023/

### Layout Options

Sermon and series archives render as a list.

On a page, the **CP Sermons Sermons/Series** block toolbar has **List view** and **Grid view**. **Grid view** shows a **Columns** control.

**Vertical (1 column)** is only the **Single Page Template** option on Series → Settings → Messages. It changes the single sermon page, not the archive.

### Template Customization

Templates can be customized in several ways:

1. **Settings**
   - Navigate to Series → Settings → Messages tab
   - Choose **Single Page Template** (**Default (2 column)** or **Vertical (1 column)**)
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

- **CP Sermons Sermons/Series** — List sermons or series
- **Sermon Template** — The inner template for items inside that query
- **CP Sermons Template** — Embed a template you built under Series → Templates

Blocks you place inside a template:

- **Sermon Actions**, **Item Graphic**, **Item Title**, **Item Date**, **Item Description**, **Sermon Speaker**, **Sermon Series**, **Sermon Topics**, **Sermon Scripture**

### Block Customization

On **CP Sermons Sermons/Series**, open **Settings**:

1. Set **Type** to **Sermons** or **Series**.
2. Set **Items to show**.
3. Turn on **Single Item** to pick one sermon or series.
4. For sermons that are not a single item, **Show Upcoming** is available.
5. Set **Order by** to **Newest to oldest**, **Oldest to newest**, **A → Z**, or **Z → A**.
6. Use the toolbar **List view** or **Grid view**. In **Grid view**, set **Columns**.

The **Filters** panel can limit the query with **Taxonomies**, **Authors**, and **Parents**.

**Item Graphic** has an **Aspect ratio** setting. Spacing controls depend on the block.

### Block Patterns

CP Sermon Library includes pre-built block patterns you can insert from the Gutenberg pattern inserter:

- **Latest Sermon** — Most recent sermon (category **Messages**)
- **Latest Series** — Most recent series (category **Series**)
- **Latest Sermon + Series** — Both
- **Latest Sermons - Grid View**
- **Latest Sermons - List View**
- **Latest Series - Grid View**
- **Latest Series - List View**

To use a pattern, click the "+" inserter in the block editor, switch to the **Patterns** tab, and look under **Messages** or **Series**. Those category names follow your plural labels.

## Sermon Templates (Custom Layouts)

CP Sermon Library includes a **Templates** post type that lets you build custom sermon layouts using the block editor.

### Creating a Template

1. Navigate to Series → Templates in the admin
2. Click "Add New"
3. Build your layout using CP Sermon Library blocks (CP Sermons Sermons/Series, Sermon Actions, Item Graphic, Item Title, Item Date, Item Description, Sermon Season, etc.)
4. Publish the template

When editing a template, the inserter allows CP Sermons blocks plus core blocks such as **Paragraph**, **Heading**, **Group**, **Columns**, and **Spacer**.

### Using Templates

Once you've created a template, you can use it in several ways:

- **Shortcode** — Each template shows `[cpl_template id=123]` in a sidebar metabox (the id is not quoted). Copy it into any page or post. `[cpl_template id="123"]` also works.
- **Page Builders** — The Beaver Builder, Divi, and Elementor modules each provide a "CP Sermons Template" module that lets you select and embed any template you've created (see [Page Builder Integration](../advanced/integrations.md#page-builder-integration)).
- **CP Sermons Template** block — Insert this block and choose a template.

## Using Shortcodes for Custom Displays

CP Sermon Library provides several shortcodes for displaying sermon content:

### Core Shortcodes

- `[cpl_item]` or `[cp-sermon]` - Display a single sermon
- `[cpl_item_widget]` - Display a sermon widget
- `[cpl_video_widget]` - Display a video widget
- `[cp-sermons]` - Display the sermons archive
- `[cpl_template id="123"]` - Display a sermon template (see Sermon Templates above)

### Shortcode Parameters

`[cpl_item]` and `[cp-sermon]` accept:

- `id` — Sermon post ID. Omit it to show the latest sermon.
- `player` — `true` or `false` (default `true`)
- `details` — `true` or `false` (default `true`)
- `location` — Location id, when CP Locations is in use
- `service-type` — Service type post ID. Used when `id` is omitted, to pick the latest sermon for that service type.
- `template` — `alt` for the alternate widget layout. Any other value uses the standard layout.

Example:

```
[cpl_item id="123" template="alt"]
```

`[cp-sermons]` prints the sermon archive and takes no attributes. `[cpl_template id="123"]` prints a template. `[cpl_item_widget]` and `[cpl_video_widget]` print the latest audio or video sermon and take no attributes.

## Series Display Options

Series can be displayed in several ways:

### Series Archive

The series archive is a list, ordered by the date of the latest sermon in each series. Open a series to see its description, artwork, and sermons.

### Series in the Query Block

Insert **CP Sermons Sermons/Series**, set **Type** to **Series**, then choose **List view** or **Grid view**. Use **Items to show**, **Order by**, and the **Filters** panel the same way you do for sermons.

### Series Single View

When viewing a single series:
- Shows the series description
- Lists all sermons in the series
- Displays series artwork prominently

## Controlling Content Visibility

### Sermon Visibility Control

You can control which sermons appear in the main sermon list:

1. Edit a sermon
2. Find the "Visibility Settings" panel
3. Check the **Exclude from Main List** box to remove this sermon from the main list (leave unchecked to keep it visible)
4. Sermons hidden from the main list are still accessible via their direct URL, taxonomy archives, and search

> **Changed in 1.6.2:** This checkbox was previously labeled "Show in Main List" and defaulted to checked. The default is now "visible" without an explicit setting, so imports and other programmatic saves no longer hide sermons by accident. If you upgraded from an earlier 1.6.x release, run **Series → Tools → Migrate Visibility Settings** to carry forward any sermons you previously hid by hand.

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

Navigate to Series → Settings → Advanced to adjust:
- **Sort Topics**, **Sort Scripture**, **Sort Seasons**, and **Sort Speakers**: **By Message Count** or **Alphabetically** (Scripture uses **By Scripture Order**)
- **Count Threshold** (default 3)
- **Show Counts**: **Show** or **Hide**

**Disable Filters** on the Messages tab and the Series tab hides facets on those archives. The **Disable Filters** field on the Advanced tab is marked deprecated in its description.

### Filter button labels

Sermon archive: **Topic**, **Scripture**, **Season**, **Speaker**, **Service Type**, **Year**. Series archive: **Season**, **Year**, **Number of Sermons**. **Singular Label** and **Plural Label** do not change that text.

### Filter Contexts

Filters are now context-aware and work in multiple locations:
- Main sermon archive
- Service Type pages
- Custom templates

For detailed documentation on the filter system, see [Filter System Documentation](../developers/filter-system.md).

