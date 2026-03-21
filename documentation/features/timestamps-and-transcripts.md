# Sermon Timestamps & Transcripts

This guide covers the timestamp and transcript features in CP Sermon Library.

## Sermon Timestamps

Timestamps allow you to place a visual marker on the media player's timeline, helping listeners find where the sermon begins in a longer recording.

### How Timestamps Work

When you set a timestamp on a sermon, a marker labeled "Sermon" appears on the player's timeline slider at that point. When a listener drags the slider near the marker, it automatically snaps to the exact timestamp position. This makes it easy for listeners to skip pre-sermon content (announcements, worship, etc.) and jump directly to the message.

### Adding a Timestamp

1. Edit the sermon
2. In the sermon details, locate the timestamp field
3. Enter the timestamp in one of these formats:
   - `mm:ss` (e.g., `12:34`)
   - `hh:mm:ss` (e.g., `1:12:34`)
4. Save the sermon

### Quick Edit Timestamps

You can also set timestamps using Quick Edit:

1. From the sermon list, hover over a sermon and click "Quick Edit"
2. Locate the Timestamp field
3. Enter the timestamp value
4. Click "Update" to save

### Timestamp Best Practices

- Use timestamps to help listeners skip pre-sermon content in recordings
- Be consistent with your timestamp approach across sermons
- Test playback after setting timestamps to verify the marker appears correctly

## Managing Sermon Transcripts

Transcripts make your sermon content searchable, accessible, and beneficial for SEO.

### Adding a Transcript

1. Edit the sermon
2. Find the "Transcript" metabox in the sermon editor
3. Enter or paste the transcript text
4. Use the WordPress editor formatting tools to structure the content
5. Save the sermon

### YouTube Transcript Import

For sermons with YouTube videos, you can import auto-generated captions as transcripts:

1. Add the YouTube video URL to the sermon's Video URL field
2. In the sermon list, click the "Import from YouTube" button in the Transcript column
3. Review and edit the imported transcript for accuracy
4. Save the sermon

You can also use the bulk action "Import Transcript" to import transcripts for multiple sermons at once.

> **Note:** YouTube transcript import requires that the video has auto-generated or manually added captions available.

### OpenAI Integration for Transcript Formatting

CP Sermon Library supports OpenAI for cleaning up and formatting transcripts. This integration is configured via a PHP filter — there is no admin settings page for the API key.

To enable OpenAI transcript formatting, add this to your theme's `functions.php` or a custom plugin:

```php
add_filter( 'cpl_openai_api_key', function() {
    return 'your-openai-api-key';
});
```

Once configured, transcripts imported from YouTube are automatically sent to OpenAI for formatting and cleanup in the background. The formatted transcript replaces the raw import.

### Transcript Display

Control whether transcripts appear on sermon pages:

1. Navigate to Messages → Settings → Messages tab
2. Find the "Show Transcript" option
3. Toggle transcript visibility
4. Save Changes

### Transcript Formatting Tips

- **Paragraph Breaks** — Include paragraph breaks for readability
- **Scripture References** — Format scripture quotations distinctly
- **Headings** — Use headings to mark major sections
- **Speaker Identification** — Use bold formatting for different speakers

## SEO Benefits

Adding transcripts to your sermons provides SEO advantages:

- Search engines can index the full text content of your sermons
- Increases the keywords your sermons can rank for
- Improves accessibility, which search engines reward

## Accessibility Benefits

Using timestamps and transcripts improves your content's accessibility:

- Provides alternative access for hearing-impaired users
- Enables non-native speakers to follow along more easily
- Allows users to consume content in text form when audio isn't an option

## Troubleshooting

### Timestamp Not Working

- Verify the timestamp format is correct (`mm:ss` or `hh:mm:ss`)
- Check that the media file is properly attached to the sermon
- Ensure the media player is loading without JavaScript errors

### YouTube Import Issues

- Verify the video has auto-generated captions available
- Check that the YouTube URL is correct and the video is public
- Try with a different YouTube video to rule out video-specific issues

### Transcript Not Displaying

- Check that "Show Transcript" is enabled in Messages → Settings → Messages tab
- Verify the sermon has transcript content entered
- Test with a default WordPress theme to rule out theme conflicts
