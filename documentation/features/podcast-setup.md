# Podcast Setup

This guide walks you through setting up your church's sermon podcast using CP Sermon Library.

## What is a Sermon Podcast?

A sermon podcast is an audio feed of your church's messages that people can subscribe to in podcast apps like Apple Podcasts, Spotify, Amazon Music, and more. This allows your congregation and others to:

- Automatically receive new sermons when published
- Listen on mobile devices, smart speakers, and other platforms
- Discover your content through podcast directories
- Listen offline after downloading episodes

## Enabling the Podcast Feed

The podcast feed is **disabled by default**. To enable it:

1. Navigate to Messages → Settings → Advanced
2. Find "Enable Podcast Feed" and select **Enable**
3. Click "Save Changes"

Once enabled, a **Podcast** tab appears in Messages → Settings where you can configure your feed.

## Configuring Podcast Settings

All podcast settings are on the Podcast tab:

1. Navigate to Messages → Settings → Podcast tab
2. Configure the fields below
3. Click "Save Changes"

### Essential Fields

- **Podcast Image** — Upload square artwork (1400×1400px minimum, max 3000×3000px, JPG or PNG)
- **Title** — Your podcast name (typically your church name + "Sermons" or "Messages")
- **Subtitle** — A brief description (1-2 sentences)
- **Description** — A complete description of your podcast content
- **Provider** — Usually your church name
- **Email** — Contact email (required by podcast directories)
- **Copyright** — Copyright statement (e.g., "© 2025 [Church Name]")
- **Link** — Your church website URL
- **Language** — Primary language of your sermons

### iTunes Settings

- **Category** — Select from available category options (default: "Christianity (Religion)")
- **Clean** — Check this box to indicate non-explicit content (typically checked for churches)

### Feed URL

CP Sermon Library automatically generates your main podcast feed based on your sermon archive URL:
`https://yoursite.com/messages/feed/podcast`

(If you've changed the sermon slug, the URL will reflect that, e.g., `/sermons/feed/podcast`.)

This is the URL you'll submit to podcast directories.

### Episode Count

The number of episodes in your feed is controlled by the WordPress setting at Settings → Reading → "Syndication feeds show the most recent X items." The default is 10.

## Taxonomy-Specific Feeds

You can access podcast feeds scoped to specific series, speakers, or service types by appending `/feed/podcast/` to the relevant page URL:

- Series feed: `https://yoursite.com/series/series-slug/feed/podcast/`
- Speaker feed: `https://yoursite.com/speakers/speaker-slug/feed/podcast/`
- Service type feed: `https://yoursite.com/service-type/service-type-slug/feed/podcast/`

## Excluding Sermons from the Podcast

To exclude a specific sermon from the podcast feed:

1. Edit the sermon
2. Find the "Exclude from Podcast" checkbox
3. Check the box to remove this sermon from the feed
4. Update the sermon

## Validating Your Podcast Feed

Before submitting to directories, validate your feed:

1. Copy your feed URL (shown on the Podcast settings page, e.g., `https://yoursite.com/messages/feed/podcast`)
2. Visit a podcast validator service:
   - [Cast Feed Validator](https://castfeedvalidator.com/)
   - [Podbase Podcast Validator](https://podba.se/validate/)
3. Paste your feed URL and validate
4. Address any errors or warnings

Common issues to check:
- Missing required fields (title, provider, email)
- Improperly sized artwork
- Invalid media file formats
- Sermons without audio files attached

## Troubleshooting Podcast Issues

### Feed Not Generating (404 Error)

1. Go to Settings → Permalinks and click "Save Changes" to flush rewrite rules
2. Verify your permalink structure is not set to "Plain"
3. Check server rewrite rules if using Nginx

### Episodes Missing from Feed

1. Check that the missing sermons have audio files attached
2. Verify the sermons are published (not draft or scheduled)
3. Check if the sermons are marked as "Exclude from Podcast"
4. The feed only includes the most recent X sermons (based on your Reading settings)

### Cover Art Not Displaying

1. Verify the image is at least 1400×1400 pixels
2. Check file format (JPG or PNG)
3. Ensure the file is JPG or PNG format
4. Re-upload the image in podcast settings
