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

- **YouTube** — Paste a YouTube URL into the Video URL field
- **Vimeo** — Paste a Vimeo URL into the Video URL field
- **Other oEmbed services** — Any oEmbed-compatible service works

To embed external video:

1. Edit a sermon
2. In the Video URL field, paste the link to your video
3. The video will automatically embed on the sermon page

## SermonAudio Integration

CP Sermon Library can import sermon data from SermonAudio.

### Enabling SermonAudio

1. Navigate to Messages → Settings → Advanced
2. Find "Enable Sermon Audio Integration" and select **Enable**
3. Click "Save Changes"
4. A Sermon Audio settings tab will appear where you can configure your broadcaster ID

### Configuring and Importing

Once enabled, a "Sermon Audio" settings tab appears:

1. Navigate to Messages → Settings → Sermon Audio tab
2. Enter your API Key (found at sermonaudio.com/members)
3. Enter your Broadcaster ID
4. Optionally set an "Ignore Before" date to skip older sermons
5. Click "Start full import" to begin importing sermons
6. Configure the Update Check interval for automatic syncing of new content

## YouTube Integration

### Video Embedding

Paste any YouTube URL into a sermon's Video URL field for automatic embedding.

### Transcript Import

YouTube videos with auto-generated captions can have their transcripts imported:

1. Add the YouTube video URL to the sermon's Video URL field
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

### CP Church Locations

Link sermons to specific campuses or locations:

- Assign sermons to locations
- Filter sermons by location on the frontend
- Display location-specific sermon archives

### CP Resources

Associate downloadable resources with sermons:

- Link study guides, handouts, and slides
- Display resources alongside sermon content
- Organize resources by series or topic

### The Events Calendar

CP Sermon Library includes compatibility with The Events Calendar. If both plugins use the "Series" slug, CP Sermon Library automatically changes The Events Calendar's series slug to "event-series" to prevent URL conflicts.

## Page Builder Integration

CP Sermon Library provides a **CP Sermons Template** module for each supported page builder. This module lets you embed any sermon template you've created in Messages → Templates.

> **Important:** You must first create a template in Messages → Templates using the block editor before the page builder module will have content to display.

### Beaver Builder

1. Install and activate both CP Sermon Library and Beaver Builder
2. Create a sermon template in Messages → Templates
3. Edit a page with Beaver Builder
4. Look for the "CP Sermons Template" module in the module panel (under the "CP Sermons" group)
5. Select your template from the dropdown

### Divi

1. Install and activate both plugins
2. Create a sermon template in Messages → Templates
3. Edit a page with Divi Builder
4. Add the "CP Sermons Template" module
5. Select your template from the dropdown

### Elementor

1. Install and activate both plugins
2. Create a sermon template in Messages → Templates
3. Edit a page with Elementor
4. Find the "CP Sermons Template" widget in the widget panel (under the "CP Library" category)
5. Select your template from the dropdown

## WP All Import Integration

If you use WP All Import for bulk data imports, CP Sermon Library automatically integrates with it. When WP All Import writes post meta for sermon posts, the plugin intercepts the following fields and routes them through its own data layer:

- `cpl_service_type` — Assigns the sermon to a service type
- `audio_url` — Sets the sermon's audio file URL
- `video_url` — Sets the sermon's video file URL

No configuration is required — the integration activates automatically when WP All Import is installed and active.

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
- Language files in the `/languages` directory
- Compatible with translation management plugins

### Multi-Language Sermons

For sites with multiple languages:

1. Use a multilingual plugin such as WPML or Polylang
2. Create translations for sermon posts, series, and speaker profiles
3. The plugin will display the appropriate language content based on the visitor's language preference

## Caching Compatibility

### General Cache Plugin Compatibility

CP Sermon Library works with popular caching plugins. If you experience issues:

1. Exclude dynamic pages (analytics tracking, podcast feeds) from caching
2. Clear cache after changing sermon settings
3. Ensure REST API endpoints are not cached

### LiteSpeed Cache

If using LiteSpeed Cache:

1. Navigate to LiteSpeed Cache → Settings → Excludes
2. Add sermon-related dynamic URLs to the exclusion list
3. Exclude podcast feed URLs from caching
4. Clear the cache after configuration changes
