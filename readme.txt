=== CP Sermon Library ===
Contributors: churchplugins, tabormoushey
Tags: sermons, church, podcast, speakers, series
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.6.1
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

= 1.6.0 =
Major update with rebuilt filter system, improved media player, and new filter controls per post type. If you have filters disabled in Advanced Settings, they will continue to work until you configure the new post-type specific settings.
