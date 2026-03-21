# Settings & Configuration

This guide provides an overview of CP Sermon Library settings. Access settings at Messages → Settings in your WordPress dashboard.

> **Note:** The settings menu appears under your configured content label. The default is **Messages** → Settings.

## Main Tab (`cpl_main_options`)

### Primary Color

Set the primary accent color used by the media player and UI elements.

### Site Logo

Upload an image to use as the logo for your messages.

### Default Thumbnail

Upload a fallback thumbnail image used when a sermon or series doesn't have a featured image set.

### Button Labels

- **Play Video Button** — Text displayed on the video play button (default: "Watch")
- **Play Audio Button** — Text displayed on the audio play button (default: "Listen")

## Messages Tab (Item Options — `cpl_item_options`)

> **Note:** This tab is labeled with your plural content label (default: "Messages").

### Content Labels

Customize the terminology used for sermons:

- **Singular Label** — What to call a single sermon (default: "Message")
- **Plural Label** — What to call the collection (default: "Messages") — this also sets the admin menu name
- **Slug** — URL base for sermon archives (default: derived from the plural label)

### Display Options

- **Single Page Template** — Choose the layout for individual sermon pages (default or vertical)
- **Image Aspect Ratio** — Set the aspect ratio for sermon thumbnails
- **Messages Per Page** — Number of sermons to display on the archive page

### Info Items & Meta Items

Control what information displays with each sermon:

- **Info Items** — Content shown prominently (e.g., date, speaker, series)
- **Meta Items** — Secondary content (e.g., topics, scripture, downloads)

### Transcript Settings

- **Show Transcript** — Show or hide the transcript section on sermon pages

### Variation Settings

If variations are enabled (see Advanced tab):

- **Variations Enabled** — Enable sermon variations for this content type
- **Variation Source** — Select the source for variations (e.g., Service Types)

### Filter Settings

- **Disable Filters** — Selectively disable individual filter facets (topics, scripture, seasons, speakers, service types, year) on the sermon archive page

### Podcast Exclusion

At the individual sermon level, each sermon has an "Exclude from Podcast" checkbox in its edit screen to prevent it from appearing in the podcast feed.

## Series Tab (`cpl_item_type_options`)

- **Singular/Plural Labels** — Customize series terminology
- **Slug** — URL base for series archives
- **Image Aspect Ratio** — Aspect ratio for series artwork
- **Series Per Page** — Number of series to display on the archive page
- **Messages Per Series** — Number of sermons shown within a single series view
- **Messages Sort Order** — How sermons are ordered within a series (ascending or descending)
- **Messages Sort By** — Sort sermons by title or publish date
- **Disable Filters** — Selectively disable individual filter facets (topics, scripture, seasons, year) on the series archive page

## Speaker Tab (`cpl_speaker_options`)

- **Singular/Plural Labels** — Customize speaker terminology (changing the plural label also changes the URL slug)
- **Enable Permalinks** — Create individual speaker archive pages

## Service Type Tab (`cpl_service_type_options`)

> **Note:** This tab only appears when Service Types are enabled in the Advanced tab.

- **Singular/Plural Labels** — Customize service type terminology
- **Default Service Type** — Set the default service type for new sermons

## Podcast Tab (`cpl_podcast_options`)

> **Note:** This tab only appears when the Podcast Feed is enabled in the Advanced tab.

Configure your podcast feed settings:

- **Podcast Image** — Cover artwork (1400×1400px minimum, square format)
- **Title** — Your podcast name
- **Subtitle** — Brief description
- **Description** — Full podcast description
- **Item Image** — Check to include the sermon's featured image in the podcast episode description
- **Provider** — Podcast provider name (typically your church name)
- **Copyright** — Copyright statement
- **Link** — Your church website URL
- **Email** — Contact email (required by directories)
- **Category** — iTunes podcast category (combined category/subcategory selection, e.g., "Christianity (Religion)")
- **Clean** — Check this box to indicate non-explicit content (typically checked for churches)
- **Language** — Primary language
- **iTunes New Feed URL** — Only used when migrating feed URLs

See the [Podcast Setup Guide](podcast-setup.md) for more details.

## Advanced Tab (`cpl_advanced_options`)

### Modules

Enable or disable content types and features:

- **Enable Series** — Toggle the Series post type (enabled by default)
- **Enable Speakers** — Toggle the Speakers post type (enabled by default)
- **Enable Service Types** — Toggle Service Types for multi-service churches (disabled by default)
- **Enable Podcast Feed** — Toggle the podcast feed (disabled by default). When enabled, a Podcast settings tab appears.
- **Adapter Integrations** — Enable/disable integrations like SermonAudio (disabled by default). When enabled, an adapter-specific settings tab appears.

### Settings

- **Default Menu Item** — Choose whether the admin menu defaults to Messages or Series (defaults to Series; only appears when Series is enabled)

### Built-in Terms

- **Enable Built-in Seasons** — Use the plugin's curated list of church seasons
- **Enable Built-in Topics** — Use the plugin's curated list of sermon topics

### Filter Settings

- **Show Counts** — Show or hide the count of items in each filter option
- **Count Threshold** — Minimum number of sermons for a filter option to display (default: 3)
- **Disable Filters** — *(Deprecated)* Use the per-post-type "Disable Filters" settings on the Messages and Series tabs instead
- **Sort per Taxonomy** — Sort filter options by sermon count or alphabetically (configured per taxonomy: topics, scripture, seasons, speakers)

### Debug Mode

- **Enable Debug** — Turn on verbose debugging output in Messages → Tools → Log

## Sermon Audio Tab (`cpl_sermon_audio_adapter_options`)

> **Note:** This tab only appears when the Sermon Audio integration is enabled in the Advanced tab.

Configure your SermonAudio connection and import settings:

- **API Key** — Your SermonAudio API key (found at sermonaudio.com/members)
- **Broadcaster ID** — Your SermonAudio broadcaster ID
- **Ignore Messages Before** — Optionally set a date to skip importing older sermons
- **Start full import** — Begin a full import of all sermons from SermonAudio
- **Update Check** — How often to automatically check for new sermons (e.g., twice daily)
- **Check Now** — Manually trigger an update check
- **Check Count** — Number of sermons to check per update

## License Tab

- **License Key** — Enter your Church Plugins license key
- **Activate/Deactivate** — Manage license activation
- **License Status** — View current status (active, expired, etc.)

## Saving and Applying Settings

- Click "Save Changes" at the bottom of any settings tab
- After changing URL slugs, go to Settings → Permalinks and click "Save Changes" to flush rewrite rules
- Label changes take effect immediately (including the admin menu name)
