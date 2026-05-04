<?php
/**
 * SermonAudio WP-CLI commands.
 *
 * @package CP_Library
 */

namespace CP_Library\CLI\Commands;

use CP_Library\Adapters\Init as AdaptersInit;
use WP_CLI;

/**
 * Manage SermonAudio imports from the command line.
 */
class SermonAudio {

	/**
	 * Import sermons, speakers, and series from SermonAudio.
	 *
	 * Runs synchronously: paginates through the SermonAudio API and writes
	 * directly via the adapter's load_item() path, bypassing the async
	 * dispatcher and WP_Background_Process queue used by the admin UI.
	 *
	 * Defaults to a full import (every sermon, oldest first). Use --recent
	 * to fetch only the most recently updated sermons — the same flow the
	 * cron-based update check uses.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Fetch from the SermonAudio API and report what would be imported,
	 * without writing any data.
	 *
	 * [--recent[=<count>]]
	 * : Pull only the most recently updated sermons instead of running a full
	 * import. Defaults to the "Check Count" adapter setting (typically 50).
	 * Skips the full-import prep — does not clear the queue, the change-detection
	 * store, or the in-progress flag.
	 *
	 * [--max-batches=<n>]
	 * : Stop after fetching this many batches (each batch is up to 100 sermons).
	 * Useful for smoke-testing on large broadcasters. Cannot combine with --recent.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cp sermonaudio import
	 *     wp cp sermonaudio import --dry-run
	 *     wp cp sermonaudio import --max-batches=2
	 *     wp cp sermonaudio import --recent
	 *     wp cp sermonaudio import --recent=20
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional args (unused).
	 * @param array $assoc_args Flags.
	 */
	public function import( $args, $assoc_args ) {
		$dry_run     = ! empty( $assoc_args['dry-run'] );
		$max_batches = isset( $assoc_args['max-batches'] ) ? max( 1, absint( $assoc_args['max-batches'] ) ) : 0;
		$is_recent   = isset( $assoc_args['recent'] );

		if ( $is_recent && $max_batches ) {
			WP_CLI::error( '--recent and --max-batches cannot be combined.' );
		}

		$adapter = $this->get_adapter();

		if ( ! $adapter->is_enabled() ) {
			WP_CLI::error( 'SermonAudio adapter is disabled. Enable it under Sermons → Settings → Adapters.' );
		}
		if ( empty( $adapter->get_setting( 'api_key', '' ) ) ) {
			WP_CLI::error( 'SermonAudio API key is not set. Configure it under Sermons → Settings → Adapters.' );
		}
		if ( empty( $adapter->get_setting( 'broadcaster_id', '' ) ) ) {
			WP_CLI::error( 'SermonAudio broadcaster ID is not set. Configure it under Sermons → Settings → Adapters.' );
		}

		$recent_count = 0;
		if ( $is_recent ) {
			$recent_count = is_numeric( $assoc_args['recent'] )
				? max( 1, absint( $assoc_args['recent'] ) )
				: max( 1, absint( $adapter->get_setting( 'check_count', 50 ) ) );
		}

		if ( $dry_run ) {
			WP_CLI::log( 'Dry run — no data will be written.' );
		} elseif ( ! $is_recent ) {
			$this->prepare_full_import( $adapter );
		}

		$start = microtime( true );
		$stats = array(
			'items_created'    => 0,
			'items_updated'    => 0,
			'speakers_created' => 0,
			'speakers_updated' => 0,
			'series_created'   => 0,
			'series_updated'   => 0,
			'errors'           => 0,
		);

		// Wrap in try/finally so the in_progress flag (set by full-import prep)
		// is always cleared — including when WP_CLI::error() exits mid-import.
		// PHP runs finally blocks before exit() terminates the script.
		try {
			if ( $is_recent ) {
				WP_CLI::log( sprintf( 'Fetching %d most recent sermons...', $recent_count ) );
				try {
					$items = $adapter->get_recent_items( $recent_count );
				} catch ( \ChurchPlugins\Exception | \Exception $e ) {
					WP_CLI::error( sprintf( 'API error: %s', $e->getMessage() ) );
				}
				if ( ! empty( $items ) ) {
					$this->process_batch_sync( $adapter, $items, $stats, $dry_run );
				}
			} else {
				$batch = 1;
				while ( true ) {
					WP_CLI::log( sprintf( 'Fetching batch %d...', $batch ) );

					try {
						$items = $adapter->get_next_batch( $batch );
					} catch ( \ChurchPlugins\Exception | \Exception $e ) {
						WP_CLI::error( sprintf( 'API error on batch %d: %s', $batch, $e->getMessage() ) );
					}

					if ( empty( $items ) ) {
						break;
					}

					$this->process_batch_sync( $adapter, $items, $stats, $dry_run );

					if ( $max_batches && $batch >= $max_batches ) {
						WP_CLI::log( sprintf( 'Reached --max-batches=%d, stopping.', $max_batches ) );
						break;
					}

					$batch++;
				}

				if ( ! $dry_run ) {
					update_option( "cpl_{$adapter->type}_adapter_import_complete", true );
				}
			}
		} finally {
			if ( ! $dry_run && ! $is_recent ) {
				update_option( "cpl_{$adapter->type}_adapter_import_in_progress", false );
			}
		}

		$elapsed = round( microtime( true ) - $start, 1 );

		WP_CLI::success( sprintf(
			'%s%sSermons: %d created, %d updated. Speakers: %d created, %d updated. Series: %d created, %d updated. Errors: %d. (%ss)',
			$dry_run ? '[DRY RUN] ' : '',
			$is_recent ? '[recent] ' : '',
			$stats['items_created'],
			$stats['items_updated'],
			$stats['speakers_created'],
			$stats['speakers_updated'],
			$stats['series_created'],
			$stats['series_updated'],
			$stats['errors'],
			$elapsed
		) );
	}

	/**
	 * Resolve the SermonAudio adapter or fail.
	 *
	 * @return \CP_Library\Adapters\SermonAudio
	 */
	private function get_adapter() {
		$adapters = AdaptersInit::get_instance()->get_adapters();
		if ( empty( $adapters['sermon_audio'] ) ) {
			WP_CLI::error( 'SermonAudio adapter is not registered.' );
		}
		return $adapters['sermon_audio'];
	}

	/**
	 * Mirror the prep portion of Adapter::do_full_import() — clear the queue,
	 * reset the change-detection store, and flip the import-in-progress flags
	 * so the admin UI reflects an active import.
	 *
	 * @param \CP_Library\Adapters\Adapter $adapter Adapter instance.
	 */
	private function prepare_full_import( $adapter ) {
		global $wpdb;

		$adapter->delete_all();

		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( "cpl_{$adapter->type}_adapter_store_" ) . '%'
		) );

		update_option( "cpl_{$adapter->type}_adapter_import_complete", false );
		update_option( "cpl_{$adapter->type}_adapter_import_in_progress", true );
	}

	/**
	 * Synchronously load a fetched batch.
	 *
	 * Mirrors SermonAudio::format_and_process(): groups speakers and series,
	 * loads them first so item attachments can resolve, then loads each sermon
	 * and wires up its attachments. Writes happen via Adapter::load_item();
	 * --dry-run skips the writes and just reports what would happen.
	 *
	 * @param \CP_Library\Adapters\SermonAudio $adapter Adapter instance.
	 * @param array                            $items   Sermon objects from the API.
	 * @param array                            $stats   Stats accumulator (by reference).
	 * @param bool                             $dry_run Whether to skip writes.
	 */
	private function process_batch_sync( $adapter, $items, &$stats, $dry_run ) {
		$speakers   = array();
		$item_types = array();
		$sermons    = array();

		foreach ( $items as $sermon ) {
			$formatted_item              = $adapter->format_item( $sermon );
			$formatted_item['attachments'] = array();

			if ( (bool) $sermon->speaker && cp_library()->setup->post_types->speaker_enabled() ) {
				$speakers[ $sermon->speaker->speakerID ]      = $adapter->format_speaker( $sermon->speaker );
				$formatted_item['attachments']['cpl_speaker'] = array( $sermon->speaker->speakerID );
			}

			if ( (bool) $sermon->series && cp_library()->setup->post_types->item_type_enabled() ) {
				$item_types[ $sermon->series->seriesID ]        = $adapter->format_item_type( $sermon->series );
				$formatted_item['attachments']['cpl_item_type'] = array( $sermon->series->seriesID );
			}

			$sermons[ $sermon->sermonID ] = apply_filters( 'cp_library_sermon_audio_item', $formatted_item );
		}

		// Load speakers first so item attachments can resolve them by external_id.
		foreach ( $speakers as $speaker ) {
			$this->load_one( $adapter, $speaker, 'cpl_speaker', $stats, $dry_run, 'speakers' );
		}

		foreach ( $item_types as $item_type ) {
			$this->load_one( $adapter, $item_type, 'cpl_item_type', $stats, $dry_run, 'series' );
		}

		foreach ( $sermons as $sermon_data ) {
			$attachments = isset( $sermon_data['attachments'] ) ? $sermon_data['attachments'] : array();
			unset( $sermon_data['attachments'] );

			$item_model = $this->load_one( $adapter, $sermon_data, 'cpl_item', $stats, $dry_run, 'items' );

			if ( $dry_run || ! $item_model ) {
				continue;
			}

			foreach ( $attachments as $attachment_key => $attachment ) {
				try {
					$adapter->process_attachment( $item_model, $attachment, $attachment_key );
				} catch ( \Exception | \Throwable $e ) {
					$stats['errors']++;
					WP_CLI::warning( sprintf( 'Attachment error (%s) for "%s": %s', $attachment_key, $sermon_data['post_title'], $e->getMessage() ) );
				}
			}
		}
	}

	/**
	 * Load one formatted entity (speaker / series / sermon) and update stats.
	 *
	 * @param \CP_Library\Adapters\Adapter $adapter      Adapter instance.
	 * @param array                        $formatted    Formatted entity ready for load_item().
	 * @param string                       $post_type_key Adapter key — cpl_item, cpl_speaker, cpl_item_type.
	 * @param array                        $stats        Stats accumulator (by reference).
	 * @param bool                         $dry_run      Skip writes when true.
	 * @param string                       $stat_group   'items' | 'speakers' | 'series'.
	 *
	 * @return \CP_Library\Models\Table|null Loaded model on success, null on dry-run / error.
	 */
	private function load_one( $adapter, $formatted, $post_type_key, &$stats, $dry_run, $stat_group ) {
		$title       = isset( $formatted['post_title'] ) ? $formatted['post_title'] : $formatted['external_id'];
		$existing_id = $adapter->get_item_id_from_external( $formatted['external_id'] );
		$is_update   = (bool) $existing_id;

		if ( $dry_run ) {
			WP_CLI::log( sprintf( '  %s %s: %s', $is_update ? '[update]' : '[create]', $stat_group, $title ) );
			$stats[ $stat_group . ( $is_update ? '_updated' : '_created' ) ]++;
			return null;
		}

		try {
			$model = $adapter->load_item( $formatted, $adapter->get_model_from_key( $post_type_key ) );
			$stats[ $stat_group . ( $is_update ? '_updated' : '_created' ) ]++;
			WP_CLI::log( sprintf( '  %s %s: %s', $is_update ? 'updated' : 'created', $stat_group, $title ) );
			return $model;
		} catch ( \Exception | \Throwable $e ) {
			$stats['errors']++;
			WP_CLI::warning( sprintf( 'Failed to load %s "%s": %s', $stat_group, $title, $e->getMessage() ) );
			return null;
		}
	}
}
