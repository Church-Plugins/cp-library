# Integrations & Compatibility

CP Sermon Library integrates with other plugins and external services to extend functionality. This guide covers all available integrations and how to configure them.

## External Media Hosting

### Direct File Hosting

For audio and video files, you can use:

- Media uploaded directly to WordPress
- Files stored on external servers via URL
- CDN-hosted files for better performance

### Video Platform Integration

CP Sermon Library supports embedding videos from:

- **YouTube** — Paste a YouTube watch, Shorts, or youtu.be URL into the **Message Video** field in the **Message Details** box
- **Vimeo** — Paste a Vimeo URL into the **Message Video** field
- **Facebook, Dailymotion, and Wistia** — Paste the video URL into the **Message Video** field
- **Direct video** — Paste a direct `.mp4` URL into the **Message Video** field
- **Embed code** — Paste a service's embed HTML into **Message Video**. Embed code is filtered for safety. Only iframe, script, div and p tags are kept, and some iframe attributes (such as `allow`) are removed. Other URLs play only if the built-in player supports them (for example Twitch, Streamable, Vidyard, SoundCloud). For anything else, paste the embed code.

The **Message Video** field name follows your singular label. The **Message Details** box title does too.

To embed external video:

1. Edit a sermon
2. In the **Message Video** field, paste the link to your video
3. The video will automatically embed on the sermon page

## SermonAudio Integration

CP Sermon Library can import sermon data from SermonAudio.

### Enabling SermonAudio

1. Navigate to Series → Settings → Advanced, or under Messages if Series is turned off
2. Find "Enable Sermon Audio Integration" and select **Enable**
3. Click "Save Changes"
4. A Sermon Audio settings tab will appear where you can configure your broadcaster ID

### Configuring and Importing

Once enabled, a "Sermon Audio" settings tab appears:

1. Navigate to Series → Settings → Sermon Audio tab, or under Messages if Series is turned off
2. Enter your API Key (found at sermonaudio.com/members)
3. Enter your Broadcaster ID
4. Optionally set an **Ignore Messages Before** date to skip older sermons. The label follows your plural label.
5. Optionally set a **Minimum Audio Duration** (in seconds) to skip short clips, intros, and announcements
6. Click "Start full import" to begin importing sermons
7. Configure the Update Check interval for automatic syncing of new content

### Sermons already on your site

A sync links a SermonAudio sermon to one sermon already on your site when the titles match and both fall on the same calendar day in your site's timezone. Capital letters and extra spaces in the title do not matter. If two sermons that day share the title, or SermonAudio sends two sermons with that title for that day, the sync adds a new sermon. A sermon already tied to a different SermonAudio id is left alone.

Audio and video can confirm that link or rule it out. The same file confirms it. A different file of the same kind, on the same host, rules it out. A matching file does not link a sermon whose title is different.

A new sermon with no preach date on SermonAudio is dated with SermonAudio's published time, in your site's timezone. A sermon already on your site keeps its date when SermonAudio has no preach date.

When the sync links to a sermon you already had:

- Notes and scripture already on that sermon stay on the first link. If notes or scripture are empty, SermonAudio's text is filled in. On a later sync, those fields change when SermonAudio's text changed and the sermon still has the text from the last sync. An edit made on the site stays as you wrote it. If SermonAudio removes notes or scripture it had supplied, and nobody has edited that field, the field is cleared.
- Speaker, service type, audio, and video follow SermonAudio on every sync when SermonAudio sends that value. SermonAudio's speaker replaces the speakers on the sermon. SermonAudio's service type replaces the service type. Audio and video are updated to the SermonAudio files. If SermonAudio does not send a speaker, service type, audio file, or video, the one already on the sermon stays.
- The series is replaced when SermonAudio sends a series (which removes a hand-added second series). If SermonAudio sends no series, the series already on the sermon stay.

A sermon the sync adds as a new post still takes SermonAudio's notes on each later sync. It also takes SermonAudio's scripture when SermonAudio sends a passage.

### Speakers and series already on your site

A sync reuses the one speaker or series that already has the same name, and updates that record. Capital letters and extra spaces do not matter. SermonAudio's name replaces the name on your site.

A bio, description, or picture from SermonAudio replaces the one on your site when SermonAudio has that value. An empty bio or description leaves a bio or description you wrote in place. An empty picture leaves the picture on your site in place. For a series, SermonAudio uses the series picture, or the broadcaster picture when the series has none.

If two speakers or two series on your site share that name, the sync creates a new one. A speaker or series already tied to a different SermonAudio id is left alone, and a new one is created.

### Importing from the Command Line

For scripted, scheduled, or large-broadcaster imports, CP Sermon Library provides a WP-CLI command. The command runs the same import as the admin button but synchronously, so it returns when the import is finished and exits with a real status code.

```bash
# Full import (every sermon, oldest first)
wp cp sermonaudio import

# Pull only the most recent sermons (uses the "Check Count" setting)
wp cp sermonaudio import --recent

# Pull a specific number of recent sermons
wp cp sermonaudio import --recent=20

# Preview what would be imported without writing anything
wp cp sermonaudio import --dry-run

# Stop after the first N batches (each batch is up to 100 sermons) — useful
# for smoke tests on large broadcasters
wp cp sermonaudio import --max-batches=2
```

Notes:

- The command requires the SermonAudio adapter to be enabled and configured (API Key + Broadcaster ID).
- `--dry-run` fetches from the SermonAudio API and reports what would be created or updated, but skips all writes — handy for verifying configuration before a full sync.
- `--recent` mirrors the cron-based update check. It does not clear the import queue, change-detection store, or in-progress flag, so it can safely run alongside other syncs.
- `--recent` and `--max-batches` cannot be combined.
- Per-item output makes the command verbose on large libraries. Pipe to a log file for large runs: `wp cp sermonaudio import > import.log 2>&1`.

## YouTube Integration

### Video Embedding

Paste any YouTube URL into a sermon's **Message Video** field for automatic embedding.

### Transcript Import

Videos with captions, either uploaded or auto-generated, can have their transcripts imported. The first caption track YouTube lists is used, in any language. Watch (youtube.com/watch?v=), youtu.be, Shorts, live, and embed links all work:

1. Add the YouTube video URL to the sermon's **Message Video** field
2. In the sermon list, an "Import" button appears in the Transcript column for sermons with YouTube videos
3. Click "Import" to pull captions from YouTube
4. Review and edit the imported transcript
5. If OpenAI integration is configured, the transcript is automatically formatted after import

You can also use the bulk action "Import Transcript" to import transcripts for multiple sermons at once.

## OpenAI Integration

CP Sermon Library supports OpenAI for transcript formatting. The API key is configured via a PHP filter — there is no admin UI for this setting.

To enable OpenAI integration, add this to your theme's `functions.php` or a custom plugin:

```php
add_filter( 'cpl_openai_api_key', function() {
    return 'your-openai-api-key';
});
```

Once configured, OpenAI can be used to format and clean up sermon transcripts.

## Church Plugins Integrations

CP Sermon Library is designed to work with other Church Plugins products:

### CP Locations

This requires the CP Locations plugin with its location taxonomy enabled. Define `CP_LOCATIONS_TAX_ENABLED` as true in `wp-config.php`, or return true from the `cploc_location_taxonomy_enabled` filter.

Link sermons to specific campuses or locations:

- Assign sermons to locations
- Location-specific sermon archives, such as `/north-campus/messages/`, once the CP Locations location taxonomy is enabled (see above). The sermon filters do not include a location dropdown.

### CP Resources

This requires the CP Resources plugin, with sermons selected in the **Objects with Resources** setting in CP Resources settings.

Associate downloadable resources with sermons:

- Link study guides, handouts, and slides
- Display resources alongside sermon content
- Organize resources by series or topic

### The Events Calendar

CP Sermon Library includes compatibility with The Events Calendar. If both plugins use the "Series" slug, CP Sermon Library automatically changes The Events Calendar's series slug to "event-series" to prevent URL conflicts.

## Page Builder Integration

CP Sermon Library provides a **CP Sermons Template** module for each supported page builder. This module lets you embed any sermon template you've created in Series → Templates.

> **Important:** You must first create a template in Series → Templates using the block editor before the page builder module will have content to display.

### Beaver Builder

1. Install and activate both CP Sermon Library and Beaver Builder
2. Create a sermon template in Series → Templates
3. Edit a page with Beaver Builder
4. Look for the "CP Sermons Template" module in the module panel (under the "CP Sermons" group)
5. Select your template from the dropdown

### Divi

1. Install and activate both plugins
2. Create a sermon template in Series → Templates
3. Edit a page with Divi Builder
4. Add the "CP Sermons Template" module
5. Select your template from the dropdown

### Elementor

1. Install and activate both plugins
2. Create a sermon template in Series → Templates
3. Edit a page with Elementor
4. Find the "CP Sermons Template" widget in the widget panel (under the **CP Sermons** category)
5. Select your template from the dropdown

## WP All Import Integration

If you use WP All Import for bulk data imports, CP Sermon Library automatically integrates with it. When WP All Import writes post meta for sermon posts, the plugin intercepts the following fields and routes them through its own data layer:

- `cpl_speaker` — Assigns the sermon to a speaker
- `cpl_series` — Assigns the sermon to a series
- `cpl_service_type` — Assigns the sermon to a service type
- `audio_url` — Sets the sermon's audio file URL
- `video_url` — Sets the sermon's video file URL

A blank `cpl_speaker`, `cpl_series`, or `cpl_service_type` value is skipped and leaves existing assignments in place.

No configuration is required — the integration activates automatically when WP All Import Pro is installed and active.

## SearchWP Integration

If you use SearchWP, CP Sermon Library integrates with it to enhance admin search:

1. Install and activate SearchWP
2. Create a SearchWP engine named "sermons" and configure it to index sermon content (including transcripts)
3. Admin searches on the Messages list screen will use SearchWP instead of the default WordPress search
4. This allows searching within transcript content and other indexed fields from the admin

> **Note:** The integration only applies to admin searches on the sermon list screen. You can customize the engine name using the `cp_library_searchwp_engine` filter.

## Localization & Multi-Language Support

### Plugin Translation

CP Sermon Library is translation-ready:

- Text domain: `cp-library`
- The plugin does not ship translation files. Put `cp-library-{locale}.mo` in `wp-content/languages/plugins/`.
- Compatible with translation management plugins
