<?php
/**
 * Full-site export engine.
 *
 * Streams every CP Library record (settings, taxonomy terms, speakers,
 * service types, series, templates, sermons and their variants) into a
 * gzipped NDJSON file — one JSON record per line — so arbitrarily large
 * libraries can be exported with flat memory usage.
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
 * Exporter
 *
 * @since 1.6.3
 */
class Exporter {

	/**
	 * Whether plugin settings option groups are included.
	 *
	 * @var bool
	 */
	protected $include_settings = false;

	/**
	 * Posts processed per batch before caches are cleared.
	 *
	 * @var int
	 */
	protected $batch_size = 200;

	/**
	 * Open gzip handle for the export file.
	 *
	 * @var resource|null
	 */
	protected $handle = null;

	/**
	 * Callable invoked after each record is written: fn( string $record_type ).
	 *
	 * @var callable|null
	 */
	protected $progress_callback = null;

	/**
	 * Record counts written, keyed by record type.
	 *
	 * @var array
	 */
	protected $counts = array();

	/**
	 * Cached model-id => origin post id maps for relation export.
	 *
	 * @var array
	 */
	protected $model_origin_maps = array();

	/**
	 * Constructor.
	 *
	 * @param array $args {
	 *     @type bool     $include_settings  Include plugin settings records. Default false.
	 *     @type int      $batch_size        Posts per batch. Default 200.
	 *     @type callable $progress_callback Called with the record type after each record.
	 * }
	 */
	public function __construct( $args = array() ) {
		if ( ! empty( $args['include_settings'] ) ) {
			$this->include_settings = true;
		}

		if ( ! empty( $args['batch_size'] ) ) {
			$this->batch_size = max( 10, absint( $args['batch_size'] ) );
		}

		if ( ! empty( $args['progress_callback'] ) && is_callable( $args['progress_callback'] ) ) {
			$this->progress_callback = $args['progress_callback'];
		}
	}

	/**
	 * Count every record that will be written (posts + terms), for progress UIs.
	 *
	 * @return int
	 */
	public function count_total() {
		global $wpdb;

		$total = 0;

		foreach ( Util::record_post_types() as $post_type ) {
			$total += (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status != 'auto-draft'", $post_type )
			);
		}

		foreach ( Util::plugin_taxonomies() as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$count = wp_count_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );

			if ( ! is_wp_error( $count ) ) {
				$total += (int) $count;
			}
		}

		return $total;
	}

	/**
	 * Run the export, writing a gzipped NDJSON file to $file.
	 *
	 * @param string $file Absolute destination path (conventionally .ndjson.gz).
	 * @return array Counts of written records keyed by record type.
	 * @throws \Exception When the file cannot be opened or written.
	 */
	public function export_to_file( $file ) {
		$this->handle = gzopen( $file, 'wb6' );

		if ( ! $this->handle ) {
			throw new \Exception( sprintf( 'Could not open %s for writing.', $file ) );
		}

		$this->counts = array();

		try {
			$this->write_header();

			if ( $this->include_settings ) {
				$this->export_settings();
			}

			$this->export_terms();

			foreach ( Util::record_post_types() as $record_type => $post_type ) {
				$this->export_post_type( $record_type, $post_type );
			}
		} finally {
			gzclose( $this->handle );
			$this->handle = null;
		}

		return $this->counts;
	}

	/**
	 * Write the header record.
	 *
	 * @return void
	 */
	protected function write_header() {
		$this->write_record(
			array(
				'type'             => 'header',
				'format'           => Util::FORMAT,
				'format_version'   => Util::FORMAT_VERSION,
				'plugin_version'   => defined( 'CP_LIBRARY_PLUGIN_VERSION' ) ? CP_LIBRARY_PLUGIN_VERSION : '',
				'site_url'         => get_site_url(),
				'exported_at'      => gmdate( 'c' ),
				'include_settings' => $this->include_settings,
			),
			false
		);
	}

	/**
	 * Write one settings record containing all option groups.
	 *
	 * @return void
	 */
	protected function export_settings() {
		$options = array();

		foreach ( Util::settings_groups() as $group ) {
			$value = get_option( $group, null );

			if ( null !== $value ) {
				$options[ $group ] = $value;
			}
		}

		$this->write_record(
			array(
				'type'    => 'settings',
				'options' => $options,
			)
		);
	}

	/**
	 * Write a record per term for the plugin-owned taxonomies, parents before
	 * children so import can resolve hierarchy in one pass.
	 *
	 * @return void
	 */
	protected function export_terms() {
		foreach ( Util::plugin_taxonomies() as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				)
			);

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			$by_id = array();
			foreach ( $terms as $term ) {
				$by_id[ $term->term_id ] = $term;
			}

			// Topological order: write terms whose parent is already written (or none).
			$written = array();
			$queue   = $terms;
			$guard   = count( $terms ) + 1;

			while ( ! empty( $queue ) && $guard-- > 0 ) {
				$remaining = array();

				foreach ( $queue as $term ) {
					if ( $term->parent && isset( $by_id[ $term->parent ] ) && ! isset( $written[ $term->parent ] ) ) {
						$remaining[] = $term;
						continue;
					}

					$this->write_record(
						array(
							'type'        => 'term',
							'taxonomy'    => $taxonomy,
							'slug'        => $term->slug,
							'name'        => $term->name,
							'description' => $term->description,
							'parent'      => ( $term->parent && isset( $by_id[ $term->parent ] ) ) ? $by_id[ $term->parent ]->slug : '',
						)
					);

					$written[ $term->term_id ] = true;
				}

				$queue = $remaining;
			}
		}
	}

	/**
	 * Export all posts of one type in ID-keyset batches. Two passes: parents
	 * (post_parent = 0) then children, so sermon variants always follow their
	 * parent sermon in the file.
	 *
	 * @param string $record_type NDJSON record type.
	 * @param string $post_type   WordPress post type.
	 * @return void
	 */
	protected function export_post_type( $record_type, $post_type ) {
		foreach ( array( false, true ) as $children_pass ) {
			$after_id = 0;

			while ( true ) {
				$ids = $this->get_id_batch( $post_type, $children_pass, $after_id );

				if ( empty( $ids ) ) {
					break;
				}

				foreach ( $ids as $post_id ) {
					$after_id = (int) $post_id;
					$record   = $this->build_post_record( $record_type, (int) $post_id );

					if ( $record ) {
						$this->write_record( $record );
					}
				}

				Util::free_memory();
				// Model-origin maps are cheap to keep; re-prime after flush.
				$this->model_origin_maps = array();
			}
		}
	}

	/**
	 * Fetch the next batch of post IDs using keyset pagination.
	 *
	 * @param string $post_type     Post type.
	 * @param bool   $children_pass True to fetch posts with a parent (variants).
	 * @param int    $after_id      Return IDs greater than this.
	 * @return array Array of IDs.
	 */
	protected function get_id_batch( $post_type, $children_pass, $after_id ) {
		global $wpdb;

		$parent_clause = $children_pass ? 'post_parent != 0' : 'post_parent = 0';

		return $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				 WHERE post_type = %s AND {$parent_clause} AND post_status != 'auto-draft' AND ID > %d
				 ORDER BY ID ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- clause is a fixed string.
				$post_type,
				$after_id,
				$this->batch_size
			)
		);
	}

	/**
	 * Build the exportable record for a single post.
	 *
	 * @param string $record_type Record type.
	 * @param int    $post_id     Post ID.
	 * @return array|null Record, or null when the post disappeared.
	 */
	protected function build_post_record( $record_type, $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return null;
		}

		$author = get_userdata( (int) $post->post_author );

		$record = array(
			'type'        => $record_type,
			'original_id' => $post->ID,
			'post'        => array(
				'title'          => $post->post_title,
				'name'           => $post->post_name,
				'status'         => $post->post_status,
				'date'           => $post->post_date,
				'date_gmt'       => $post->post_date_gmt,
				'content'        => $post->post_content,
				'excerpt'        => $post->post_excerpt,
				'parent'         => (int) $post->post_parent,
				'menu_order'     => (int) $post->menu_order,
				'comment_status' => $post->comment_status,
				'ping_status'    => $post->ping_status,
				'author_login'   => $author ? $author->user_login : '',
			),
			'meta'        => $this->get_exportable_meta( $post->ID ),
			'terms'       => $this->get_post_terms( $post ),
			'thumbnail'   => $this->get_thumbnail( $post->ID ),
		);

		$table_meta = $this->get_table_meta( $record_type, $post->ID );

		if ( ! empty( $table_meta ) ) {
			$record['table_meta'] = $table_meta;
		}

		if ( 'item' === $record_type ) {
			$record = array_merge( $record, $this->get_item_relations( $post->ID ) );
		}

		return apply_filters( 'cpl_import_export_post_record', $record, $post );
	}

	/**
	 * Collect exportable post meta.
	 *
	 * @param int $post_id Post ID.
	 * @return array meta_key => array of values.
	 */
	protected function get_exportable_meta( $post_id ) {
		$raw  = get_post_meta( $post_id );
		$meta = array();

		foreach ( (array) $raw as $key => $values ) {
			if ( Util::is_excluded_meta( $key ) ) {
				continue;
			}

			$meta[ $key ] = array_map( 'maybe_unserialize', (array) $values );
		}

		return $meta;
	}

	/**
	 * Collect term slugs for every taxonomy attached to the post.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array taxonomy => array of slugs.
	 */
	protected function get_post_terms( $post ) {
		$out        = array();
		$taxonomies = get_object_taxonomies( $post );

		if ( empty( $taxonomies ) ) {
			// Post type may not be registered (disabled module) — fall back to
			// every taxonomy that has assignments for this post.
			$taxonomies = Util::plugin_taxonomies();
		}

		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_the_terms( $post->ID, $taxonomy );

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			$out[ $taxonomy ] = wp_list_pluck( $terms, 'slug' );
		}

		return $out;
	}

	/**
	 * Featured image reference (URL-based, portable).
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	protected function get_thumbnail( $post_id ) {
		$thumb_id = get_post_thumbnail_id( $post_id );

		if ( ! $thumb_id ) {
			// A previous URL-only import may have stored the URL in meta.
			$url = get_post_meta( $post_id, Util::META_THUMB_URL, true );

			return $url ? array( 'original_id' => 0, 'url' => $url, 'alt' => '' ) : null;
		}

		$url = wp_get_attachment_url( $thumb_id );

		if ( ! $url ) {
			return null;
		}

		return array(
			'original_id' => (int) $thumb_id,
			'url'         => $url,
			'alt'         => (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ),
		);
	}

	/**
	 * Value-bearing custom-table meta for the record.
	 *
	 * Relation rows (item_type / source_item / source_type) are exported as
	 * explicit relations instead, so they are excluded here.
	 *
	 * @param string $record_type Record type.
	 * @param int    $post_id     Post ID.
	 * @return array key => value.
	 */
	protected function get_table_meta( $record_type, $post_id ) {
		global $wpdb;

		switch ( $record_type ) {
			case 'item':
				$table   = ItemModel::get_prop( 'table_name' );
				$meta    = ItemModel::get_prop( 'meta_table_name' );
				$col     = 'item_id';
				$exclude = array( 'item_type' );
				break;
			case 'speaker':
			case 'service_type':
				$table   = SpeakerModel::get_prop( 'table_name' );
				$meta    = SpeakerModel::get_prop( 'meta_table_name' );
				$col     = 'source_id';
				$exclude = array( 'source_type', 'source_item' );
				break;
			default:
				// Series (cpl_item_type) has no meta table; templates have none.
				return array();
		}

		$model_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE origin_id = %d LIMIT 1", $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $model_id ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $exclude ), '%s' ) );
		$args         = array_merge( array( absint( $model_id ) ), $exclude );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT `key`, `value` FROM {$meta} WHERE `{$col}` = %d AND `key` NOT IN ( {$placeholders} )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names are internal constants.
				$args
			)
		);

		$out = array();

		foreach ( (array) $rows as $row ) {
			$out[ $row->key ] = maybe_unserialize( $row->value );
		}

		return $out;
	}

	/**
	 * Series / speaker / service type relations for a sermon, expressed as
	 * source-site post IDs (portable keys resolved through the import ID map).
	 *
	 * @param int $post_id Sermon post ID.
	 * @return array
	 */
	protected function get_item_relations( $post_id ) {
		global $wpdb;

		$relations = array(
			'series'        => array(),
			'speakers'      => array(),
			'service_types' => array(),
		);

		$item_table = ItemModel::get_prop( 'table_name' );
		$item_id    = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$item_table} WHERE origin_id = %d LIMIT 1", $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $item_id ) {
			return $relations;
		}

		$item_id = absint( $item_id );

		// Series: item meta rows keyed 'item_type', with their manual order.
		$item_meta = ItemModel::get_prop( 'meta_table_name' );
		$rows      = $wpdb->get_results( $wpdb->prepare( "SELECT item_type_id, `order` FROM {$item_meta} WHERE `key` = 'item_type' AND item_id = %d", $item_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$series_map = $this->get_model_origin_map( 'series' );

		foreach ( (array) $rows as $row ) {
			if ( isset( $series_map[ (int) $row->item_type_id ] ) ) {
				$relations['series'][] = array(
					'id'    => $series_map[ (int) $row->item_type_id ],
					'order' => (int) $row->order,
				);
			}
		}

		// Speakers + service types: cp_source_meta rows keyed 'source_item'.
		$source_meta = SpeakerModel::get_prop( 'meta_table_name' );
		$rows        = $wpdb->get_results( $wpdb->prepare( "SELECT source_id, source_type_id FROM {$source_meta} WHERE `key` = 'source_item' AND item_id = %d", $item_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! empty( $rows ) ) {
			$speaker_map      = $this->get_model_origin_map( 'speaker' );
			$service_map      = $this->get_model_origin_map( 'service_type' );
			$speaker_type     = $this->get_source_type_id( 'speaker' );
			$service_type_type = $this->get_source_type_id( 'service_type' );

			foreach ( $rows as $row ) {
				$source_id = (int) $row->source_id;

				if ( (int) $row->source_type_id === $speaker_type && isset( $speaker_map[ $source_id ] ) ) {
					$relations['speakers'][] = $speaker_map[ $source_id ];
				} elseif ( (int) $row->source_type_id === $service_type_type && isset( $service_map[ $source_id ] ) ) {
					$relations['service_types'][] = $service_map[ $source_id ];
				}
			}
		}

		return $relations;
	}

	/**
	 * Resolve a cp_source_type ID without creating missing rows.
	 *
	 * @param string $key 'speaker' or 'service_type'.
	 * @return int 0 when the type row does not exist.
	 */
	protected function get_source_type_id( $key ) {
		if ( ! isset( $this->model_origin_maps[ '_type_' . $key ] ) ) {
			$type = \ChurchPlugins\Models\SourceType::get_by_title( $key );

			$this->model_origin_maps[ '_type_' . $key ] = $type ? (int) $type->id : 0;
		}

		return $this->model_origin_maps[ '_type_' . $key ];
	}

	/**
	 * Model id => origin post id map for a relation target, cached per batch.
	 *
	 * @param string $record_type 'series', 'speaker', or 'service_type'.
	 * @return array
	 */
	protected function get_model_origin_map( $record_type ) {
		if ( isset( $this->model_origin_maps[ $record_type ] ) ) {
			return $this->model_origin_maps[ $record_type ];
		}

		global $wpdb;

		if ( 'series' === $record_type ) {
			$table = ItemTypeModel::get_prop( 'table_name' );
			$rows  = $wpdb->get_results( "SELECT id, origin_id FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$post_type = 'speaker' === $record_type ? SpeakerModel::get_prop( 'post_type' ) : ServiceTypeModel::get_prop( 'post_type' );
			$table     = SpeakerModel::get_prop( 'table_name' );
			$rows      = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT s.id, s.origin_id FROM {$table} s INNER JOIN {$wpdb->posts} p ON p.ID = s.origin_id WHERE p.post_type = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$post_type
				)
			);
		}

		$map = array();

		foreach ( (array) $rows as $row ) {
			$map[ (int) $row->id ] = (int) $row->origin_id;
		}

		$this->model_origin_maps[ $record_type ] = $map;

		return $map;
	}

	/**
	 * Encode and write one NDJSON record.
	 *
	 * @param array $record        Record data (must contain `type`).
	 * @param bool  $count_progress Whether to report progress for this record.
	 * @return void
	 * @throws \Exception When the write fails.
	 */
	protected function write_record( $record, $count_progress = true ) {
		$line = wp_json_encode( $record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		if ( false === $line ) {
			// Malformed (non-UTF8) data — retry with substitution rather than dropping the record.
			$line = wp_json_encode( $record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE );
		}

		if ( false === $line || false === gzwrite( $this->handle, $line . "\n" ) ) {
			throw new \Exception( 'Failed writing to the export file (disk full?).' );
		}

		$type = isset( $record['type'] ) ? $record['type'] : 'unknown';

		$this->counts[ $type ] = isset( $this->counts[ $type ] ) ? $this->counts[ $type ] + 1 : 1;

		if ( $count_progress && $this->progress_callback ) {
			call_user_func( $this->progress_callback, $type );
		}
	}
}
