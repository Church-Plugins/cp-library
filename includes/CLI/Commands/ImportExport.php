<?php
/**
 * Full-site export/import WP-CLI commands.
 *
 * @package CP_Library
 */

namespace CP_Library\CLI\Commands;

use CP_Library\Admin\ImportExport\Exporter;
use CP_Library\Admin\ImportExport\Importer;
use CP_Library\Admin\ImportExport\Util;
use WP_CLI;

/**
 * Export and import the full CP Library data set (sermons, series, speakers,
 * service types, variations, taxonomies and settings) for site-to-site
 * migration. Both directions stream a gzipped NDJSON file in batches, so
 * libraries with tens of thousands of sermons run with flat memory.
 */
class ImportExport {

	/**
	 * Export all CP Library content to a portable file.
	 *
	 * Writes gzipped NDJSON (one JSON record per line): a header record, then
	 * optional settings, taxonomy terms, speakers, service types, series,
	 * templates and sermons (variants after their parents). Sermon relations
	 * are stored as source-site post IDs, which the importer maps to new IDs.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Destination path. The conventional extension is .ndjson.gz.
	 *
	 * [--include-settings]
	 * : Also export the plugin settings option groups (general, sermon,
	 * series, speaker, service type, advanced, podcast).
	 *
	 * [--batch-size=<n>]
	 * : Posts processed per batch before caches are cleared. Default 200.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cpl export ~/sermons.ndjson.gz
	 *     wp cpl export ~/sermons.ndjson.gz --include-settings
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Flags.
	 */
	public function export( $args, $assoc_args ) {
		$file = $args[0];

		$dir = dirname( $file );
		if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
			WP_CLI::error( sprintf( 'Directory %s does not exist or is not writable.', $dir ) );
		}

		$total    = ( new Exporter() )->count_total();
		$progress = \WP_CLI\Utils\make_progress_bar( sprintf( 'Exporting %d records', $total ), $total );

		$exporter = new Exporter(
			array(
				'include_settings'  => ! empty( $assoc_args['include-settings'] ),
				'batch_size'        => isset( $assoc_args['batch-size'] ) ? absint( $assoc_args['batch-size'] ) : 0,
				'progress_callback' => function () use ( $progress ) {
					$progress->tick();
				},
			)
		);

		$start = microtime( true );

		try {
			$counts = $exporter->export_to_file( $file );
		} catch ( \Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		$progress->finish();

		$summary = array();
		foreach ( $counts as $type => $count ) {
			if ( 'header' === $type ) {
				continue;
			}
			$summary[] = sprintf( '%s: %d', $type, $count );
		}

		WP_CLI::success(
			sprintf(
				'Exported to %s (%s) in %ss — %s.',
				$file,
				size_format( (int) filesize( $file ) ),
				round( microtime( true ) - $start, 1 ),
				implode( ', ', $summary )
			)
		);
	}

	/**
	 * Import a CP Library export file.
	 *
	 * Idempotent: records are matched to previously imported content by their
	 * source-site post ID (stored in post meta together with the source site
	 * URL) and updated in place — importing the same file twice does not
	 * duplicate content. Relations (series, speakers, service types, sermon
	 * variants) are rewired through the plugin's model API.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to a .ndjson.gz (or uncompressed .ndjson) export file.
	 *
	 * [--download-media]
	 * : Sideload featured images and downloadable files into this site's
	 * media library. Without it, media references keep pointing at the
	 * source site. Failures are logged and skipped, never fatal.
	 *
	 * [--dry-run]
	 * : Parse the file and report what would be created/updated without
	 * writing anything.
	 *
	 * [--match-by-slug]
	 * : Also adopt existing posts that merely share a URL slug with an
	 * imported record, overwriting them in place. Off by default: an unmarked
	 * same-slug post is usually content this site authored, and overwriting it
	 * cannot be undone. Use for round-trips (A => B => A) and re-imports over
	 * content that originally came from the source site.
	 *
	 * [--batch-size=<n>]
	 * : Records processed between cache flushes. Default 200.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cpl import ~/sermons.ndjson.gz --dry-run
	 *     wp cpl import ~/sermons.ndjson.gz
	 *     wp cpl import ~/sermons.ndjson.gz --download-media
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Flags.
	 */
	public function import( $args, $assoc_args ) {
		$file = $args[0];

		if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
			WP_CLI::error( sprintf( 'File %s does not exist or is not readable.', $file ) );
		}

		$dry_run = ! empty( $assoc_args['dry-run'] );

		if ( $dry_run ) {
			WP_CLI::log( 'Dry run — no data will be written.' );
		}

		// First pass: count lines for the progress bar (gzopen also reads
		// uncompressed files transparently) and confirm the archive is whole.
		$total  = 0;
		$bytes  = 0;
		$handle = gzopen( $file, 'rb' );

		if ( ! $handle ) {
			WP_CLI::error( sprintf( 'Could not open %s.', $file ) );
		}

		while ( false !== ( $line = gzgets( $handle, 16777216 ) ) ) {
			$bytes += strlen( $line );
			$total++;
		}
		gzclose( $handle );

		if ( ! $total ) {
			WP_CLI::error( 'The file is empty.' );
		}

		// A truncated gzip stream just stops early — without this the import would run
		// over a partial file and report success on a fraction of the library.
		if ( ! Util::gzip_is_complete( $file, $bytes ) ) {
			WP_CLI::error( sprintf( '%s is incomplete — it was cut short in transfer or when it was created. Re-create the export and try again.', $file ) );
		}

		$importer = new Importer(
			array(
				'download_media' => ! empty( $assoc_args['download-media'] ),
				'dry_run'        => $dry_run,
				'match_by_slug'  => ! empty( $assoc_args['match-by-slug'] ),
				'batch_size'     => isset( $assoc_args['batch-size'] ) ? absint( $assoc_args['batch-size'] ) : 0,
				'logger'         => function ( $message, $level ) {
					if ( 'warning' === $level ) {
						WP_CLI::warning( $message );
					} else {
						WP_CLI::debug( $message, 'cpl' );
					}
				},
			)
		);

		$progress = \WP_CLI\Utils\make_progress_bar( sprintf( 'Importing %d records', $total ), $total );
		$start    = microtime( true );
		$handle   = gzopen( $file, 'rb' );

		if ( ! $handle ) {
			WP_CLI::error( sprintf( 'Could not open %s.', $file ) );
		}

		$importer->begin();

		try {
			while ( false !== ( $line = gzgets( $handle, 16777216 ) ) ) {
				$importer->process_line( $line );
				$progress->tick();
			}
		} catch ( \Exception $e ) {
			// Invalid header (wrong file / newer format) aborts the run.
			WP_CLI::error( $e->getMessage() );
		} finally {
			gzclose( $handle );
			$importer->end();
		}

		$progress->finish();

		$stats   = $importer->get_stats();
		$summary = array();

		foreach ( $stats as $type => $counts ) {
			if ( 'header' === $type ) {
				continue;
			}

			$parts = array();
			foreach ( array( 'created', 'updated', 'skipped', 'errors' ) as $key ) {
				if ( ! empty( $counts[ $key ] ) ) {
					$parts[] = sprintf( '%d %s', $counts[ $key ], $key );
				}
			}

			if ( $parts ) {
				$summary[] = sprintf( '%s (%s)', $type, implode( ', ', $parts ) );
			}
		}

		$message = sprintf(
			'%sImport finished in %ss — %s. Errors: %d.',
			$dry_run ? '[DRY RUN] ' : '',
			round( microtime( true ) - $start, 1 ),
			$summary ? implode( '; ', $summary ) : 'nothing to do',
			$importer->get_error_count()
		);

		if ( $importer->get_error_count() ) {
			WP_CLI::warning( $message );
			foreach ( $importer->get_errors() as $error ) {
				WP_CLI::log( '  - ' . $error );
			}
		} else {
			WP_CLI::success( $message );
		}

		// Imported settings can change the post type and taxonomy slugs, but the CPTs
		// for this run already registered with the old ones. The flush is queued for
		// the next request rather than rewriting the stale rules now.
		if ( get_option( 'cpl_flush_rewrite_rules' ) ) {
			WP_CLI::log( 'Plugin settings were imported. Permalinks will rebuild automatically on the next page load; run `wp rewrite flush` to do it now.' );
		}
	}
}
