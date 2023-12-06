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

#### 1.3.0
* Enhancement: new Template builder to generate shortcodes
* Enhancement: updates to Filters and additional settings
* Enhancement: Allow modifying Season and Topic terms
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
