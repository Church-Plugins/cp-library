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
 * Remove orphaned speaker and series relationship rows.
 *
 * Legacy save handlers could create source_item / item_type rows whose
 * source_id / item_type_id is NULL, 0, or points to a deleted record. These
 * render as empty entries (e.g. ", Speaker Name") and the normal save flow
 * cannot remove them.
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

	cp_library_invalidate_item_caches( $affected );

	cp_library_complete_migration( $migration );
}
add_action( 'cpl_migrate', 'cp_library_migrate_1_6_3', 10, 2 );

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
 * @since 1.6.3
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
