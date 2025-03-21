# Comprehensive Podcast Setup Guide

This guide will walk you through the complete process of setting up, configuring, and distributing your church's sermon podcast using CP Sermon Library.

## Podcast Fundamentals

### What is a Sermon Podcast?

A sermon podcast is an audio feed of your church's messages that people can subscribe to in podcast apps like Apple Podcasts, Spotify, Google Podcasts, and more. This allows your congregation and others to:

- Automatically receive new sermons when published
- Listen on mobile devices, smart speakers, and other platforms
- Discover your content through podcast directories
- Listen offline after downloading episodes

### Benefits of Podcasting Your Sermons

- **Extended Reach**: Reach people beyond your physical congregation
- **Convenience**: Allow listeners to engage with sermons on their schedule
- **Discoverability**: Appear in podcast directory searches
- **Analytics**: Gain insights into listener behavior
- **Accessibility**: Provide alternative access to your message

## Initial Podcast Configuration

### Accessing Podcast Settings

1. Navigate to Library → Settings → Podcast
2. Review all available settings tabs:
   - General
   - Display
   - Feed
   - iTunes
   - Advanced

### Essential Settings

Configure these critical settings first:

#### Basic Information
- **Title**: Your podcast name (typically your church name + "Sermons" or "Messages")
- **Subtitle**: A brief description (1-2 sentences)
- **Description**: A complete description of your podcast content
- **Website**: Your church website URL
- **Language**: Primary language of your sermons

#### Author Information
- **Author Name**: Usually your church name or lead pastor
- **Email**: Contact email (required by podcast directories)
- **Copyright**: Copyright statement (e.g., "© 2025 [Church Name]")

#### Cover Artwork
- **Podcast Image**: Upload your podcast artwork
  - Must be at least 1400×1400 pixels (square format)
  - Maximum 3000×3000 pixels
  - JPG or PNG format
  - Less than 500KB in size

#### iTunes Categories
- **Primary Category**: Select the most relevant category
  - "Religion & Spirituality" is typically selected
- **Subcategory**: Usually "Christianity"
- **Secondary Category**: Optional additional category

### Feed URL Configuration

CP Sermon Library automatically generates your podcast feed at:
`https://yoursite.com/feed/podcast/`

This is the URL you'll submit to podcast directories.

## Advanced Podcast Configuration

### Episode Settings

Control which sermons appear in your podcast:

1. Navigate to Library → Settings → Podcast → Advanced
2. Configure:
   - **Episode Limit**: Number of sermons to include in feed (25-50 recommended)
   - **Include Series**: Whether to create series feeds
   - **Sort Order**: Usually newest first
   - **Media Preference**: Prioritize audio or video

### Media File Settings

Optimize your media files for podcast distribution:

1. Navigate to Library → Settings → Podcast → Advanced
2. Configure:
   - **Media Format**: Audio format settings
   - **ID3 Tags**: Whether to include file metadata
   - **File Size Limit**: Maximum file size for episodes
   - **Duration Display**: How sermon length is displayed

### Explicit Content Rating

Set the appropriate content rating:

1. Navigate to Library → Settings → Podcast → iTunes
2. Set "Explicit Content" to "No" for most church podcasts
3. Save changes

## Creating Specific Podcast Feeds

CP Sermon Library can generate multiple specialized feeds:

### Series-Specific Feeds

Create a podcast feed for a specific sermon series:

1. Navigate to Library → Settings → Podcast → Advanced
2. Enable "Series Feeds"
3. Access at: `https://yoursite.com/feed/podcast/series/series-slug/`

### Speaker-Specific Feeds

Create a podcast feed for a specific speaker:

1. Navigate to Library → Settings → Podcast → Advanced
2. Enable "Speaker Feeds"
3. Access at: `https://yoursite.com/feed/podcast/speaker/speaker-slug/`

### Topic-Specific Feeds

Create a podcast feed for a specific topic:

1. Navigate to Library → Settings → Podcast → Advanced
2. Enable "Topic Feeds"
3. Access at: `https://yoursite.com/feed/podcast/topic/topic-slug/`

### Service Type Feeds

If you use sermon variations with service types:

1. Navigate to Library → Settings → Podcast → Advanced
2. Enable "Service Type Feeds"
3. Access at: `https://yoursite.com/feed/podcast/service-type/service-type-slug/`

## Controlling Episode Content

### Managing Episode Details

Control what appears in each podcast episode:

1. Edit a sermon
2. Scroll to the "Podcast Settings" section
3. Configure:
   - **Episode Title**: Default is sermon title, but can be customized
   - **Episode Description**: Default is sermon excerpt
   - **Custom Thumbnail**: Episode-specific image
   - **Exclude from Podcast**: Option to hide specific sermons

### Using the Sermon Content as Show Notes

Podcast apps display "show notes" for each episode:

1. Navigate to Library → Settings → Podcast → Display
2. Configure:
   - **Content Display**: How much sermon content to include
   - **Use Scripture**: Whether to include scripture references
   - **Include Links**: Whether to include hyperlinks
   - **Speaker Info**: Whether to include speaker details

## Validating Your Podcast Feed

Before submitting to directories, validate your feed:

1. Copy your feed URL: `https://yoursite.com/feed/podcast/`
2. Visit a podcast validator service:
   - [Cast Feed Validator](https://castfeedvalidator.com/)
   - [Podbase Podcast Validator](https://podba.se/validate/)
3. Paste your feed URL and validate
4. Address any errors or warnings

Common issues to check:
- Missing required fields
- Improperly sized artwork
- Invalid media file formats
- Excessive feed size

## Submitting to Podcast Directories

### Apple Podcasts

1. Create an [Apple ID](https://appleid.apple.com/) if you don't have one
2. Visit [Podcast Connect](https://podcastsconnect.apple.com/)
3. Sign in with your Apple ID
4. Click "+" to add a new podcast
5. Enter your feed URL
6. Verify and submit
7. Approval typically takes 1-5 business days

### Spotify

1. Visit [Spotify for Podcasters](https://podcasters.spotify.com/)
2. Create an account or sign in
3. Click "Add podcast"
4. Provide your feed URL
5. Verify ownership
6. Submit for review
7. Approval typically takes 1-3 business days

### Google Podcasts

Google Podcasts automatically discovers podcast feeds that:
1. Are properly formatted
2. Are linked from your website
3. Use HTTPS

To ensure discovery:
1. Add a link to your podcast feed on your website
2. Include proper meta tags in your site's header

### Amazon Music / Audible

1. Visit [Amazon Music Podcasters](https://music.amazon.com/podcasts/submission)
2. Create an account or sign in
3. Submit your feed URL
4. Complete the submission form
5. Review and submit

## Promoting Your Podcast

### Website Integration

1. Add podcast subscribe buttons to your website
2. Place these buttons on:
   - Sermon pages
   - Series pages
   - Dedicated podcast page

To create a subscribe button section:

1. Navigate to Library → Settings → Podcast → Display
2. Enable "Subscribe Buttons"
3. Select which platforms to include
4. Configure display options

### Podcast Subscription Links

Create easy-to-remember links for verbal announcements:

1. Consider creating a dedicated page at: `yourchurch.com/podcast`
2. Use a podcast service like [Podfollow](https://podfollow.com/) to create a universal subscribe link
3. Promote this link in church announcements, bulletins, and social media

## Monitoring & Analytics

### Podcast Performance Tracking

CP Sermon Library provides basic podcast analytics:

1. Navigate to Library → Analytics
2. View the "Podcast" tab
3. See metrics like:
   - Total subscribers
   - Episode downloads
   - Popular episodes
   - Listening duration

### Advanced Analytics

For more detailed podcast analytics:

1. Consider using a podcast hosting service alongside CP Library:
   - [Buzzsprout](https://www.buzzsprout.com/)
   - [Podbean](https://www.podbean.com/)
   - [Transistor](https://transistor.fm/)
2. Configure the direct file URLs in CP Library to point to your hosting service

## Troubleshooting Podcast Issues

### Feed Not Updating

If new episodes aren't appearing:

1. Verify the sermons have audio files attached
2. Check they're not excluded from the podcast
3. Flush rewrite rules: Settings → Permalinks → Save Changes
4. Test the feed URL directly in a browser

### Podcast Apps Not Displaying Cover Art

If your artwork isn't showing:

1. Verify the image meets size requirements (1400×1400px minimum)
2. Check file format (JPG or PNG)
3. Ensure file size is under 500KB
4. Re-upload the image in podcast settings

### Episodes Missing from Feed

If specific episodes aren't included:

1. Edit the sermon
2. Check "Podcast Settings" for "Exclude from Podcast"
3. Verify there's a valid audio file attached
4. Check if it exceeds any file size limits you've set

## Best Practices for Church Podcasts

### Optimal Episode Titles

Create clear, searchable titles:
- Include sermon title
- Consider adding series name for context
- Keep under 60 characters for best display
- Be consistent in formatting

Example: "Finding Peace in Chaos - Hope Remains Series"

### Audio Quality Guidelines

For the best listener experience:
- Record at 44.1 kHz, 16-bit minimum
- Export MP3 files at 128kbps mono or 192kbps stereo
- Normalize audio to -16 LUFS for consistent volume
- Edit out long silences and technical issues
- Consider adding a brief intro/outro

### Episode Descriptions

Create engaging episode descriptions:
- First 1-2 sentences are most important (visible in apps)
- Include key topics and scripture references
- Add timestamps for major points
- Include links to additional resources
- Maintain consistent formatting

### Publishing Schedule

Establish a consistent publishing routine:
- Set a regular release schedule (same day/time each week)
- Batch process sermons for timely publishing
- Consider scheduling releases for optimal timing
- Alert subscribers when schedule changes