<?php
/**
 * Plugin constants
 */

/**
 * Setup/config constants
 */
if( !defined( 'CP_LIBRARY_PLUGIN_FILE' ) ) {
	 define ( 'CP_LIBRARY_PLUGIN_FILE',
	 	dirname( dirname( __FILE__ ) ) . "/cp-library.php"
	);
}
if( !defined( 'CP_LIBRARY_PLUGIN_DIR' ) ) {
	 define ( 'CP_LIBRARY_PLUGIN_DIR',
	 	plugin_dir_path( CP_LIBRARY_PLUGIN_FILE )
	);
}
if( !defined( 'CP_LIBRARY_PLUGIN_URL' ) ) {
	 define ( 'CP_LIBRARY_PLUGIN_URL',
	 	plugin_dir_url( CP_LIBRARY_PLUGIN_FILE )
	);
}
if( !defined( 'CP_LIBRARY_PLUGIN_VERSION' ) ) {
	 define ( 'CP_LIBRARY_PLUGIN_VERSION',
	 	'1.0.2'
	);
}
if( !defined( 'CP_LIBRARY_INCLUDES' ) ) {
	 define ( 'CP_LIBRARY_INCLUDES',
	 	plugin_dir_path( dirname( __FILE__ ) ) . 'includes'
	);
}
if( !defined( 'CP_LIBRARY_UPREFIX' ) ) {
	define ( 'CP_LIBRARY_UPREFIX',
		'cpl'
   );
}
if( !defined( 'CP_LIBRARY_TEXT_DOMAIN' ) ) {
	 define ( 'CP_LIBRARY_TEXT_DOMAIN',
		'cp_library'
   );
}
if( !defined( 'CP_LIBRARY_DIST' ) ) {
	 define ( 'CP_LIBRARY_DIST',
		CP_LIBRARY_PLUGIN_URL . "/dist/"
   );
}

/**
 * Licensing constants
 */
if( !defined( 'CP_LIBRARY_STORE_URL' ) ) {
	 define ( 'CP_LIBRARY_STORE_URL',
	 	'https://churchplugins.com'
	);
}
if( !defined( 'CP_LIBRARY_ITEM_NAME' ) ) {
	 define ( 'CP_LIBRARY_ITEM_NAME',
	 	'Church Plugins - Library'
	);
}

/**
 * App constants
 */
if( !defined( 'CP_LIBRARY_APP_PATH' ) ) {
	 define ( 'CP_LIBRARY_APP_PATH',
	 	plugin_dir_path( dirname( __FILE__ ) ) . 'app'
	);
}
if( !defined( 'CP_LIBRARY_ASSET_MANIFEST' ) ) {
	 define ( 'CP_LIBRARY_ASSET_MANIFEST',
	 	plugin_dir_path( dirname( __FILE__ ) ) . 'app/build/asset-manifest.json'
	);
}
