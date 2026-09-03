<?php
/**
 * Shared helpers for the full-site Export/Import engine.
 *
 * @package CP_Library
 * @since   1.6.3
 */

namespace CP_Library\Admin\ImportExport;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Util
 *
 * Constants and helpers shared by the Exporter, Importer, admin UI and CLI.
 *
 * @since 1.6.3
 */
class Util {

	/**
	 * Export file format identifier (header record `format` field).
	 *
	 * @var string
	 */
	const FORMAT = 'cp-library-export';

	/**
	 * Export file format version. Bump when the record shape changes.
	 *
	 * @var int
	 */
	const FORMAT_VERSION = 1;

	/**
	 * Post meta key storing the post ID the record had on the source site.
	 *
	 * @var string
	 */
	const META_ORIGINAL_ID = '_cpl_import_original_id';

	/**
	 * Post meta key storing the source site URL a record was imported from.
	 *
	 * @var string
	 */
	const META_SOURCE_SITE = '_cpl_import_source';

	/**
	 * Post meta key storing the source-site featured image URL when media is
	 * not downloaded during import.
	 *
	 * @var string
	 */
	const META_THUMB_URL = '_cpl_import_thumbnail_url';

	/**
	 * Map of NDJSON record type => post type.
	 *
	 * Order matters: it is the export order, which guarantees that records are
	 * always written before anything that references them (speakers, service
	 * types and series before items; parent items before variants).
	 *
	 * @return array
	 */
	public static function record_post_types() {
		return apply_filters(
			'cpl_import_export_post_types',
			array(
				'speaker'      => 'cpl_speaker',
				'service_type' => 'cpl_service_type',
				'series'       => 'cpl_item_type',
				'template'     => 'cpl_template',
				'item'         => 'cpl_item',
			)
		);
	}

	/**
	 * Post type => record type map.
	 *
	 * @return array
	 */
	public static function post_type_records() {
		return array_flip( self::record_post_types() );
	}

	/**
	 * Taxonomies owned by this plugin — terms for these are exported and
	 * missing terms are created on import. Terms of other taxonomies found on
	 * exported posts (e.g. cp_location) are only assigned when a term with the
	 * same slug already exists on the target site.
	 *
	 * @return array
	 */
	public static function plugin_taxonomies() {
		$taxonomies = array( 'cpl_scripture', 'cpl_season', 'cpl_topic' );

		return apply_filters( 'cpl_import_export_taxonomies', $taxonomies );
	}

	/**
	 * Option groups included when settings are exported.
	 *
	 * @return array
	 */
	public static function settings_groups() {
		return apply_filters(
			'cpl_import_export_settings_groups',
			array(
				'cpl_main_options',
				'cpl_item_options',
				'cpl_item_type_options',
				'cpl_speaker_options',
				'cpl_service_type_options',
				'cpl_advanced_options',
				'cpl_podcast_options',
			)
		);
	}

	/**
	 * Post meta keys that are never exported.
	 *
	 * @return array
	 */
	public static function excluded_meta_keys() {
		return apply_filters(
			'cpl_import_export_excluded_meta_keys',
			array(
				'_edit_lock',
				'_edit_last',
				'_encloseme',
				'_pingme',
				'_wp_old_slug',
				'_wp_old_date',
				'_wp_trash_meta_status',
				'_wp_trash_meta_time',
				'_wp_desired_post_slug',
				'_thumbnail_id', // Exported separately as a URL reference.
				'_cp_import_data', // Bulky bookkeeping blob from the CSV importer.
				// Mirrors of the relationship tables, holding the *source site's* custom-table
				// ids. The Speaker/ItemType/ServiceType post types hook added_post_meta on
				// these keys, so replaying them attaches whatever record holds that id here.
				// Relations are rebuilt from the record's own relation lists instead.
				'cpl_speaker',
				'cpl_series',
				'cpl_service_type',
				self::META_ORIGINAL_ID,
				self::META_SOURCE_SITE,
				self::META_THUMB_URL,
			)
		);
	}

	/**
	 * Post meta key prefixes that are never exported.
	 *
	 * These repeater fields cache the post IDs of the site that wrote them. Replaying
	 * them verbatim would point the series and variation save handlers at whatever
	 * posts happen to hold those IDs here — the next time an admin opened the record
	 * and hit Update, unrelated content would be retitled, re-dated, reparented or
	 * (for variations) deleted outright.
	 *
	 * Nothing is lost by dropping them: series membership is rebuilt from the item
	 * relations, and variants are exported as their own child-post records.
	 *
	 * @return array
	 */
	public static function excluded_meta_prefixes() {
		return apply_filters(
			'cpl_import_export_excluded_meta_prefixes',
			array(
				'cpl_series_items',     // Series => item repeater, holds item post IDs.
				'_cpl_item_variation_', // Sermon variation repeater, holds variant post IDs.
			)
		);
	}

	/**
	 * Whether a post meta key is excluded from export and import.
	 *
	 * @param string $key Meta key.
	 * @return bool
	 */
	public static function is_excluded_meta( $key ) {
		if ( in_array( $key, self::excluded_meta_keys(), true ) ) {
			return true;
		}

		foreach ( self::excluded_meta_prefixes() as $prefix ) {
			if ( 0 === strpos( $key, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Release memory between batches so long exports/imports stay flat.
	 *
	 * WPCOM-style "stop the insanity": clear accumulated wpdb query logs and
	 * reset the runtime object cache.
	 *
	 * @return void
	 */
	public static function free_memory() {
		global $wpdb, $wp_object_cache;

		$wpdb->queries = array();

		if ( function_exists( 'wp_cache_flush_runtime' ) ) {
			wp_cache_flush_runtime();
			return;
		}

		if ( is_object( $wp_object_cache ) ) {
			// Fall back for object-cache drop-ins / older WP.
			foreach ( array( 'group_ops', 'memcache_debug', 'cache' ) as $prop ) {
				if ( property_exists( $wp_object_cache, $prop ) ) {
					$wp_object_cache->$prop = array();
				}
			}

			if ( method_exists( $wp_object_cache, '__remoteset' ) ) {
				$wp_object_cache->__remoteset();
			}
		} else {
			wp_cache_flush();
		}
	}

	/**
	 * Directory used for uploaded/generated export files. Created on demand
	 * and protected from direct listing.
	 *
	 * @return string|\WP_Error Absolute path without trailing slash.
	 */
	public static function get_working_dir() {
		$upload_dir = wp_upload_dir();

		if ( ! empty( $upload_dir['error'] ) ) {
			return new \WP_Error( 'cpl_no_upload_dir', $upload_dir['error'] );
		}

		$dir = trailingslashit( $upload_dir['basedir'] ) . 'cp-library-migration';

		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return new \WP_Error( 'cpl_no_working_dir', __( 'Could not create the working directory for import/export files.', 'cp-library' ) );
		}

		// Best-effort protection against direct access / listing.
		if ( ! file_exists( $dir . '/index.html' ) ) {
			@file_put_contents( $dir . '/index.html', '' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			@file_put_contents( $dir . '/.htaccess', "Deny from all\n" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		return $dir;
	}

	/**
	 * Delete leftover working files older than the given age.
	 *
	 * Export files are streamed to the browser and then unlinked, but a download the
	 * client aborts can kill the request mid-stream. These files hold the entire
	 * sermon library — and the plugin's option groups when settings are included — so
	 * they must not accumulate in uploads. Called before each export/upload.
	 *
	 * @param int $max_age Seconds a file may live. Default 6 hours.
	 * @return void
	 */
	public static function cleanup_stale_files( $max_age = 21600 ) {
		$dir = self::get_working_dir();

		if ( is_wp_error( $dir ) ) {
			return;
		}

		// Two globs rather than GLOB_BRACE, which musl-based PHP builds (Alpine) don't define.
		$files = array_merge( (array) glob( $dir . '/export-*' ), (array) glob( $dir . '/import-*' ) );

		if ( empty( $files ) ) {
			return;
		}

		$in_progress = get_option( Admin::STATE_OPTION );
		$active      = ! empty( $in_progress['file'] ) ? $in_progress['file'] : '';
		$cutoff      = time() - absint( $max_age );

		foreach ( $files as $file ) {
			$modified = @filemtime( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

			if ( $file === $active || ! $modified || $modified > $cutoff ) {
				continue;
			}

			@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
		}
	}

	/**
	 * Read the uncompressed size recorded in a gzip file's trailer.
	 *
	 * A truncated gzip stream ends silently — readers just stop, with no warning and
	 * no error — so a cut-short import file otherwise looks like a complete but
	 * smaller library. Comparing the bytes actually inflated against ISIZE is what
	 * catches it.
	 *
	 * @param string $file Absolute path.
	 * @return int|null ISIZE (uncompressed length modulo 2^32), or null if unreadable.
	 */
	public static function gzip_uncompressed_size( $file ) {
		$fh = fopen( $file, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( ! $fh ) {
			return null;
		}

		if ( -1 === fseek( $fh, -4, SEEK_END ) ) {
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return null;
		}

		$trailer = fread( $fh, 4 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( false === $trailer || 4 !== strlen( $trailer ) ) {
			return null;
		}

		$unpacked = unpack( 'V', $trailer );

		return $unpacked ? (int) $unpacked[1] : null;
	}

	/**
	 * Whether the inflated byte count matches what the gzip trailer promises.
	 *
	 * @param string $file  Absolute path.
	 * @param int    $bytes Uncompressed bytes actually read.
	 * @return bool True when the archive is whole (or the check is not applicable).
	 */
	public static function gzip_is_complete( $file, $bytes ) {
		if ( ! self::is_gzip( $file ) ) {
			return true;
		}

		$isize = self::gzip_uncompressed_size( $file );

		if ( null === $isize ) {
			return true;
		}

		return ( $bytes % 4294967296 ) === $isize;
	}

	/**
	 * Whether a path looks gzip-compressed (by magic bytes, not extension).
	 *
	 * @param string $file Absolute path.
	 * @return bool
	 */
	public static function is_gzip( $file ) {
		$fh = fopen( $file, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( ! $fh ) {
			return false;
		}

		$magic = fread( $fh, 2 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return "\x1f\x8b" === $magic;
	}
}
