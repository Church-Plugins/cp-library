# Church Plugins Library
Church library plugin for sermons, talks, and other media.

### Developer info ###
[![Deployment status from DeployBot](https://iwitness-design.deploybot.com/badge/02267418037485/200896.svg)](https://iwitness-design.deploybot.com/)
[![Deployment status from DeployBot](https://iwitness-design.deploybot.com/badge/77558060124950/197383.svg)](https://iwitness-design.deploybot.com/)
[![Deployment status from DeployBot](https://iwitness-design.deploybot.com/badge/56046448099960/197530.svg)](https://iwitness-design.deploybot.com/)

##### First-time installation  #####

- Copy or clone the code into `wp-content/plugins/cp-library/`
- Run these commands
```
composer install
npm install
cd app
npm install
npm run build
```

##### Dev updates  #####

- There is currently no watcher that will update the React app in the WordPress context, so changes are executed through `npm run build` which can be run from either the `cp-plugins` directory or from `cp-plugins/app`

### Change Log

#### Unreleased
* Bug Fix: SermonAudio imports use the sermon's full title when SermonAudio provides one (`fullTitle`), and fall back to the abbreviated title when it is missing or blank. Already-imported sermons are retitled when fetched again: the most recent ones (Check Count, default 50) on the next scheduled sync or Check Now, and older ones on a full import. Permalinks stay the same.

#### 1.7.0
* **Major Feature**: Full-site Import/Export (Tools → Import/Export → Full Migration) — exports sermons (with variations, timestamps, transcripts and downloads), series, speakers, service types, templates, taxonomy terms and optionally plugin settings. Imports run in resumable batches; re-importing the same file updates instead of duplicating.
* **Major Feature**: WP-CLI commands for full-site migration (`wp cpl export` / `wp cpl import`) with `--dry-run`, `--download-media`, `--include-settings`, `--match-by-slug` and `--batch-size` options
* Enhancement: `SermonSync::upsert()` can set a sermon's service type and supply `thumbnail_id` artwork for series and service types — a hand-picked image is never overwritten
* Enhancement: PHPUnit harness — WP-free unit suite (Brain Monkey) plus a WordPress integration suite; `vendor/` removed from version control, run `composer install` after pulling
* Bug Fix: Sermons with emoji in the title no longer fail to save on sites whose `cpl_*` tables predate 4-byte character support (ChurchPlugins 1.1.18)
* Bug Fix: Series/Speaker/Service Type podcast feeds serve the full-size featured image for channel art (previously 600×600, below Apple's 1400×1400 minimum)
* Bug Fix: Series/Speaker assignment saves reliably when the stored value already matches (duplicated sermons); clearing the field removes the assignment
* Bug Fix: One-time upgrade cleanup removes orphaned Speaker/Series/Service Type associations (the stray comma in speaker lists) and collapses duplicated ones (double-listed sermons in feeds)
* Bug Fix: Re-saving a sermon with duplicate Speaker/Series rows removes only the surplus row, never the one being kept
* Bug Fix: When Service Types drive variations, saving the parent sermon no longer overwrites the service type that identifies each variation
* Bug Fix: A save that doesn't include the Speaker/Series/Service Type field (e.g. programmatic CMB2 saves) no longer clears those assignments
* Bug Fix: WP All Import — a blank or unmatched Speaker/Series column no longer clears existing assignments; comma-separated names are matched individually
* Bug Fix: Permanently deleting a Series removes it from every sermon it contained
* Bug Fix: Listen starts playback on the first click (including Safari/iOS); video stays in the feature area after audio has played; embed audio renders in the feature area again

#### 1.6.2
* Bug Fix: Imported sermons (CSV import and SermonAudio adapter) were silently flagged as hidden and excluded from the main sermon list; imports now default to visible
* Change: The per-sermon visibility checkbox is now "Exclude from Main List" (default unchecked), matching the Series and Service Type metaboxes
* Feature: Tools → Migrate Visibility Settings, and Reset All Sermons to Visible for affected sites
* Feature: WP-CLI command for SermonAudio imports (`wp cp sermonaudio import`) with `--dry-run`, `--recent[=count]` and `--max-batches`
* Enhancement: Minimum audio duration filter for the SermonAudio adapter
* Bug Fix: Vimeo videos reliably unmute on iOS
* Bug Fix: All scripture references display on sermon detail views
* Bug Fix: Sermon sort order corrected on speaker pages and taxonomy archives

#### 1.6.1
* Bug Fix: Fix speaker single page not displaying sermons
* Enhancement: Change default SearchWP engine to "sermons" so the default engine can be used for the main site search

#### 1.6.0
* **Major Feature**: Complete filter system refactoring with new architecture for better performance and reliability
* **Major Feature**: Enhanced media player with significantly improved iOS support, mobile experience, and styling across sermon list and player interfaces
* **Major Feature**: Service Type enhancements with dedicated archives, filtering, and REST API support
* **Major Feature**: New visibility management system for controlling content access
* Enhancement: Post-type specific filter disable settings - independently control filter visibility for sermons and series with backward compatibility
* Enhancement: UTF-8 encoding support for CSV imports with automatic detection and conversion
* Enhancement: Improved accessibility with better keyboard navigation and screen reader support
* Enhancement: Enhanced API responses with variation data support
* Enhancement: Improved error handling with centralized error code system
* Enhancement: Scripture filter now available for series archives
* Bug Fix: Resolved filter bugs affecting Speaker and Service Type filters
* Bug Fix: Fixed critical bugs in series and item management
* Bug Fix: Corrected persistent player functionality issues

#### 1.5.4
* Enhancement: Use "Preach Date" when importing from SermonAudio (instead of publish date)
* Enhancement: Add template for Speaker Archive
* Bug Fix: Some templates were throwing errors

#### 1.5.3
* Enhancement: Improve download functionality for sermon audio
* Enhancement: Add cp-template shortcode for better page builder support
* Bug Fix: Fix incompatibility with Astra Pro addon
* Bug Fix: Custom per page count was not working for sermons

#### 1.5.2
* Enhancement: Update attachment icon for external resources
* Enhancement: Add option for managing sermon and series per page count
* Enhancement: Update styling for pagination
* Enhancement: Add widget areas for sermon and series single and archive templates
* Bug Fix: Fix issue with SermonManager import
* Bug Fix: Transcript & OpenAI parsing fix

#### 1.5.1
* Bug Fix: Better handling for icons

#### 1.5.0
* Enhancement: Add initial support for block themes
* Enhancement: Style updates
* Enhancement: Improvements for Import process
* Enhancement: Code Cleanup
* Enhancement: Add initial debug logging
* Enhancement: Add transcript support to frontend
* Enhancement: Add transcript support for Sermons
* Enhancement: Add automatic transcript import from YouTube
* Enhancement: Add support for SearchWP search engine in Sermon admin area
* Enhancement: Follow SermonAudio redirects when needed
* Bug Fix: Show correct thumbnail on single Sermon template
* Bug Fix: Fix issue with SermonAudio import

#### 1.4.10
* Enhancement: Add support for image in Podcast feed <item>

#### 1.4.9
* Bug Fix: Fix featured image on single sermon template

#### 1.4.8
* Bug Fix: Show correct post counts when using variations

#### 1.4.7
* Enhancement: Add additional image sizes to podcast feed <channel>

#### 1.4.6
* Compatibility update for Divi

#### 1.4.5
* Feature: Add filter by series in template builder
* Enhancement: Podcast feed improvements
* Enhancement: Import Service Type from SermonAudio
* Bug Fix: Fix audio / video play button not showing on archive

#### 1.4.4
* Bug Fix: Fatal error was thrown for non-Series edit pages

#### 1.4.3
* Bug Fix: Service Type filter was not working
* Bug Fix: Sermon vertical outline broken in Series context
* Bug Fix: Hide Sermons in Series edit screen when more than 60 Sermons
* Enhancement: Setting to hide item count in filters
* Enhancement: control Sermon order on Series page

#### 1.4.2
* Bug Fix: Speaker single page had incorrect sermon count displayed.
* Bug Fix: Speakers with no sermons assigned would display all sermons on the single page.
* Bug Fix: Hide variations without necessary metadata.
* Bug Fix: Make sure jQuery is loaded before scripts
* Enhancement: Add enclosures when importing sermons
* Enhancement: Update podcast feed for better compatibility
* Enhancement: Improve support for SermonAudio
* Enhancement: Add migration support for SeriesEngine

#### 1.4.1
* Bug Fix: Import series images from sermon manager when migrating
* Bug Fix: Fix scripture save bug
* Feature: Add tool to merge duplicate speakers.
* Enhancement: Better handling for multisite
* Enhancement: Add API Key support for SermonAudio
* Enhancement: Add show-all parameter to podcast feed to show all sermons

#### 1.4.0
* Enhancement: Add integration for SermonAudio
* Enhancement: Add vertical layout for Single Sermon Template
* Enhancement: Add downloads for sermons
* Enhancement: Add aspect-ratio control for Series grid view
* Update: Accessibility enhancements for sermon player
* Update: Improve migration wizard to better handle processing and avoid duplicate items
* Bug Fix: better handling for new lines in import CSV
* Security Fix: Resolve XSS vulnerability during search (props Kevin Wilgenbusch)

#### 1.3.2
* Bug Fix: Resolve Fatal error on activation

#### 1.3.1
* Enhancement: Allow page to share the same slug as the Podcast Feed

#### 1.3.0
* Enhancement: New Template builder to generate shortcodes
* Enhancement: Updates to Filters and additional settings
* Enhancement: Allow modifying Season and Topic terms
* Enhancement: Automatic migration from Church Content Plugin and Sermon Manager
* Bug Fix: Podcast feed now works for sermons
* Bug Fix: Fix bug where tables wouldn't always create on activation
* Update: Do not automatically set Series to draft when no sermons are published

#### 1.2.5
* Fix javascript error by enclosing filter js in enclosure

#### 1.2.4
* Fix alignment issue on archive page

#### 1.2.3
* Fix deprecation error
* Allow beta updates
* Fix player issues

#### 1.2.2
* Fix bug that was preventing the thumbnail from showing in the media player
* Fix error handling on single series template

#### 1.2.1
* Fix bug with series sermon rewrite rules
* Fix html in podcast feed

#### 1.2.0
* Add sermon export
* Add pagination for series with more than 10 sermons
* Add Analytics panel for sermon views
* Update podcast feed to work with series and taxonomies
* Add setting to control the admin default menu for sermons
* Add support for embeds in sermon audio and video

#### 1.1.1
* Updates to importer

#### 1.1.0
* New Scripture and Verse selector
* New beta feature: Sermon Groupings
* Add label control for buttons

#### 1.0.4
* Minor bug fixes
* Add import process for sermons
* Add podcast feed for sermons

#### 1.0.3.2
* Fix persistent player bug caused by loading the wrong js file

#### 1.0.3.1
* Show correct item on series template single item view
* Only show published items in item list

#### 1.0.3
* Update Church Plugins core

#### 1.0.2
* Show global messages on locations series page

#### 1.0.1
* Misc updates

#### 1.0.0
* Initial release
