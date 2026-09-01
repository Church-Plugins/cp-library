<?php
/**
 * Full-site import engine.
 *
 * Consumes the gzipped NDJSON files produced by the Exporter one record at a
 * time, so imports of any size run with flat memory. Callers (WP-CLI command,
 * admin AJAX ticks) own the file iteration and hand each decoded line to
 * process_record(); the importer owns idempotent writes, the old-ID => new-ID
 * map, relation wiring through the Model API, and optional media sideloading.
 *
 * @package CP_Library
 * @since   1.6.3
 */

namespace CP_Library\Admin\ImportExport;

use CP_Library\Models\Item as ItemModel;
use CP_Library\Models\ItemType as ItemTypeModel;
use CP_Library\Models\ServiceType as ServiceTypeModel;
use CP_Library\Models\Speaker as SpeakerModel;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Importer
 *
 * @since 1.6.3
 */
class Importer {

	/**
	 * Sideload media files (thumbnails, downloads) instead of keeping
	 * source-site URLs.
	 *
	 * @var bool
	 */
	protected $download_media = false;

	/**
	 * Parse and report without writing anything.
	 *
	 * @var bool
	 */
	protected $dry_run = false;

	/**
	 * Adopt pre-existing posts that merely share a slug with an imported record.
	 *
	 * Off by default: an unmarked same-slug post is far more likely to be content
	 * this site authored than a previous copy of the record being imported, and
	 * adopting it overwrites its title, content, dates and meta in place with no
	 * undo. Turn on only for round-trips (A => B => A) and re-imports over content
	 * that originally came from the source site.
	 *
	 * @var bool
	 */
	protected $match_by_slug = false;

	/**
	 * Records processed before caches are cleared.
	 *
	 * @var int
	 */
	protected $batch_size = 200;

	/**
	 * Site URL from the export header — part of the idempotency key.
	 *
	 * @var string
	 */
	protected $source_site = '';

	/**
	 * Source-site post ID => target-site post ID.
	 *
	 * @var array
	 */
	protected $post_map = array();

	/**
	 * Author login => user ID cache.
	 *
	 * @var array
	 */
	protected $author_map = array();

	/**
	 * "taxonomy:slug" => term_id cache (not persisted; rebuilt on demand).
	 *
	 * @var array
	 */
	protected $term_cache = array();

	/**
	 * Media URL => attachment ID (or false when a sideload failed).
	 *
	 * @var array
	 */
	protected $media_cache = array();

	/**
	 * Counters keyed by record type: created / updated / skipped.
	 *
	 * @var array
	 */
	protected $stats = array();

	/**
	 * Recent error messages (bounded).
	 *
	 * @var array
	 */
	protected $errors = array();

	/**
	 * Total number of errors encountered.
	 *
	 * @var int
	 */
	protected $error_count = 0;

	/**
	 * Records processed since the last cache flush.
	 *
	 * @var int
	 */
	protected $since_flush = 0;

	/**
	 * Whether begin() suspended counters/invalidation.
	 *
	 * @var bool
	 */
	protected $suspended = false;

	/**
	 * Post IDs written while cache invalidation was suspended, keyed by ID.
	 *
	 * @var array
	 */
	protected $touched_posts = array();

	/**
	 * Term IDs written while cache invalidation was suspended, keyed by taxonomy.
	 *
	 * @var array
	 */
	protected $touched_terms = array();

	/**
	 * Optional logger: fn( string $message, string $level ).
	 *
	 * @var callable|null
	 */
	protected $logger = null;

	/**
	 * Constructor.
	 *
	 * @param array $args {
	 *     @type bool     $download_media Sideload media files. Default false.
	 *     @type bool     $dry_run        Report only, write nothing. Default false.
	 *     @type bool     $match_by_slug  Adopt unmarked same-slug posts. Default false.
	 *     @type int      $batch_size     Records between cache flushes. Default 200.
	 *     @type callable $logger         Receives ( $message, $level ) for warnings/info.
	 * }
	 */
	public function __construct( $args = array() ) {
		$this->download_media = ! empty( $args['download_media'] );
		$this->dry_run        = ! empty( $args['dry_run'] );
		$this->match_by_slug  = ! empty( $args['match_by_slug'] );

		if ( ! empty( $args['batch_size'] ) ) {
			$this->batch_size = max( 10, absint( $args['batch_size'] ) );
		}

		if ( ! empty( $args['logger'] ) && is_callable( $args['logger'] ) ) {
			$this->logger = $args['logger'];
		}
	}

	/**
	 * Defer expensive side effects for the duration of a processing run.
	 * Call end() when done — including per AJAX tick.
	 *
	 * @return void
	 */
	public function begin() {
		if ( $this->suspended ) {
			return;
		}

		$this->suspended = true;

		wp_defer_term_counting( true );
		wp_defer_comment_counting( true );
		wp_suspend_cache_invalidation( true );

		if ( ! $this->dry_run && function_exists( 'kses_remove_filters' ) ) {
			// Trusted, admin-initiated migration: don't let kses mangle content
			// (WP-CLI runs without a user, which would apply anonymous kses).
			kses_remove_filters();
		}
	}

	/**
	 * Restore side effects deferred by begin().
	 *
	 * @return void
	 */
	public function end() {
		if ( ! $this->suspended ) {
			return;
		}

		$this->suspended = false;

		wp_suspend_cache_invalidation( false );
		wp_defer_term_counting( false );
		wp_defer_comment_counting( false );

		// With invalidation suspended, wp_insert_post() and wp_insert_term() skipped
		// clean_post_cache() / clean_term_cache(), and the runtime flush in
		// free_memory() only empties in-process memory. On a persistent object cache
		// (Redis, Memcached) the pre-import copies of updated posts and terms would
		// otherwise be served until they expire. Evict exactly what was written — not
		// wp_cache_flush(), which would take every other plugin's data (and, on a
		// shared backend, every other site's) with it.
		foreach ( array_keys( $this->touched_posts ) as $post_id ) {
			clean_post_cache( $post_id );
		}

		foreach ( $this->touched_terms as $taxonomy => $term_ids ) {
			clean_term_cache( array_keys( $term_ids ), $taxonomy );
		}

		$this->touched_posts = array();
		$this->touched_terms = array();

		if ( ! $this->dry_run && function_exists( 'kses_init' ) ) {
			kses_init();
		}

		Util::free_memory();
	}

	/**
	 * Decode one NDJSON line and process it.
	 *
	 * @param string $line Raw line from the export file.
	 * @return void
	 */
	public function process_line( $line ) {
		$line = trim( $line );

		if ( '' === $line ) {
			return;
		}

		$record = json_decode( $line, true );

		if ( ! is_array( $record ) || empty( $record['type'] ) ) {
			$this->add_error( 'Skipped a malformed line (invalid JSON or missing type).' );
			return;
		}

		$this->process_record( $record );
	}

	/**
	 * Process one decoded record. Errors are logged and the import continues —
	 * except header validation failures, which abort the run.
	 *
	 * @param array $record Decoded record.
	 * @return void
	 * @throws \Exception When the header record is invalid (wrong file/version).
	 */
	public function process_record( $record ) {
		$type = $record['type'];

		try {
			switch ( $type ) {
				case 'header':
					$this->handle_header( $record );
					break;

				case 'settings':
					$this->handle_settings( $record );
					break;

				case 'term':
					$this->handle_term( $record );
					break;

				default:
					$post_types = Util::record_post_types();

					if ( isset( $post_types[ $type ] ) ) {
						$this->import_post_record( $record, $post_types[ $type ] );
					} else {
						$this->bump_stat( $type, 'skipped' );
						$this->log( sprintf( 'Unknown record type "%s" skipped.', $type ), 'warning' );
					}
			}
		} catch ( \Throwable $e ) {
			if ( 'header' === $type ) {
				// Wrong file or unsupported version — callers must abort.
				throw new \Exception( $e->getMessage() );
			}

			$this->bump_stat( $type, 'errors' );
			$this->add_error( sprintf( '%s record failed: %s', $type, $e->getMessage() ) );
		}

		if ( ++$this->since_flush >= $this->batch_size ) {
			$this->since_flush = 0;
			Util::free_memory();
			$this->term_cache = array();
		}
	}

	/**
	 * Validate the header record and capture the source site URL.
	 *
	 * @param array $record Header record.
	 * @return void
	 * @throws \Exception When the file is not a supported export.
	 */
	protected function handle_header( $record ) {
		if ( ! isset( $record['format'] ) || Util::FORMAT !== $record['format'] ) {
			throw new \Exception( 'This file is not a CP Library export.' );
		}

		if ( (int) $record['format_version'] > Util::FORMAT_VERSION ) {
			throw new \Exception(
				sprintf(
					'Export format version %d is newer than this plugin supports (%d). Update CP Library on this site first.',
					(int) $record['format_version'],
					Util::FORMAT_VERSION
				)
			);
		}

		$this->source_site = isset( $record['site_url'] ) ? (string) $record['site_url'] : '';
		$this->bump_stat( 'header', 'processed' );
	}

	/**
	 * Apply exported plugin settings.
	 *
	 * @param array $record Settings record.
	 * @return void
	 */
	protected function handle_settings( $record ) {
		if ( empty( $record['options'] ) || ! is_array( $record['options'] ) ) {
			$this->bump_stat( 'settings', 'skipped' );
			return;
		}

		$allowed = Util::settings_groups();

		foreach ( $record['options'] as $group => $value ) {
			if ( ! in_array( $group, $allowed, true ) ) {
				continue;
			}

			if ( ! $this->dry_run ) {
				update_option( $group, $value );
			}
		}

		if ( ! $this->dry_run ) {
			// These groups carry the post type and taxonomy slugs. The CPTs registered
			// for this request already used the old slugs, so flushing now would just
			// rewrite the stale rules; schedule it for the next request instead.
			// Without this every sermon, series, speaker and the podcast feed 404s
			// until someone opens Settings => Permalinks and saves.
			update_option( 'cpl_flush_rewrite_rules', true );
		}

		$this->bump_stat( 'settings', $this->dry_run ? 'skipped' : 'updated' );
	}

	/**
	 * Create/update a taxonomy term by slug.
	 *
	 * @param array $record Term record.
	 * @return void
	 */
	protected function handle_term( $record ) {
		$taxonomy = isset( $record['taxonomy'] ) ? $record['taxonomy'] : '';
		$slug     = isset( $record['slug'] ) ? $record['slug'] : '';
		$name     = isset( $record['name'] ) ? $record['name'] : $slug;

		if ( ! $taxonomy || ! $slug || ! taxonomy_exists( $taxonomy ) ) {
			$this->bump_stat( 'term', 'skipped' );
			return;
		}

		$existing = get_term_by( 'slug', $slug, $taxonomy );

		if ( $this->dry_run ) {
			$this->bump_stat( 'term', $existing ? 'updated' : 'created' );
			return;
		}

		$parent_id = 0;

		if ( ! empty( $record['parent'] ) ) {
			$parent = get_term_by( 'slug', $record['parent'], $taxonomy );

			if ( $parent ) {
				$parent_id = (int) $parent->term_id;
			}
		}

		$args = array(
			'description' => isset( $record['description'] ) ? $record['description'] : '',
			'parent'      => $parent_id,
		);

		if ( $existing ) {
			wp_update_term( $existing->term_id, $taxonomy, array_merge( $args, array( 'name' => $name ) ) );
			$this->term_cache[ $taxonomy . ':' . $slug ]         = (int) $existing->term_id;
			$this->touched_terms[ $taxonomy ][ $existing->term_id ] = true;
			$this->bump_stat( 'term', 'updated' );
			return;
		}

		$result = wp_insert_term( $name, $taxonomy, array_merge( $args, array( 'slug' => $slug ) ) );

		if ( is_wp_error( $result ) ) {
			$this->bump_stat( 'term', 'errors' );
			$this->add_error( sprintf( 'Term "%s" (%s): %s', $name, $taxonomy, $result->get_error_message() ) );
			return;
		}

		$this->term_cache[ $taxonomy . ':' . $slug ]        = (int) $result['term_id'];
		$this->touched_terms[ $taxonomy ][ $result['term_id'] ] = true;
		$this->bump_stat( 'term', 'created' );
	}

	/**
	 * Import one post-based record (item / series / speaker / service_type /
	 * template).
	 *
	 * @param array  $record    Post record.
	 * @param string $post_type Target post type.
	 * @return void
	 */
	protected function import_post_record( $record, $post_type ) {
		$type        = $record['type'];
		$original_id = isset( $record['original_id'] ) ? (int) $record['original_id'] : 0;
		$post_data   = isset( $record['post'] ) && is_array( $record['post'] ) ? $record['post'] : array();

		if ( ! $original_id || empty( $post_data['title'] ) && empty( $post_data['name'] ) ) {
			$this->bump_stat( $type, 'skipped' );
			return;
		}

		$existing_id = $this->find_existing( $original_id, $post_type, isset( $post_data['name'] ) ? $post_data['name'] : '' );

		if ( $this->dry_run ) {
			$this->post_map[ $original_id ] = $existing_id ? $existing_id : 0;
			$this->bump_stat( $type, $existing_id ? 'updated' : 'created' );
			return;
		}

		$postarr = array(
			'post_type'      => $post_type,
			'post_title'     => isset( $post_data['title'] ) ? $post_data['title'] : '',
			'post_name'      => isset( $post_data['name'] ) ? $post_data['name'] : '',
			'post_status'    => isset( $post_data['status'] ) ? $post_data['status'] : 'publish',
			'post_content'   => isset( $post_data['content'] ) ? $post_data['content'] : '',
			'post_excerpt'   => isset( $post_data['excerpt'] ) ? $post_data['excerpt'] : '',
			'post_date'      => isset( $post_data['date'] ) ? $post_data['date'] : '',
			'post_date_gmt'  => isset( $post_data['date_gmt'] ) ? $post_data['date_gmt'] : '',
			'menu_order'     => isset( $post_data['menu_order'] ) ? (int) $post_data['menu_order'] : 0,
			'comment_status' => isset( $post_data['comment_status'] ) ? $post_data['comment_status'] : 'closed',
			'ping_status'    => isset( $post_data['ping_status'] ) ? $post_data['ping_status'] : 'closed',
			'post_author'    => $this->resolve_author( isset( $post_data['author_login'] ) ? $post_data['author_login'] : '' ),
			'post_parent'    => $this->resolve_parent( $record, $post_type ),
		);

		if ( $existing_id ) {
			$postarr['ID'] = $existing_id;
			// Preserve the exported publish date on updates.
			$postarr['edit_date'] = true;
		}

		$post_id = wp_insert_post( wp_slash( $postarr ), true );

		if ( is_wp_error( $post_id ) ) {
			$this->bump_stat( $type, 'errors' );
			$this->add_error( sprintf( '%s "%s": %s', $type, $postarr['post_title'], $post_id->get_error_message() ) );
			return;
		}

		$this->post_map[ $original_id ]      = (int) $post_id;
		$this->touched_posts[ (int) $post_id ] = true;

		// Idempotency markers.
		update_post_meta( $post_id, Util::META_ORIGINAL_ID, $original_id );
		update_post_meta( $post_id, Util::META_SOURCE_SITE, $this->source_site );

		$this->import_meta( $post_id, $record );
		$this->import_terms( $post_id, $record );
		$this->import_thumbnail( $post_id, $record );

		$model = $this->sync_model( $post_id, $type, $postarr );

		if ( $model ) {
			$this->import_table_meta( $model, $record );

			if ( 'item' === $type ) {
				$this->import_item_relations( $model, $record );
			}
		}

		$this->bump_stat( $type, $existing_id ? 'updated' : 'created' );
	}

	/**
	 * Locate a previously imported copy of a source post.
	 *
	 * Primary key: the original-ID marker meta scoped to the source site.
	 *
	 * An exact slug match is only adopted when the importer was created with
	 * match_by_slug — see that property for why it is off by default. Collisions are
	 * always reported so the operator can spot them either way.
	 *
	 * @param int    $original_id Source-site post ID.
	 * @param string $post_type   Post type.
	 * @param string $slug        Source post_name.
	 * @return int Existing post ID, or 0.
	 */
	protected function find_existing( $original_id, $post_type, $slug = '' ) {
		global $wpdb;

		if ( isset( $this->post_map[ $original_id ] ) && $this->post_map[ $original_id ] ) {
			return $this->post_map[ $original_id ];
		}

		$candidates = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s AND pm.meta_value = %s
				 WHERE p.post_type = %s",
				Util::META_ORIGINAL_ID,
				(string) $original_id,
				$post_type
			)
		);

		foreach ( (array) $candidates as $candidate ) {
			$candidate_source = get_post_meta( (int) $candidate, Util::META_SOURCE_SITE, true );

			if ( empty( $this->source_site ) || empty( $candidate_source ) || $candidate_source === $this->source_site ) {
				return (int) $candidate;
			}
		}

		if ( '' !== $slug ) {
			$match = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_name = %s AND post_status != 'auto-draft' LIMIT 1",
					$post_type,
					$slug
				)
			);

			if ( $match && $this->match_by_slug ) {
				$this->log(
					sprintf( 'Adopted existing %s "%s" (post %d) by slug for source record %d.', $post_type, $slug, (int) $match, $original_id ),
					'warning'
				);

				return (int) $match;
			}

			if ( $match ) {
				$this->log(
					sprintf(
						'%s "%s" (post %d) already exists on this site and was left untouched; source record %d was imported as a new post. Re-run with slug matching enabled to update it instead.',
						$post_type,
						$slug,
						(int) $match,
						$original_id
					),
					'warning'
				);
			}
		}

		return 0;
	}

	/**
	 * Map the exported parent ID (sermon variants) through the ID map.
	 *
	 * @param array  $record    Post record.
	 * @param string $post_type Post type.
	 * @return int
	 */
	protected function resolve_parent( $record, $post_type ) {
		$parent_original = isset( $record['post']['parent'] ) ? (int) $record['post']['parent'] : 0;

		if ( ! $parent_original ) {
			return 0;
		}

		if ( ! empty( $this->post_map[ $parent_original ] ) ) {
			return $this->post_map[ $parent_original ];
		}

		$found = $this->find_existing( $parent_original, $post_type );

		if ( ! $found ) {
			$this->log( sprintf( 'Parent %d not found for %s record %d; importing without parent.', $parent_original, $record['type'], $record['original_id'] ), 'warning' );
		}

		return $found;
	}

	/**
	 * Resolve an author login to a local user ID.
	 *
	 * @param string $login Source-site user login.
	 * @return int
	 */
	protected function resolve_author( $login ) {
		if ( '' === $login ) {
			return get_current_user_id();
		}

		if ( ! isset( $this->author_map[ $login ] ) ) {
			$user = get_user_by( 'login', $login );

			$this->author_map[ $login ] = $user ? (int) $user->ID : get_current_user_id();
		}

		return $this->author_map[ $login ];
	}

	/**
	 * Restore exported post meta. Only exported keys are touched — meta added
	 * on the target site under other keys is preserved.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $record  Post record.
	 * @return void
	 */
	protected function import_meta( $post_id, $record ) {
		if ( empty( $record['meta'] ) || ! is_array( $record['meta'] ) ) {
			return;
		}

		foreach ( $record['meta'] as $key => $values ) {
			// Also enforced here, not just on export: a file written before these keys
			// were excluded must not replay another site's post IDs into this one.
			if ( Util::is_excluded_meta( $key ) ) {
				continue;
			}

			delete_post_meta( $post_id, $key );

			foreach ( (array) $values as $value ) {
				if ( 'downloads' === $key && 'item' === $record['type'] ) {
					$value = $this->prepare_downloads( $post_id, $value );
				}

				add_post_meta( $post_id, wp_slash( $key ), wp_slash( $value ) );
			}
		}
	}

	/**
	 * Rewrite the downloads meta array: sideload files when requested,
	 * otherwise drop stale source-site attachment IDs and keep the URLs.
	 *
	 * @param int   $post_id   Post ID.
	 * @param mixed $downloads Exported downloads value.
	 * @return mixed
	 */
	protected function prepare_downloads( $post_id, $downloads ) {
		if ( ! is_array( $downloads ) ) {
			return $downloads;
		}

		foreach ( $downloads as &$download ) {
			if ( ! is_array( $download ) || empty( $download['file'] ) ) {
				continue;
			}

			unset( $download['file_id'] );

			$attachment_id = $this->resolve_media( $download['file'], $post_id );

			if ( $attachment_id ) {
				$url = wp_get_attachment_url( $attachment_id );

				if ( $url ) {
					$download['file']    = $url;
					$download['file_id'] = $attachment_id;
				}
			}
		}

		unset( $download );

		return $downloads;
	}

	/**
	 * Assign taxonomy terms by slug. Missing terms are created only for the
	 * plugin's own taxonomies; other taxonomies (cp_location, tags, ...) only
	 * get terms that already exist on the target site.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $record  Post record.
	 * @return void
	 */
	protected function import_terms( $post_id, $record ) {
		if ( empty( $record['terms'] ) || ! is_array( $record['terms'] ) ) {
			return;
		}

		$plugin_taxonomies = Util::plugin_taxonomies();

		foreach ( $record['terms'] as $taxonomy => $slugs ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$term_ids = array();

			foreach ( (array) $slugs as $slug ) {
				$cache_key = $taxonomy . ':' . $slug;

				if ( ! isset( $this->term_cache[ $cache_key ] ) ) {
					$term = get_term_by( 'slug', $slug, $taxonomy );

					if ( ! $term && in_array( $taxonomy, $plugin_taxonomies, true ) ) {
						// Term record missing from the file (edge case) — create from the slug.
						$created = wp_insert_term( $slug, $taxonomy, array( 'slug' => $slug ) );

						if ( ! is_wp_error( $created ) ) {
							$term = get_term( $created['term_id'], $taxonomy );

							$this->touched_terms[ $taxonomy ][ $created['term_id'] ] = true;
						}
					}

					$this->term_cache[ $cache_key ] = ( $term && ! is_wp_error( $term ) ) ? (int) $term->term_id : 0;
				}

				if ( $this->term_cache[ $cache_key ] ) {
					$term_ids[] = $this->term_cache[ $cache_key ];
				}
			}

			wp_set_object_terms( $post_id, $term_ids, $taxonomy );
		}
	}

	/**
	 * Restore the featured image. With --download-media the file is sideloaded;
	 * otherwise we first try to match an existing local attachment by URL, and
	 * fall back to storing the source URL in meta for later processing.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $record  Post record.
	 * @return void
	 */
	protected function import_thumbnail( $post_id, $record ) {
		if ( empty( $record['thumbnail']['url'] ) ) {
			return;
		}

		$url           = $record['thumbnail']['url'];
		$attachment_id = $this->resolve_media( $url, $post_id );

		if ( $attachment_id ) {
			set_post_thumbnail( $post_id, $attachment_id );
			delete_post_meta( $post_id, Util::META_THUMB_URL );

			if ( ! empty( $record['thumbnail']['alt'] ) && ! get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', wp_slash( $record['thumbnail']['alt'] ) );
			}

			return;
		}

		update_post_meta( $post_id, Util::META_THUMB_URL, esc_url_raw( $url ) );
	}

	/**
	 * Resolve a media URL to a local attachment ID.
	 *
	 * Always tries an exact local match first (free). Downloads the file only
	 * when the importer was created with download_media. Failures are cached
	 * and logged, never fatal.
	 *
	 * @param string $url     Source URL.
	 * @param int    $post_id Post the attachment is for.
	 * @return int Attachment ID, or 0.
	 */
	protected function resolve_media( $url, $post_id ) {
		if ( empty( $url ) ) {
			return 0;
		}

		if ( isset( $this->media_cache[ $url ] ) ) {
			return (int) $this->media_cache[ $url ];
		}

		// Already local (same host / previously sideloaded)?
		$local = attachment_url_to_postid( $url );

		if ( $local ) {
			$this->media_cache[ $url ] = (int) $local;
			return (int) $local;
		}

		if ( ! $this->download_media ) {
			$this->media_cache[ $url ] = 0;
			return 0;
		}

		$this->media_cache[ $url ] = $this->sideload( $url, $post_id );

		return (int) $this->media_cache[ $url ];
	}

	/**
	 * Download a remote file into the media library.
	 *
	 * @param string $url     Remote URL.
	 * @param int    $post_id Parent post.
	 * @return int Attachment ID, or 0 on failure.
	 */
	protected function sideload( $url, $post_id ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url, 120 );

		if ( is_wp_error( $tmp ) ) {
			$this->add_error( sprintf( 'Media download failed (%s): %s', $url, $tmp->get_error_message() ) );
			return 0;
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$name = sanitize_file_name( basename( $path ) );

		if ( '' === $name ) {
			$name = 'cpl-import-' . md5( $url );
		}

		$file_array = array(
			'name'     => $name,
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file_array, $post_id );

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
			$this->add_error( sprintf( 'Media sideload failed (%s): %s', $url, $attachment_id->get_error_message() ) );
			return 0;
		}

		update_post_meta( $attachment_id, Util::META_SOURCE_SITE, $this->source_site );
		update_post_meta( $attachment_id, '_cpl_import_source_url', esc_url_raw( $url ) );

		$this->touched_posts[ (int) $attachment_id ] = true;

		return (int) $attachment_id;
	}

	/**
	 * Ensure the custom-table row exists for the post and mirrors its
	 * title/status/date. Uses the plugin Model API — get_instance_from_origin()
	 * auto-creates the row, which also covers post types whose save_post hooks
	 * are not registered (module disabled on the target site).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $type    Record type.
	 * @param array  $postarr Post data used for the insert.
	 * @return \ChurchPlugins\Models\Table|null
	 */
	protected function sync_model( $post_id, $type, $postarr ) {
		$classes = array(
			'item'         => ItemModel::class,
			'series'       => ItemTypeModel::class,
			'speaker'      => SpeakerModel::class,
			'service_type' => ServiceTypeModel::class,
		);

		if ( ! isset( $classes[ $type ] ) ) {
			return null;
		}

		try {
			$model = $classes[ $type ]::get_instance_from_origin( $post_id );

			$model->update(
				array(
					'title'     => $postarr['post_title'],
					'status'    => $postarr['post_status'],
					'published' => $postarr['post_date'] ? $postarr['post_date'] : gmdate( 'Y-m-d H:i:s' ),
				)
			);

			return $model;
		} catch ( \Throwable $e ) {
			$this->add_error( sprintf( 'Model sync failed for %s %d: %s', $type, $post_id, $e->getMessage() ) );
			return null;
		}
	}

	/**
	 * Restore value-bearing custom-table meta (audio_url, video_url,
	 * enclosure, ...) through the Model API.
	 *
	 * @param \ChurchPlugins\Models\Table $model  The model.
	 * @param array                       $record Post record.
	 * @return void
	 */
	protected function import_table_meta( $model, $record ) {
		if ( empty( $record['table_meta'] ) || ! is_array( $record['table_meta'] ) ) {
			return;
		}

		foreach ( $record['table_meta'] as $key => $value ) {
			try {
				$model->update_meta_value( $key, maybe_serialize( $value ) );
			} catch ( \Throwable $e ) {
				$this->add_error( sprintf( 'Table meta "%s" failed for post %d: %s', $key, $model->origin_id, $e->getMessage() ) );
			}
		}
	}

	/**
	 * Wire series / speaker / service-type relations through the Model API so
	 * meta rows and caches stay correct. Relation targets are referenced by
	 * source-site post IDs and resolved through the ID map (falling back to
	 * the marker meta for resumed runs).
	 *
	 * @param ItemModel $item   The sermon model.
	 * @param array     $record Post record.
	 * @return void
	 */
	protected function import_item_relations( $item, $record ) {
		// Series.
		if ( cp_library()->setup->post_types->item_type_enabled() && isset( $record['series'] ) ) {
			$type_ids = array();
			$orders   = array();

			foreach ( (array) $record['series'] as $rel ) {
				$rel      = is_array( $rel ) ? $rel : array( 'id' => $rel, 'order' => 0 );
				$model_id = $this->relation_model_id( isset( $rel['id'] ) ? (int) $rel['id'] : 0, 'series' );

				if ( $model_id ) {
					$type_ids[]           = $model_id;
					$orders[ $model_id ] = isset( $rel['order'] ) ? (int) $rel['order'] : 0;
				}
			}

			try {
				$item->update_types( $type_ids );

				foreach ( $orders as $type_id => $order ) {
					if ( $order ) {
						$item->update_type_order( $type_id, $order );
					}
				}
			} catch ( \Throwable $e ) {
				$this->add_error( sprintf( 'Series relation failed for post %d: %s', $item->origin_id, $e->getMessage() ) );
			}
		}

		// Speakers.
		if ( cp_library()->setup->post_types->speaker_enabled() && isset( $record['speakers'] ) ) {
			$speaker_ids = array();

			foreach ( (array) $record['speakers'] as $original_id ) {
				$model_id = $this->relation_model_id( (int) $original_id, 'speaker' );

				if ( $model_id ) {
					$speaker_ids[] = $model_id;
				}
			}

			try {
				$item->update_speakers( $speaker_ids );
			} catch ( \Throwable $e ) {
				$this->add_error( sprintf( 'Speaker relation failed for post %d: %s', $item->origin_id, $e->getMessage() ) );
			}
		}

		// Service types (carries sermon variation sources as well).
		if ( cp_library()->setup->post_types->service_type_enabled() && isset( $record['service_types'] ) ) {
			$service_ids = array();

			foreach ( (array) $record['service_types'] as $original_id ) {
				$model_id = $this->relation_model_id( (int) $original_id, 'service_type' );

				if ( $model_id ) {
					$service_ids[] = $model_id;
				}
			}

			try {
				$item->update_service_types( $service_ids );
			} catch ( \Throwable $e ) {
				$this->add_error( sprintf( 'Service type relation failed for post %d: %s', $item->origin_id, $e->getMessage() ) );
			}
		}
	}

	/**
	 * Resolve a relation target from its source-site post ID to its
	 * custom-table model ID on this site.
	 *
	 * @param int    $original_id Source-site post ID.
	 * @param string $type        'series' | 'speaker' | 'service_type'.
	 * @return int Model ID, or 0 when unresolvable.
	 */
	protected function relation_model_id( $original_id, $type ) {
		if ( ! $original_id ) {
			return 0;
		}

		$post_types = Util::record_post_types();
		$post_id    = ! empty( $this->post_map[ $original_id ] )
			? $this->post_map[ $original_id ]
			: $this->find_existing( $original_id, $post_types[ $type ] );

		if ( ! $post_id ) {
			$this->log( sprintf( 'Could not resolve %s relation for source ID %d.', $type, $original_id ), 'warning' );
			return 0;
		}

		$this->post_map[ $original_id ] = $post_id;

		$classes = array(
			'series'       => ItemTypeModel::class,
			'speaker'      => SpeakerModel::class,
			'service_type' => ServiceTypeModel::class,
		);

		try {
			return (int) $classes[ $type ]::get_instance_from_origin( $post_id )->id;
		} catch ( \Throwable $e ) {
			$this->log( sprintf( 'Could not load %s model for post %d: %s', $type, $post_id, $e->getMessage() ), 'warning' );
			return 0;
		}
	}

	/**
	 * Increment a stats counter.
	 *
	 * @param string $type Record type.
	 * @param string $key  Counter: created|updated|skipped|errors|processed.
	 * @return void
	 */
	protected function bump_stat( $type, $key ) {
		if ( ! isset( $this->stats[ $type ] ) ) {
			$this->stats[ $type ] = array();
		}

		$this->stats[ $type ][ $key ] = isset( $this->stats[ $type ][ $key ] ) ? $this->stats[ $type ][ $key ] + 1 : 1;
	}

	/**
	 * Record an error (bounded list) and pass it to the logger.
	 *
	 * @param string $message Error message.
	 * @return void
	 */
	protected function add_error( $message ) {
		$this->error_count++;

		$this->errors[] = $message;

		if ( count( $this->errors ) > 20 ) {
			array_shift( $this->errors );
		}

		$this->log( $message, 'warning' );

		if ( function_exists( 'cp_library' ) && isset( cp_library()->logging ) ) {
			cp_library()->logging->log( 'ImportExport: ' . $message );
		}
	}

	/**
	 * Pass a message to the logger callable, when set.
	 *
	 * @param string $message Message.
	 * @param string $level   Level (info|warning).
	 * @return void
	 */
	protected function log( $message, $level = 'info' ) {
		if ( $this->logger ) {
			call_user_func( $this->logger, $message, $level );
		}
	}

	/**
	 * Export resumable state (used by the AJAX tick runner).
	 *
	 * @return array
	 */
	public function get_state() {
		return array(
			'source_site' => $this->source_site,
			'post_map'    => $this->post_map,
			'stats'       => $this->stats,
			'errors'      => $this->errors,
			'error_count' => $this->error_count,
		);
	}

	/**
	 * Restore state persisted by a previous tick.
	 *
	 * @param array $state State from get_state().
	 * @return void
	 */
	public function set_state( $state ) {
		if ( ! is_array( $state ) ) {
			return;
		}

		$this->source_site = isset( $state['source_site'] ) ? (string) $state['source_site'] : '';
		$this->post_map    = isset( $state['post_map'] ) && is_array( $state['post_map'] ) ? $state['post_map'] : array();
		$this->stats       = isset( $state['stats'] ) && is_array( $state['stats'] ) ? $state['stats'] : array();
		$this->errors      = isset( $state['errors'] ) && is_array( $state['errors'] ) ? $state['errors'] : array();
		$this->error_count = isset( $state['error_count'] ) ? (int) $state['error_count'] : 0;
	}

	/**
	 * Stats getter.
	 *
	 * @return array
	 */
	public function get_stats() {
		return $this->stats;
	}

	/**
	 * Recent errors getter.
	 *
	 * @return array
	 */
	public function get_errors() {
		return $this->errors;
	}

	/**
	 * Total error count getter.
	 *
	 * @return int
	 */
	public function get_error_count() {
		return $this->error_count;
	}
}
