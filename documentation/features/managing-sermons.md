# Managing Sermons

This guide covers adding, organizing, and managing sermon content in CP Sermon Library.

> **Note:** The default top-level admin menu is **Series** (**Set default menu item**). If you set that to Messages, or you rename a label, the menu name changes to match.

## Adding a New Sermon

To add a new sermon to your library:

1. Navigate to Series → Messages and click **Add New**
2. Enter the sermon title
3. Add the sermon content in the main editor
4. Configure sermon details in the metadata panels:
   - **Message Audio** and **Message Video** in **Message Details**
   - **Select a Speaker** in the **Speaker** box
   - **Select a Series** in the **Series** box
   - Scripture references in the **Scripture** box
   - Topics in the **Topics** box and seasons in the **Seasons** box
5. Click "Publish" to make the sermon available on your site

## Organizing Sermons with Categories & Filters

CP Sermon Library provides multiple ways to categorize and organize your sermons for easy navigation.

### Speakers

Speakers help visitors find sermons by a specific pastor or guest speaker:

1. Navigate to Series → Speakers and click **Add New**
2. Enter the speaker's name
3. Add a biographical description
4. Upload a profile image
5. Click "Publish"

When adding sermons, choose the speaker in the **Speaker** box (**Select a Speaker**).

### Topics

Topics allow categorization of sermons by subject matter:

1. Navigate to Series → Topics
2. Click "Add New Topic"
3. Enter the topic name and description
4. Click "Add New Topic"

Apply topics to sermons using the Topics panel when editing a sermon.

### Book of the Bible (Scripture)

Scripture references help visitors find sermons based on biblical passages:

Scripture has no admin menu. On the sermon edit screen, use the **Scripture** box to pick a book, chapter, and verse.

Apply scripture references to sermons using the **Scripture** box when editing a sermon.

### Date

The sermon date is the WordPress publish date in the **Publish** box. That date is used to:

- Sort sermons
- Build the **Year** filter on the archive
- Show the publish date on the site

### Seasons

Seasons can be used to organize sermons by church calendar, sermon campaigns, or other time-based groupings:

1. Navigate to Series → Seasons
2. Click "Add New Season"
3. Enter the season name and description
4. Click "Add New Season"

Apply seasons to sermons using the Seasons panel when editing a sermon.

## Using Series to Group Sermons

Sermon series allow you to group related sermons together:

1. Navigate to Series → Series and click **Add New**
2. Enter the series title
3. Add a description
4. Upload a featured image
5. Click "Publish"

When adding sermons, choose the series in the **Series** box (**Select a Series**).

## Uploading Sermon Audio & Video

### Audio Files

To add audio to a sermon:

1. Edit the sermon
2. In the **Message Details** box, use **Message Audio**
3. Upload the audio file or select from the media library
4. The plugin automatically generates an audio player

### Video Files

To add video to a sermon:

1. Edit the sermon
2. In the **Message Details** box, use **Message Video**
3. Upload the video file or enter a video URL (YouTube, Vimeo, etc.)
4. The plugin automatically generates a video player

## Embedding Sermon Audio & Video

Instead of uploading media files directly, you can embed videos from external services:

1. Edit the sermon
2. In the **Message Details** box, use **Message Video**
3. Paste the URL from YouTube, Vimeo, or other supported platforms
4. The plugin will automatically embed the video player

## Adding Sermon Transcripts

### Manual Transcripts

To add a manually created transcript:

1. Edit the sermon
2. In the Transcript metabox, enter or paste the transcript text
3. Use the formatting tools to structure the content
4. Update the sermon to save the transcript

### YouTube Transcript Import

1. Edit the sermon, set **Message Video** to the YouTube URL, and update the sermon
2. In the **Transcript** box, click **Import from YouTube**
3. Edit the transcript if needed and update the sermon

## Searching & Filtering Sermons

Site visitors can search and filter sermons on the sermon archive. The filter form includes a search field and the facets left on under **Disable Filters** (**Topics**, **Scripture**, **Seasons**, **Speakers**, and **Year**).

As an administrator, you can filter sermons in the admin area by:

1. Navigating to Series → Messages
2. Using the month dropdown at the top of the list
3. Using the **Speaker**, **Series**, and **Service Type** column links to filter the list

## Controlling Sermon Visibility

You can control whether individual sermons appear in the main sermon list:

### Sermon Visibility Settings

1. Edit a sermon
2. Locate the "Visibility Settings" panel in the sidebar
3. Check **Exclude from Main List** to remove this sermon from the main sermon list
4. Leave the box unchecked (the default) to keep the sermon in the main list
5. Hidden sermons remain accessible via direct links, taxonomy archives, and search

> **Note:** Prior to version 1.6.2 this checkbox was labeled "Show in Main List" and defaulted to checked. The label and default were inverted to match the existing Series and Service Type controls so that imported sermons (via CSV or SermonAudio) reliably default to visible. Any sermons you previously hid by hand can be carried forward using the **Migrate Visibility Settings** tool — see [Tools: Import, Export & Maintenance](tools.md).

### Inherited Visibility

A sermon's visibility may be controlled by its parent entities:

- If the sermon belongs to a hidden Series, it inherits that visibility setting
- If the sermon belongs to a hidden Service Type, it inherits that visibility setting
- When visibility is inherited, the control will be disabled with an explanatory message

### Visibility vs. Deletion

- Hidden sermons are not deleted — they remain in your database
- Hidden sermons still appear in their Series or Service Type archives
- Hidden sermons can still be accessed via direct links
- This feature is ideal for organizing specialized content while keeping main lists clean

## User Roles & Permissions

CP Sermon Library uses WordPress's default `post` capability type. Access to sermon management follows standard WordPress role permissions:

- **Administrator** — Full access to all sermon features and settings
- **Editor** — Can create, edit, and delete all sermons, series, and speakers
- **Author** — Can create and edit their own sermons
- **Contributor** — Can create sermons but not publish them
- **Subscriber** — No sermon management capabilities

Only Administrators can access the plugin settings.

## Quick Edit

For rapid single-sermon edits:

1. Hover over a sermon in the sermon list
2. Click "Quick Edit"
3. Modify the **Timestamp** field
4. Click "Update" to save changes
