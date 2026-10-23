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

/**
 * Remove orphaned and duplicated speaker and series relationship rows.
 *
 * Legacy save handlers could create source_item / item_type rows whose
 * source_id / item_type_id is NULL, 0, or points to a deleted record. These
 * render as empty entries (e.g. ", Speaker Name") and the normal save flow
 * cannot remove them. They could also insert the same association twice,
 * which double-lists the sermon in that source's feeds and archives; the
 * fixed save path collapses duplicates on the next save, but only for
 * sermons that get re-saved, so the upgrade collapses the rest here. The
 * oldest row is kept — it carries the original `order`.
 */
function cp_library_migrate_1_6_3( $old_version, $new_version ) {
	global $wpdb;

	$migration = '1.6.3';

	if ( ! version_compare( $old_version, $migration, '<' ) ) {
		return;
	}

	if ( cp_library_did_migration( $migration ) ) {
		return;
	}

	$speaker           = new \CP_Library\Models\Speaker();
	$source_table      = $speaker->get_prop( 'table_name' );
	$source_meta_table = $speaker->get_prop( 'meta_table_name' );

	$source_where = "`key` = 'source_item' AND ( `source_id` IS NULL OR `source_id` = 0 OR `source_id` NOT IN ( SELECT `id` FROM {$source_table} ) )";

	// note which items these rows belong to before deleting them, so their caches can be
	// invalidated precisely afterwards
	$affected = $wpdb->get_col( "SELECT DISTINCT `item_id` FROM {$source_meta_table} WHERE {$source_where}" );

	$wpdb->query( "DELETE FROM {$source_meta_table} WHERE {$source_where}" );

	$item            = new \CP_Library\Models\Item();
	$item_meta_table = $item->get_prop( 'meta_table_name' );
	$item_type_table = ( new \CP_Library\Models\ItemType() )->get_prop( 'table_name' );

	$type_where = "`key` = 'item_type' AND ( `item_type_id` IS NULL OR `item_type_id` = 0 OR `item_type_id` NOT IN ( SELECT `id` FROM {$item_type_table} ) )";

	$affected = array_merge( $affected, (array) $wpdb->get_col( "SELECT DISTINCT `item_id` FROM {$item_meta_table} WHERE {$type_where}" ) );

	$wpdb->query( "DELETE FROM {$item_meta_table} WHERE {$type_where}" );

	// Collapse duplicate rows, keeping the oldest per association. Runs after the
	// orphan cleanup so NULL ids (which never satisfy an equality join) are gone.
	$source_dupe_join = "{$source_meta_table} t JOIN {$source_meta_table} k ON k.`key` = 'source_item' AND t.`key` = 'source_item' AND k.`item_id` = t.`item_id` AND k.`source_id` = t.`source_id` AND k.`source_type_id` = t.`source_type_id` AND k.`id` < t.`id`";

	$affected = array_merge( $affected, (array) $wpdb->get_col( "SELECT DISTINCT t.`item_id` FROM {$source_dupe_join}" ) );

	$wpdb->query( "DELETE t FROM {$source_dupe_join}" );

	$type_dupe_join = "{$item_meta_table} t JOIN {$item_meta_table} k ON k.`key` = 'item_type' AND t.`key` = 'item_type' AND k.`item_id` = t.`item_id` AND k.`item_type_id` = t.`item_type_id` AND k.`id` < t.`id`";

	$affected = array_merge( $affected, (array) $wpdb->get_col( "SELECT DISTINCT t.`item_id` FROM {$type_dupe_join}" ) );

	$wpdb->query( "DELETE t FROM {$type_dupe_join}" );

	cp_library_invalidate_item_caches( $affected );

	cp_library_complete_migration( $migration );
}
add_action( 'cpl_migrate', 'cp_library_migrate_1_6_3', 10, 2 );

/**
 * Remove sermon CSV exports that earlier versions left in public folders.
 *
 * Before 1.8.0 every export was written to `{label}_{date}.csv` in the
 * uploads folder (or next to Tools.php when uploads was unavailable) and never
 * removed, so it stayed reachable at a guessable URL. 1.8.0 streams exports,
 * and an export only cleans up files named with the current sermon label.
 * This removes the rest on upgrade, whatever label they were exported under.
 *
 * @since 1.8.0
 */
function cp_library_migrate_1_8_0( $old_version, $new_version ) {
	$migration = '1.8.0';

	if ( ! version_compare( $old_version, $migration, '<' ) ) {
		return;
	}

	if ( cp_library_did_migration( $migration ) ) {
		return;
	}

	cp_library_remove_legacy_export_files();

	cp_library_complete_migration( $migration );
}
add_action( 'cpl_migrate', 'cp_library_migrate_1_8_0', 10, 2 );

/**
 * Delete sermon CSV exports written by versions before 1.8.0.
 *
 * A file is removed only when its name is `{label}_YYYY-MM-DD.csv` (the name
 * earlier exports used, for example `Messages_2019-03-17.csv`) and its entire
 * header row equals a header the export wrote in a released version.
 *
 * @since 1.8.0
 *
 * @param array|null $directories Folders to check. Defaults to the folders earlier exports wrote to.
 * @return string[] Paths that were deleted.
 */
function cp_library_remove_legacy_export_files( $directories = null ) {
	if ( null === $directories ) {
		$directories = array( CP_LIBRARY_PLUGIN_DIR . 'includes/Admin' );
		$upload_dir  = wp_upload_dir( null, false );

		if ( empty( $upload_dir['error'] ) && ! empty( $upload_dir['basedir'] ) ) {
			$directories[] = $upload_dir['basedir'];
			$months        = glob( trailingslashit( $upload_dir['basedir'] ) . '[0-9][0-9][0-9][0-9]/[0-9][0-9]', GLOB_ONLYDIR );

			if ( is_array( $months ) ) {
				$directories = array_merge( $directories, $months );
			}
		}
	}

	$deleted = array();

	foreach ( array_unique( $directories ) as $directory ) {
		$matches = glob( trailingslashit( $directory ) . '*_[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9].csv' );

		if ( ! is_array( $matches ) ) {
			continue;
		}

		foreach ( $matches as $path ) {
			if ( ! is_file( $path ) || ! cp_library_is_sermon_export( $path ) ) {
				continue;
			}

			wp_delete_file( $path );

			if ( ! file_exists( $path ) ) {
				$deleted[] = $path;
			}
		}
	}

	return $deleted;
}

/**
 * Header rows the sermon CSV export wrote in released versions.
 *
 * Taken from Tools::get_formatted_item() on each tag that wrote this export:
 * 1.2.1–1.3.2, 1.4.0–1.4.10, and 1.5.0–1.7.0. Tags before 1.2.1 did not write it.
 * The whole row has to match. A shared prefix does not.
 *
 * @since 1.8.0
 *
 * @return array<int, string[]>
 */
function cp_library_legacy_export_headers() {
	return array(
		array( 'Title', 'Description', 'Series', 'Date', 'Passage', 'Location', 'Service Type', 'Speaker', 'Topics', 'Season', 'Scripture', 'Thumbnail', 'Video', 'Audio' ),
		array( 'Title', 'Description', 'Series', 'Date', 'Passage', 'Location', 'Service Type', 'Speaker', 'Topics', 'Season', 'Scripture', 'Thumbnail', 'Video', 'Audio', 'Downloads' ),
		array( 'Title', 'Description', 'Transcript', 'Series', 'Date', 'Passage', 'Location', 'Service Type', 'Speaker', 'Topics', 'Season', 'Scripture', 'Thumbnail', 'Video', 'Audio', 'Downloads' ),
	);
}

/**
 * Whether a CSV file's header row is one the sermon export wrote.
 *
 * @since 1.8.0
 *
 * @param string $path File path.
 * @return bool
 */
function cp_library_is_sermon_export( $path ) {
	$handle = @fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

	if ( false === $handle ) {
		return false;
	}

	$header = fgetcsv( $handle, 0, ',', '"', '\\' );
	fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

	if ( ! is_array( $header ) ) {
		return false;
	}

	foreach ( cp_library_legacy_export_headers() as $expected ) {
		if ( $header === $expected ) {
			return true;
		}
	}

	return false;
}

/**
 * Drop the cached Item models and item meta for the given item ids.
 *
 * Scoped deliberately: a bare wp_cache_flush() would evict the whole object cache on
 * sites backed by Redis/Memcached — core's alloptions, user and term caches, every
 * other plugin's data, and on a shared backend every other site on the network — just
 * to invalidate two of this plugin's groups.
 *
 * @param array $item_ids Item model ids.
 *
 * @since 1.7.0
 */
function cp_library_invalidate_item_caches( $item_ids ) {
	global $wpdb;

	$item_ids = array_unique( array_filter( array_map( 'absint', (array) $item_ids ) ) );

	if ( empty( $item_ids ) ) {
		return;
	}

	$item         = new \CP_Library\Models\Item();
	$item_table   = $item->get_prop( 'table_name' );
	$cache_group  = $item->get_prop( 'cache_group' );
	$origin_group = $item->get_prop( 'cache_group_origin' );

	foreach ( array_chunk( $item_ids, 500 ) as $chunk ) {
		$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
		$rows         = $wpdb->get_results( $wpdb->prepare( "SELECT `id`, `origin_id` FROM {$item_table} WHERE `id` IN ({$placeholders})", $chunk ) );

		foreach ( $rows as $row ) {
			wp_cache_delete( $row->id, $cache_group );
			wp_cache_delete( $row->id, $cache_group . '_meta' );

			if ( $row->origin_id ) {
				wp_cache_delete( $row->origin_id, $origin_group );
			}
		}
	}
}
