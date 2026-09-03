=== CP Sermon Library ===
Contributors: churchplugins, tannermoushey
Tags: sermons, church, podcast, speakers, series
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.6.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A full-featured sermon management plugin for churches. Organize messages by series, speakers, topics, and scripture with built-in audio/video support and podcast feeds.

== Description ==

CP Sermon Library provides everything your church needs to manage and share sermon content online. From audio and video playback to podcast feeds and sermon series, this plugin makes it easy to organize and display your teaching content.

The default post type label is "Messages" but can be customized to Sermons, Teachings, Talks, or whatever fits your church.

= Features =

* **Message Management** – Upload and manage messages with audio, video, or embedded media
* **Series Organization** – Group messages into series with cover images and descriptions
* **Speaker Profiles** – Create speaker pages with bios, photos, and message archives
* **Podcast Feeds** – Automatically generate iTunes-compatible podcast feeds
* **Taxonomy Organization** – Categorize messages by Scripture, Topics, and Seasons
* **Filtering System** – AJAX-powered filters for visitors to find content quickly
* **Multiple Layouts** – Grid, list, and vertical display options
* **Gutenberg Blocks** – Native WordPress block editor support
* **REST API** – Full REST API for custom integrations and headless sites
* **Responsive Design** – Mobile-friendly layouts and media player
* **Analytics** – Built-in tracking of message views and interactions

= Pro Features =

* **Message Variations** – Create different versions of a message for multiple services or locations
* **Service Types** – Organize content by service type with dedicated archives
* **Transcripts** – Import transcripts from YouTube or format with OpenAI
* **Timestamps** – Add navigation points for listeners to jump to specific moments
* **Downloadable Resources** – Attach PDFs, slides, and notes to messages
* **SermonAudio Integration** – Import and sync with SermonAudio
* **Advanced Analytics** – Detailed reporting with date ranges and exports

= Page Builder Support =

* Beaver Builder
* Divi
* Elementor

= Integrations =

* SearchWP – Enhanced search capabilities
* CP Locations – Link messages to physical locations
* The Events Calendar – Connect messages with events

== Installation ==

1. Upload the `cp-library` folder to the `/wp-content/plugins/` directory, or install directly through the WordPress plugin screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to **Messages** in the admin menu to start adding content.
4. Configure settings under **Messages → Settings**.

= Quick Start =

1. Create speakers under **Messages → Speakers**
2. Create a series under **Messages → Series**
3. Add your first message under **Messages → Add New**
4. Attach audio or video media to the message
5. Assign the message to a speaker, series, and any relevant taxonomies
6. Visit your message archive at `yoursite.com/messages/`

= Podcast Setup =

1. Go to **Messages → Settings → Podcast**
2. Fill in your podcast title, author, and description
3. Upload a cover image (minimum 1400x1400px, recommended 3000x3000px)
4. Select an iTunes category
5. Your feed will be available at `yoursite.com/podcast/`

== Frequently Asked Questions ==

= How do I change the "Messages" label? =

Go to **Messages → Settings → General** and update the post type label. You can rename it to Sermons, Teachings, Talks, or whatever fits your church.

= Can I customize the permalink structure? =

Yes. Navigate to **Messages → Settings → General** to configure the permalink slugs for messages, series, speakers, and taxonomies.

= Does it support video? =

Yes. You can upload video files directly, paste embed codes from YouTube or Vimeo, or provide a direct URL to a video file.

= How do I set up the podcast feed? =

Go to **Messages → Settings → Podcast**, fill in the required fields, and your feed will be automatically available at `yoursite.com/podcast/`. You can submit this URL to Apple Podcasts, Spotify, and other directories.

= Can I import messages from another source? =

Yes. The plugin supports CSV imports and SermonAudio integration for batch importing content.

= How do I control which filters appear on archive pages? =

Go to **Messages → Settings → Messages → Filters** or **Messages → Settings → Series → Filters** to control filter visibility independently for each archive type.

== Screenshots ==

1. Message archive page with filtering
2. Single message view with media player
3. Series archive display
4. Admin message editor
5. Plugin settings page
6. Podcast settings configuration

== Changelog ==

= 1.6.3 =
* New: **Tools → Import/Export → Full Migration** — move an entire sermon library between sites in one step. Exports sermons (with variations, timestamps, transcripts and downloads), series, speakers, service types, templates and taxonomy terms, and optionally your plugin settings. Imports run in small batches and can be resumed, so libraries with tens of thousands of sermons don't hit a PHP timeout. Re-importing the same file updates the existing content instead of duplicating it.
* New: WP-CLI commands for full-site migration (`wp cpl export` and `wp cpl import`), with `--dry-run`, `--download-media`, `--include-settings` and `--batch-size` options. Recommended for very large libraries.
* Fix: Sermons whose title contains an emoji no longer fail to save. On sites whose CP Sermons tables predate 4-byte character support, WordPress refuses the write rather than truncating it, so those sermons silently never imported — one site was losing 152 of 502 sermons on every sync. The sermon now saves, with the emoji omitted from the internal record only; the sermon's own title keeps it. No database changes are made.
* New: Sermon sync can now set a sermon's service type and supply artwork for both series and service types. `SermonSync::upsert()` accepts a `thumbnail_id` on either, used as the featured image when that series or service type does not already have one — an image chosen by hand is never overwritten. Service type artwork is what CP Sermons serves as podcast channel art.
* Fix: Series, Speaker, and Service Type podcast feeds now use the full-size featured image for channel artwork. Previously a 600×600 thumbnail was served, which falls below Apple Podcasts' 1400×1400 minimum and caused artwork to be rejected in Apple Podcasts / iTunes.
* Fix: Selecting a Series or Speaker on a sermon now saves reliably even when the stored value already matches — previously sermons copied with a duplicator plugin could not be assigned the same Series/Speaker as the original.
* Fix: Clearing the Series or Speaker field on a sermon now properly removes the assignment.
* Fix: Sermon Speaker lists no longer show a stray comma from orphaned speaker records; a one-time cleanup removes orphaned Speaker/Series associations on upgrade.
* Fix: A sermon with duplicate Speaker or Series records no longer loses that Speaker or Series when it is re-saved. Only the duplicate entry is removed.
* Fix: Service Type assignments now use the same corrected save path as Speakers and Series, so stale and empty entries are cleaned up properly.
* Fix: Speaker, Series and Service Type mappings in WP All Import now sync correctly. A blank column, or a name that doesn't match a published Speaker, Series or Service Type, no longer clears the assignments already on a sermon — re-running a feed to refresh other fields leaves them intact. Names separated by commas in a single column are now matched individually.
* Fix: Permanently deleting a Series now removes it from every sermon it contained, instead of leaving each sermon with a reference to a series that no longer exists.
* Fix: The Listen button on a sermon page now starts playback on the first click, including in Safari and on iOS. Video plays in the sermon's feature area again after audio has been played, instead of being pushed into the persistent player.
* Fix: Sermons whose audio is an embed (SoundCloud, Spotify, etc.) render the embed in the feature area again.

= 1.6.2 =
* Fix: Imported sermons (CSV import and SermonAudio adapter) were silently flagged as hidden and excluded from the main sermon list. Imports now default to visible.
* Change: The per-sermon visibility checkbox is now "Exclude from Main List" (default unchecked) instead of "Show in Main List" (default checked) — matching the existing Series and Service Type metaboxes.
* New: **Tools → Migrate Visibility Settings** — converts legacy meta from older sites and preserves any sermons you previously hid by hand.
* New: **Tools → Reset All Sermons to Visible** — one-click recovery for sites already affected by the hidden-on-import bug. Inheritance from hidden Series or Service Types is re-applied automatically.
* New: WP-CLI command for SermonAudio imports (`wp cp sermonaudio import`) with `--dry-run`, `--recent[=count]`, and `--max-batches` options for scripted and scheduled syncs.
* New: Minimum audio duration filter for the SermonAudio adapter — skip short clips, intros, and announcements during import by setting a threshold in the adapter settings.
* Fix: Vimeo videos now reliably unmute on iOS using a shared helper with chained promises.
* Fix: All scripture references now display on sermon detail views (previously only the first reference was shown in some layouts).
* Fix: Sermon sort order is now correct on speaker pages and taxonomy archives.

= 1.6.1 =
* Fix: Speaker page message display
* Fix: SearchWP default engine configuration

= 1.6.0 =
* New: Complete filter system rebuild with improved performance and caching
* New: Post-type specific filter controls for messages and series
* New: Service type archives and REST API support
* New: Visibility management system
* Enhancement: Improved media player with iOS compatibility
* Enhancement: UTF-8 encoding support for CSV imports
* Enhancement: Accessibility improvements for keyboard navigation and screen readers
* Enhancement: Scripture filter available on series archives
* Enhancement: Variation data included in REST API responses
* Fix: Speaker and Service Type filter bugs
* Fix: Mobile media player display issues
* Fix: Persistent player functionality
* Fix: Series and item management bugs
* Fix: Filter count calculations

= 1.4.10 =
* Bug fixes and improvements

= 1.4.0 =
* Feature updates and enhancements

= 1.3.0 =
* Feature updates and enhancements

= 1.2.0 =
* Feature updates and enhancements

== Upgrade Notice ==

= 1.6.3 =
Adds a full-site migration tool for moving a sermon library between sites, and fixes several Speaker/Series assignment bugs. A one-time cleanup runs on upgrade to remove orphaned and duplicate Speaker/Series associations. If you use WP All Import, note that a blank or unmatched Speaker/Series column no longer clears existing assignments.

= 1.6.2 =
Fixes a bug where imported sermons were silently hidden from the main list. The visibility checkbox has been renamed to "Exclude from Main List" (matching the existing Series and Service Type controls) so imports default to visible. After updating, visit Messages → Tools to migrate legacy visibility settings or reset any sermons that were accidentally hidden.

= 1.6.0 =
Major update with rebuilt filter system, improved media player, and new filter controls per post type. If you have filters disabled in Advanced Settings, they will continue to work until you configure the new post-type specific settings.
