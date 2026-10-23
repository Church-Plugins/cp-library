# Installation & Setup

This guide covers installing CP Sermon Library, activating your license, and configuring essential settings.

## System Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## Installing the Plugin

### From WordPress Dashboard

1. Log in to your WordPress dashboard
2. Navigate to Plugins → Add New
3. Search for "CP Sermon Library"
4. Click "Install Now" and then "Activate"

### Manual Installation

1. Download the plugin .zip file from your Church Plugins account
2. Log in to your WordPress dashboard
3. Navigate to Plugins → Add New → Upload Plugin
4. Choose the downloaded .zip file and click "Install Now"
5. After installation completes, click "Activate Plugin"

### Verify Installation

After activation:

1. Check that a new **Series** menu appears in your WordPress dashboard (this is the default top-level menu)
2. Navigate to Series → Settings to confirm all options are accessible

> **Note:** **Set default menu item** defaults to Series. Settings and Tools are under that menu. If you set it to Messages, or you rename a label, the menu name changes to match.

## Activating Your License

To receive updates and support, activate your license:

1. Navigate to Series → Settings → License tab
2. Enter your license key in **License Key** (found in your Church Plugins account)
3. Click **Save Changes**
4. Click **Activate License**
5. A green check mark shows when the license is active

## Initial Configuration

### Content Labels

You can customize the terminology used throughout the plugin:

1. Navigate to Series → Settings
2. Select the tab for the content type you want to rename (Messages, Series, or Speaker)
3. Change **Singular Label** and **Plural Label**. On the Messages and Series tabs, also change **Slug**. The Speaker tab has no **Slug** field.
4. Save changes. The top-level menu uses the plural label of the post type selected in **Set default menu item** (Series by default).

### Sermon Settings

1. Navigate to Series → Settings → Messages tab (or your custom label)
2. Configure display options (single page template, image aspect ratio)
3. Set info items and meta items to control what displays with each sermon

### Podcast Settings (Optional)

1. Navigate to Series → Settings → Advanced and set **Enable Podcast Feed** to **Enable**
2. Save Changes — a Podcast tab will appear
3. Navigate to Series → Settings → Podcast tab
4. Enter the **Title**, **Description**, and **Provider**
5. Upload podcast artwork (1400×1400px minimum)
6. Configure feed categories

See the [Podcast Setup Guide](../features/podcast-setup.md) for detailed instructions.

## Adding Your First Content

### 1. Add Speakers

1. Navigate to Series → Speakers and click **Add New**
2. Enter speaker name, bio, and photo
3. Publish the speaker profile

### 2. Create a Series

1. Open **Series** and click **Add New**
2. Enter series title, description, and artwork
3. Publish the series

### 3. Add Your First Sermon

1. Navigate to Series → Messages and click **Add New**
2. Enter sermon title and content
3. Add media files (audio/video)
4. Select the speaker and series
5. Add scripture references and topics
6. Publish the sermon

## Displaying Sermons on Your Website

### Using the Block Editor

CP Sermon Library provides Gutenberg blocks for displaying sermons:

1. Create or edit a page
2. Insert the **CP Sermons Sermons/Series** block, or a pattern such as **Latest Sermon**, **Latest Sermons - Grid View**, or **Latest Series - List View**
3. In the block sidebar, set **Type**, **Items to show**, and **Order by**. Use the toolbar **List view** or **Grid view**.
4. Preview and publish your page

### Using Shortcodes

As an alternative to blocks, you can use shortcodes:

- `[cpl_item id="123"]` or `[cp-sermon id="123"]` — Display one sermon. `template="alt"` uses the alternate layout.
- `[cp-sermons]` — Display the sermons archive
- `[cpl_template id="123"]` — Display a template

`[cpl_item]` also accepts `player`, `details`, `location`, and `service-type`. See [Customization and Display](../features/customization-and-display.md).

### Using Archive Pages

CP Sermon Library automatically creates archive pages based on your configured URL slugs. With default settings:

- Main sermon archive: `/messages/`
- Series archive: `/series/`
- Speaker archive: `/speakers/`

You can customize the sermon and series slugs in their respective settings tabs. Speaker slugs are derived from the plural label. After changing any slugs, go to Settings → Permalinks and click "Save Changes" to flush rewrite rules.

## Importing Existing Sermons

### From Other Sermon Plugins

If the plugin detects data from Sermon Manager, Series Engine, or Church Content on activation, a migration wizard will appear to help transfer your existing content.

### Using CSV Import

Import sermons from a spreadsheet:

1. Navigate to Series → Tools
2. Use the Import/Export tab
3. Download the sample CSV template
4. Fill in your sermon data following the template format
5. Upload your completed CSV file and start the import

## Next Steps

- [Managing Sermons](../features/managing-sermons.md) — Learn to add and organize sermon content
- [Customization & Display](../features/customization-and-display.md) — Customize how sermons appear on your site
- [Podcast Setup](../features/podcast-setup.md) — Set up your podcast feed
