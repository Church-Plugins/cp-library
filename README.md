# Church Plugins Library
Church library plugin for sermons, talks, and other media.

### Developer info ###

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

#### 1.0.0
* Initial release
