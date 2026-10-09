# Frequently Asked Questions

## Getting Started

### How do I add a new sermon?

Navigate to Messages → Add New. Enter the sermon title, add audio/video media, select a speaker and series, then click Publish. See [Managing Sermons](../features/managing-sermons.md) for detailed instructions.

### How do I display sermons on my website?

You have three options:

1. **Gutenberg Blocks** — Use the sermon blocks in the WordPress block editor
2. **Shortcodes** — Use `[cpl_item id="123"]` to show one sermon, or `[cp-sermons]` to show the sermon archive
3. **Archive Pages** — Link to the automatic archive at `/messages/` (or your custom slug)

See [Customization & Display](../features/customization-and-display.md) for details.

### How do I create a sermon series?

Navigate to Messages → Series → Add New. Enter the series title, description, and upload artwork. When adding sermons, assign them to the series using the Series dropdown.

### Can I rename "Sermons" to "Messages" or another term?

Yes. Navigate to Messages → Settings and select the tab for the content type you want to rename (Messages, Series, or Speaker). Change the Singular and Plural Labels there. This updates the terminology throughout the plugin and your site, including the admin menu name.

## Media & Playback

### What audio/video formats are supported?

- **Audio** — MP3 is recommended for broadest compatibility
- **Video** — MP4 for direct uploads, or embed from YouTube/Vimeo via URL

### Can I use YouTube or Vimeo videos instead of uploading files?

Yes. When editing a sermon, paste the YouTube or Vimeo URL into the **Message Video** field. The video will automatically embed on the sermon page.

### How does the persistent player work?

The persistent player is a site-wide audio and video bar that continues playing as visitors navigate your site. It is always active — no configuration needed. See [Persistent Player](../features/persistent-player.md).

### Why won't my audio/video play?

1. Check that the media file URL is accessible
2. Verify the file format is browser-compatible (MP3, MP4)
3. Check for JavaScript errors in your browser console
4. Test with a different browser

## Podcast

### How do I set up a podcast feed?

Your feed is `/messages/feed/podcast/` (or your slug) and works as soon as the plugin is active; don't use `/feed/podcast/`, which gives an empty feed. To set the title and artwork, set **Enable Podcast Feed** to **Enable** on the Advanced tab, then fill in the **Podcast** tab (at least Title and Image). Until a Title is saved, the feed is named after the newest sermon. See [Podcast Setup](../features/podcast-setup.md).

### Can I create separate podcast feeds for different series or speakers?

Yes. Each series and speaker also has its own podcast feed: add `/feed/podcast/` to the series or speaker page URL, for example `https://yoursite.com/series/series-slug/feed/podcast/` or `https://yoursite.com/speakers/speaker-slug/feed/podcast/`. (The `series` and `speakers` parts follow your Series slug and Speaker label settings.)

### Why is my podcast feed returning a 404 error?

Go to Settings → Permalinks and click "Save Changes" to flush rewrite rules. This resolves most feed URL issues.

### How do I exclude a sermon from the podcast?

Edit the sermon, find the "Exclude from Podcast" checkbox in the Message Details metabox, and check it. This checkbox appears when **Enable Podcast Feed** is turned on.

## Organization & Filtering

### What's the difference between Topics, Seasons, and Scripture?

- **Topics** — Subject categories (e.g., Faith, Prayer, Leadership)
- **Seasons** — Time-based groupings (e.g., Advent 2024, Summer Series)
- **Scripture** — Bible references (e.g., Romans 8, John 3:16)

All three are optional taxonomies you can use to organize your sermons.

### How do Service Types work?

Service Types categorize sermons by the service they were delivered in (Sunday Morning, Youth, etc.). Enable them in Messages → Settings → Advanced. See [Service Types](../features/service-types.md).

### What are Sermon Variations?

Variations let you create different versions of the same sermon for multiple services, each with their own speaker, media, and timestamps. See [Sermon Variations](../features/sermon-variations.md). Variations need **Enable Service Types** turned on (**Advanced** tab), **Enable Variations (beta)** turned on (**Messages** tab), and a **Variation Source** picked (**Messages** tab).

### Can I hide certain sermons from the main list?

Yes. Edit the sermon and check **Exclude from Main List** in the Visibility Settings panel. The sermon still appears on its own page and in taxonomy archives.

## Display & Templates

### How do I change the sermon layout?

Navigate to Messages → Settings → Messages tab and choose the Single Page Template option. To change the layout of one sermon list, use the **CP Sermons Sermons/Series** block: choose **List view** or **Grid view** in the block toolbar (Grid adds **Columns**), and choose which detail blocks appear inside the **Sermon Template**. For a single sermon, the `[cpl_item id="123" template="alt"]` shortcode shows the alternate layout; `player="false"` or `details="false"` hide the player or the sermon details.

### Can I customize sermon templates in my theme?

Yes. Copy template files from the plugin's `/templates/` directory to `/wp-content/themes/your-theme/cp-library/` and modify them. Your customizations will be preserved through plugin updates.

### Why aren't my template changes showing?

1. Verify template files are in the correct theme directory
2. Clear any active cache plugins
3. Check that the file names match exactly

## Technical

### What are the system requirements?

- WordPress 6.0+
- PHP 7.4+
- MySQL 5.6+

### How do I update the plugin?

Updates appear in your WordPress dashboard. Create a backup before updating. See [Updating](../advanced/updating.md).

### Where can I find debug information?

Enable debug mode in Messages → Settings → Advanced. This enables detailed logging accessible at **Tools** (under **Series** by default, or **Messages** if you changed Set default menu item) > **Log**.

## Still Need Help?

- Check the [Troubleshooting Guide](../advanced/troubleshooting.md)
- Visit [Getting Help](getting-help.md) for support options
