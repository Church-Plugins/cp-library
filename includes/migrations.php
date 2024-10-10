<?php

function cp_library_did_migration( $version ) {
	$migrations = get_option( 'cp_library_migrations', [] );
	return isset( $migrations[ $version ] );
}

function cp_library_complete_migration( $version ) {
	$migrations = get_option( 'cp_library_migrations', [] );
	$migrations[ $version ] = time();
	update_option( 'cp_library_migrations', $migrations );
}

function cp_library_migrate_1_5_0( $old_version, $new_version ) {
	$migration = '1.5.0';

	if ( ! version_compare( $old_version, $migration, '<' ) ) {
		return;
	}

	if ( cp_library_did_migration( $migration ) ) {
		return;
	}

	$key = 'cpl_advanced_options';
	$advanced_options = get_option( $key, [] );

	if ( empty( $advanced_options[ 'default_menu_item'] ) ) {
		$advanced_options[ 'default_menu_item'] = 'item_type';
		update_option( $key, $advanced_options );
	}

	cp_library_complete_migration( $migration );
}
add_action( 'cpl_migrate', 'cp_library_migrate_1_5_0', 10, 2 );
