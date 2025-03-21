# Setup Wizard Guide

CP Sermon Library includes a guided setup wizard to help you quickly configure your sermon library with the optimal settings for your church. This guide covers how to use the setup wizard effectively.

## Accessing the Setup Wizard

The setup wizard launches automatically when you first activate CP Sermon Library. If you need to access it again:

1. Navigate to Library → Settings
2. Click the "Setup Wizard" button at the top of the page

## Step 1: Welcome & Introduction

![Setup Wizard Welcome Screen](../assets/images/admin/setup-wizard-welcome.png)

The first screen welcomes you to CP Sermon Library and explains what to expect:

- Brief overview of the setup process
- Estimated completion time (5-10 minutes)
- What you'll need to prepare (church information, podcast details)

**Actions:**
- Click "Let's Get Started" to proceed

## Step 2: Basic Information

This step collects essential information about your church and sermon library:

### Church Information
- **Church Name**: Enter your church's full name
- **Website URL**: Your church website address
- **Church Logo**: Upload your church logo (used in various places)

### Sermon Terminology
- **Content Label**: Choose what to call your sermons (Sermons, Messages, Teachings, etc.)
- **Series Label**: Choose what to call your series (Series, Seasons, Campaigns, etc.)

**Actions:**
- Click "Continue" to save and proceed
- Click "Skip" to use default terminology

## Step 3: Content Structure

Configure how your sermon content will be organized:

### Post Types to Enable
- **Sermons**: Always enabled (required)
- **Series**: Recommended for most churches
- **Speakers**: Recommended for tracking different speakers
- **Service Types**: Enable for multi-service or multi-campus churches

### Taxonomies to Enable
- **Scripture**: Enable to categorize by Bible references
- **Topics**: Enable to categorize by sermon topics
- **Seasons**: Enable to organize by church seasons/campaigns

### URL Structure
- **Sermon URL Base**: Set the base slug for sermon URLs (default: "sermons")
- **Series URL Base**: Set the base slug for series URLs (default: "series")
- **Speaker URL Base**: Set the base slug for speaker URLs (default: "speakers")

**Actions:**
- Click "Continue" to save and proceed
- Click "Skip" to use recommended defaults

## Step 4: Display Settings

Configure how sermons will display on your website:

### Layout Options
- **Default Template**: Choose the default sermon display template
  - Grid (thumbnail-based grid layout)
  - List (vertical list with thumbnails)
  - Compact (text-focused list)
  - Vertical (card-style layout)

### Player Settings
- **Default Player**: Choose audio, video, or prefer video when available
- **Persistent Player**: Enable site-wide persistent audio player
- **Player Color**: Select primary color for the player UI

### Filter Display
- **Show Filters**: Enable/disable sermon filters on archive pages
- **Filter Position**: Choose where filters appear (sidebar, top, bottom)

**Actions:**
- Click "Continue" to save and proceed
- Click "Skip" to use recommended defaults

## Step 5: Podcast Configuration

Set up your podcast feed settings:

### Basic Podcast Information
- **Enable Podcast**: Turn podcast functionality on/off
- **Podcast Title**: Name of your podcast (typically "Church Name Sermons")
- **Description**: Brief description of your podcast
- **Author Name**: Typically your church name
- **Email Address**: Contact email (required by directories)

### iTunes Settings
- **Primary Category**: Select best-fitting podcast category
- **Subcategory**: Select relevant subcategory
- **Explicit Content**: Set to appropriate rating (typically "No")

### Cover Artwork
- **Podcast Image**: Upload square artwork (1400×1400px minimum)
- **Cover Preview**: See how your artwork will appear in podcast apps

**Actions:**
- Click "Continue" to save and proceed
- Click "Skip" to configure podcast later

## Step 6: Import Content (Optional)

If you're migrating from another sermon plugin or have sermon data to import:

### Migration Options
- **Migrate from Plugin**: Select source plugin if applicable
  - Sermon Manager
  - Series Engine
  - Church Content
  - Other supported plugins

### CSV Import
- **Import from CSV**: Option to import sermon data from CSV file
- **Download Template**: Link to download sample CSV format

**Actions:**
- Click "Start Migration" to begin migration process
- Click "Import CSV" to begin CSV import process
- Click "Skip" to add content manually later

## Step 7: Completion

The final screen confirms your setup is complete:

### Setup Summary
- Brief overview of what was configured
- Links to key management pages

### Next Steps
- Add your first sermon
- Create sermon series
- Add speakers
- Customize display settings further

**Actions:**
- Click "Add First Sermon" to go directly to sermon creation
- Click "Go to Dashboard" to return to WordPress dashboard
- Click "View Sermon Settings" to review and adjust configurations

## After Setup

After completing the setup wizard, you may want to fine-tune these additional settings:

1. **Advanced Settings**:
   - Analytics tracking options
   - Developer tools
   - Performance settings

2. **Integrations**:
   - Configure external services (YouTube, SermonAudio)
   - Set up page builder modules

3. **Template Customization**:
   - Adjust templates to match your site design
   - Customize information displayed

## Resetting the Wizard

If you need to start over with the setup wizard:

1. Navigate to Library → Settings → Advanced
2. Find the "Reset Options" section
3. Check "Reset Setup Wizard"
4. Click "Save Changes"
5. The wizard will be available to run again

## Troubleshooting Wizard Issues

### Wizard Won't Load

If the setup wizard doesn't appear:

1. Check for JavaScript errors in your browser console
2. Temporarily deactivate other plugins to check for conflicts
3. Try a different browser

### Settings Not Saving

If settings aren't being saved:

1. Verify WordPress permissions on your server
2. Check for database errors in your WordPress logs
3. Try with a default WordPress theme active