# Getting Started with CP Library

This guide will help you install, configure, and start using CP Sermon Library for your church website.

## Overview of CP Sermon Library

CP Sermon Library is a comprehensive WordPress plugin designed for churches and ministries to manage and display sermons, sermon series, speakers, and related media. Key features include:

- Audio and video sermon management
- Sermon series organization
- Speaker profiles and filtering
- Scripture references and topic tagging
- Podcast feed generation
- Engagement analytics
- Integration with popular page builders

## Quick Installation

### System Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

### Installation Steps

1. **Install the Plugin**
   - Log in to your WordPress dashboard
   - Navigate to Plugins → Add New
   - Search for "CP Sermon Library"
   - Click "Install Now" and then "Activate"

2. **Run the Setup Wizard**
   - After activation, you'll be prompted to run the setup wizard
   - Follow the [setup wizard steps](setup-wizard.md) to quickly configure the plugin
   - If you skip the wizard, you can access it later via Library → Settings

3. **Verify Installation**
   - Check that the "Library" menu appears in your WordPress dashboard
   - Navigate to Library → Settings to confirm all options are accessible

## Manual Installation

If you prefer to install the plugin manually:

1. Download the plugin .zip file from your Church Plugins account
2. Log in to your WordPress dashboard
3. Navigate to Plugins → Add New → Upload Plugin
4. Choose the downloaded .zip file and click "Install Now"
5. After installation completes, click "Activate Plugin"

## Activating Your License

To receive updates and support, activate your license:

1. Navigate to Library → Settings → License
2. Enter your license key (found in your Church Plugins account)
3. Click "Activate License"
4. Verify the license status shows as "active"

## Initial Configuration

### Essential Settings

These key settings should be configured immediately:

1. **General Settings**
   - Navigate to Library → Settings → General
   - Configure content labels (what to call sermons, series, etc.)
   - Set URL slugs for sermons, series, and speakers
   - Enable/disable post types you'll use (speakers, series)

2. **Sermon Settings**
   - Navigate to Library → Settings → Item
   - Configure default sermon display templates
   - Set media player preferences
   - Configure timestamp and transcript options

3. **Podcast Settings** (if using podcasts)
   - Navigate to Library → Settings → Podcast
   - Enter podcast title, description, and author information
   - Upload podcast artwork (1400×1400px minimum)
   - Configure feed settings and categories

### Adding Your First Content

Start by adding these foundational elements:

1. **Add Speakers**
   - Navigate to Library → Speakers → Add New
   - Enter speaker name, bio, and photo
   - Publish the speaker profile

2. **Create Series**
   - Navigate to Library → Series → Add New
   - Enter series title, description, and artwork
   - Publish the series

3. **Add Your First Sermon**
   - Navigate to Library → Sermons → Add New
   - Enter sermon title and content
   - Add media files (audio/video)
   - Select the speaker and series
   - Add scripture references and topics
   - Publish the sermon

## Displaying Sermons on Your Website

### Using the Block Editor

CP Sermon Library provides Gutenberg blocks for displaying sermons:

1. Create or edit a page
2. Add sermon blocks:
   - Sermon Query block - Shows a list of sermons
   - Sermon Template block - Controls sermon display
   - Individual sermon element blocks (title, date, media, etc.)

3. Configure block settings in the sidebar
4. Preview and publish your page

### Using Shortcodes

Alternative to blocks, you can use these shortcodes:

- `[cpl_item_list]` - Display a list of sermons
- `[cpl_item]` or `[cp-sermon]` - Display a single sermon
- `[cp-sermons]` - Display the sermons archive

Example with parameters:
```
[cpl_item_list count="6" columns="3" template="grid" series="summer-series" pagination="true"]
```

### Using Archive Pages

CP Sermon Library automatically creates these archive pages:

- Main sermon archive: `/sermons/`
- Series archive: `/series/`
- Speaker archive: `/speakers/`
- Individual sermon pages: `/sermons/sermon-title/`

You can link to these pages from your navigation menu.

## Importing Existing Sermons

### From Other Sermon Plugins

CP Sermon Library can migrate from popular sermon plugins:

1. Navigate to Library → Tools → Migrate
2. Select the source plugin:
   - Sermon Manager
   - Series Engine
   - Church Content
   - Other supported plugins
3. Follow the on-screen instructions to complete the migration

### Using CSV Import

Import sermons from a spreadsheet:

1. Navigate to Library → Tools → Import
2. Download the sample CSV template
3. Fill in your sermon data following the template format
4. Upload your completed CSV file
5. Map the CSV columns to sermon fields
6. Start the import process

## Next Steps

After initial setup, consider these next steps:

### Customize Display Options

- Explore different sermon templates
- Configure filter display options
- Customize the sermon player appearance

### Set Up Podcast Feed

- Complete [podcast configuration](podcast-setup-guide.md)
- Submit your feed to podcast directories
- Configure episode settings

### Explore Advanced Features

- Set up [sermon variations](sermon-variations.md) for multiple services
- Configure [sermon timestamps](timestamps-and-transcripts.md) for navigation
- Add [sermon transcripts](timestamps-and-transcripts.md#managing-sermon-transcripts) for accessibility

### Configure User Permissions

- Set up [user roles and permissions](user-management.md)
- Configure contributor access if multiple staff members add sermons

## Getting Help

If you need assistance with setup:

- Check the [troubleshooting guide](troubleshooting.md)
- Visit the [Church Plugins website](https://churchplugins.com)
- Submit a support ticket through your account dashboard