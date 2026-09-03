<?php
/**
 * Programmatic sermon create/update facade.
 *
 * A single, reusable entry point for other plugins ( e.g. CP Sync pulling from
 * Planning Center Publishing ) to create or update a sermon ( `cpl_item` ) together
 * with its series, speakers, media and taxonomies — without each caller having to
 * re-learn the custom-table model layer ( dual meta writes, `do_enclosure`,
 * model-id resolution, the enabled toggles ). This centralizes the logic that
 * otherwise lives inline in the CSV importer ( Admin\Import\ImportSermons ) so
 * callers do not become a third divergent copy.
 *
 * Item identity is the CALLER's responsibility: pass `ID` for an update, omit it
 * for an insert. Related records ( series, speakers ) are de-duplicated HERE by the
 * external id the caller supplies, so renames in the source system update the same
 * WordPress post instead of creating a duplicate.
 *
 * @package CP_Library
 * @since   1.6.0
 */

namespace CP_Library\Util;

use ChurchPlugins\Exception;
use CP_Library\Models\Item;
use CP_Library\Models\ItemType;
use CP_Library\Models\ServiceType;
use CP_Library\Models\Speaker;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * SermonSync
 *
 * @since 1.6.0
 */
class SermonSync {

	/**
	 * Post meta key used to track the external ( source ) id on related records
	 * ( series / speakers ) so re-syncs resolve the same post across renames.
	 *
	 * @var string
	 */
	const EXTERNAL_ID_META = '_cp_sync_source_id';

	/**
	 * Create or update a sermon and its related records.
	 *
	 * @since 1.6.0
	 *
	 * @param array $args {
	 *     Normalized sermon data.
	 *
	 *     @type int          $ID        Optional. Existing `cpl_item` post id to update. 0/absent inserts.
	 *     @type string       $source    Optional. Namespace for external ids ( e.g. 'pco' ). Default 'external'.
	 *     @type string       $title     Required. Sermon title.
	 *     @type string       $content   Optional. Sermon description / post content.
	 *     @type int          $date      Optional. Message date as a Unix timestamp. Defaults to now.
	 *     @type string       $status    Optional. Post status. Default 'publish'.
	 *     @type array|null   $series    Optional. `[ 'id' => extId, 'title' => string ]` or null.
	 *                                   May carry `thumbnail_id` ( an attachment id ) to use as the
	 *                                   series featured image; see maybe_update_series().
	 *     @type array|null   $service_type Optional. `[ 'id' => extId, 'title' => string ]` or null.
	 *                                   May carry `thumbnail_id` ( an attachment id ) to use as the
	 *                                   service type featured image, which CP Sermons serves as
	 *                                   podcast channel artwork; see maybe_update_service_type().
	 *     @type array        $speakers  Optional. List of `[ 'id' => extId, 'name' => string ]`.
	 *     @type string       $video_url Optional. Video URL.
	 *     @type string       $audio_url Optional. Audio URL.
	 *     @type array        $scripture Optional. List of passage strings.
	 *     @type array        $topics    Optional. List of topic term names.
	 *     @type array        $season    Optional. List of season term names.
	 * }
	 * @return int|\WP_Error The `cpl_item` post id, or WP_Error on failure.
	 */
	public static function upsert( $args ) {
		$args = wp_parse_args(
			$args,
			[
				'ID'        => 0,
				'source'    => 'external',
				'title'     => '',
				'content'   => '',
				'date'      => 0,
				'status'    => 'publish',
				'series'       => null,
				'service_type' => null,
				'speakers'  => [],
				'video_url' => '',
				'audio_url' => '',
				'scripture' => [],
				'topics'    => [],
				'season'    => [],
			]
		);

		$title = trim( (string) $args['title'] );

		if ( '' === $title ) {
			return new \WP_Error( 'cpl_sync_missing_title', __( 'A sermon title is required.', 'cp-library' ) );
		}

		$post_args = [
			'post_type'    => Item::get_prop( 'post_type' ),
			'post_title'   => $title,
			'post_content' => $args['content'] ? wp_kses_post( $args['content'] ) : '',
			'post_status'  => $args['status'] ?: 'publish',
		];

		if ( ! empty( $args['date'] ) ) {
			$post_args['post_date']     = gmdate( 'Y-m-d H:i:s', (int) $args['date'] );
			$post_args['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', (int) $args['date'] );
		}

		if ( ! empty( $args['ID'] ) ) {
			$post_args['ID'] = absint( $args['ID'] );
		}

		$post_id = wp_insert_post( $post_args, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		try {
			$item = Item::get_instance_from_origin( $post_id );
		} catch ( Exception $e ) {
			return new \WP_Error( 'cpl_sync_item_model', $e->getMessage() );
		}

		self::maybe_update_series( $item, $args['series'], $args['source'] );
		self::maybe_update_service_type( $item, $args['service_type'], $args['source'] );
		self::maybe_update_speakers( $item, $args['speakers'], $args['source'] );
		self::maybe_update_media( $item, $post_id, $args['video_url'], $args['audio_url'] );

		if ( ! empty( $args['scripture'] ) ) {
			try {
				$item->update_scripture( array_values( array_filter( (array) $args['scripture'] ) ) );
			} catch ( \Throwable $e ) {
				self::log( 'Could not set scripture for ' . $title . ': ' . $e->getMessage() );
			}
		}

		self::maybe_set_terms( $post_id, $args['topics'], \CP_Library\Setup\Taxonomies\Topic::get_instance()->taxonomy );
		self::maybe_set_terms( $post_id, $args['season'], \CP_Library\Setup\Taxonomies\Season::get_instance()->taxonomy );

		/**
		 * Fires after a sermon has been created/updated through the sync facade.
		 *
		 * @since 1.6.0
		 *
		 * @param int   $post_id The `cpl_item` post id.
		 * @param Item  $item    The item model.
		 * @param array $args    The normalized arguments.
		 */
		do_action( 'cpl_sermon_sync_after_upsert', $post_id, $item, $args );

		return $post_id;
	}

	/**
	 * Resolve ( create if needed ) the series and attach it to the item.
	 *
	 * @since 1.6.0
	 *
	 * @param Item       $item   The item model.
	 * @param array|null $series `[ 'id' => extId, 'title' => string, 'thumbnail_id' => int ]` or null.
	 * @param string     $source External-id namespace.
	 * @return void
	 */
	protected static function maybe_update_series( $item, $series, $source ) {
		if ( ! cp_library()->setup->post_types->item_type_enabled() ) {
			return;
		}

		if ( empty( $series ) || empty( $series['title'] ) ) {
			// No series in the source: clear any existing association.
			try {
				$item->update_types( [] );
			} catch ( \Throwable $e ) {
				self::log( 'Could not clear series: ' . $e->getMessage() );
			}
			return;
		}

		$series_post_id = self::resolve_or_create_post(
			ItemType::get_prop( 'post_type' ),
			$source . '_series_' . $series['id'],
			$series['title']
		);

		if ( ! $series_post_id ) {
			return;
		}

		self::maybe_set_thumbnail( $series_post_id, $series['thumbnail_id'] ?? 0 );

		try {
			$series_model_id = ItemType::get_instance_from_origin( $series_post_id )->id;
			$item->update_types( [ $series_model_id ] );
		} catch ( \Throwable $e ) {
			self::log( 'Could not attach series "' . $series['title'] . '": ' . $e->getMessage() );
		}
	}

	/**
	 * Resolve ( create if needed ) the service type and attach it to the item.
	 *
	 * Mirrors maybe_update_series(), but service types are Sources rather than
	 * ItemTypes, so the association goes through update_service_types(). The lazy
	 * row created by get_instance_from_origin() runs ServiceType::insert(), which
	 * calls add_type() itself — so no separate type registration is needed here.
	 *
	 * @since 1.6.3
	 *
	 * @param Item       $item         The item model.
	 * @param array|null $service_type `[ 'id' => extId, 'title' => string, 'thumbnail_id' => int ]` or null.
	 * @param string     $source       External-id namespace.
	 * @return void
	 */
	protected static function maybe_update_service_type( $item, $service_type, $source ) {
		if ( ! cp_library()->setup->post_types->service_type_enabled() ) {
			return;
		}

		if ( empty( $service_type ) || empty( $service_type['title'] ) ) {
			// No service type in the source: clear any existing association.
			try {
				$item->update_service_types( [] );
			} catch ( \Throwable $e ) {
				self::log( 'Could not clear service type: ' . $e->getMessage() );
			}
			return;
		}

		$service_type_post_id = self::resolve_or_create_post(
			ServiceType::get_prop( 'post_type' ),
			$source . '_service_type_' . $service_type['id'],
			$service_type['title']
		);

		if ( ! $service_type_post_id ) {
			return;
		}

		self::maybe_set_thumbnail( $service_type_post_id, $service_type['thumbnail_id'] ?? 0 );

		try {
			// update_service_types() takes MODEL ids, not post ids.
			$service_type_model_id = ServiceType::get_instance_from_origin( $service_type_post_id )->id;
			$item->update_service_types( [ $service_type_model_id ] );
		} catch ( \Throwable $e ) {
			self::log( 'Could not attach service type "' . $service_type['title'] . '": ' . $e->getMessage() );
		}
	}

	/**
	 * Set a related record's featured image, without overwriting an existing one.
	 *
	 * These posts are not always ours to style: resolve_or_create_post() deliberately
	 * adopts an existing post with a matching title so repeated syncs do not create
	 * duplicates, which means this may be a series or service type someone built and
	 * illustrated by hand. Only fill an empty slot — never replace artwork a human
	 * chose. That applies with particular force to service types, whose image is used
	 * as podcast channel artwork and may have been sized deliberately for Apple.
	 *
	 * @since 1.6.3
	 *
	 * @param int $post_id      The series ( `cpl_item_type` ) or service type
	 *                          ( `cpl_service_type` ) post id.
	 * @param int $thumbnail_id The attachment id to use, or 0 for none.
	 * @return void
	 */
	protected static function maybe_set_thumbnail( $post_id, $thumbnail_id ) {
		$thumbnail_id = (int) $thumbnail_id;

		if ( ! $thumbnail_id || get_post_thumbnail_id( $post_id ) ) {
			return;
		}

		set_post_thumbnail( $post_id, $thumbnail_id );
	}

	/**
	 * Resolve ( create if needed ) the speakers and attach them to the item.
	 *
	 * @since 1.6.0
	 *
	 * @param Item   $item     The item model.
	 * @param array  $speakers List of `[ 'id' => extId, 'name' => string ]`.
	 * @param string $source   External-id namespace.
	 * @return void
	 */
	protected static function maybe_update_speakers( $item, $speakers, $source ) {
		if ( ! cp_library()->setup->post_types->speaker_enabled() ) {
			return;
		}

		$speaker_ids = [];

		foreach ( (array) $speakers as $speaker ) {
			if ( empty( $speaker['name'] ) ) {
				continue;
			}

			$speaker_post_id = self::resolve_or_create_post(
				Speaker::get_prop( 'post_type' ),
				$source . '_speaker_' . $speaker['id'],
				$speaker['name']
			);

			if ( ! $speaker_post_id ) {
				continue;
			}

			try {
				$speaker_ids[] = Speaker::get_instance_from_origin( $speaker_post_id )->id;
			} catch ( \Throwable $e ) {
				self::log( 'Could not resolve speaker "' . $speaker['name'] . '": ' . $e->getMessage() );
			}
		}

		try {
			$item->update_speakers( $speaker_ids );
		} catch ( \Throwable $e ) {
			self::log( 'Could not attach speakers: ' . $e->getMessage() );
		}
	}

	/**
	 * Write video/audio URLs to both postmeta and the model meta table, mirroring the
	 * CSV importer, and refresh the podcast enclosure when audio is present.
	 *
	 * @since 1.6.0
	 *
	 * @param Item   $item      The item model.
	 * @param int    $post_id   The item post id.
	 * @param string $video_url Video URL ( empty clears ).
	 * @param string $audio_url Audio URL ( empty clears ).
	 * @return void
	 */
	protected static function maybe_update_media( $item, $post_id, $video_url, $audio_url ) {
		foreach ( [ 'video_url' => $video_url, 'audio_url' => $audio_url ] as $key => $value ) {
			$value = trim( (string) $value );

			if ( '' === $value ) {
				delete_post_meta( $post_id, $key );
				try {
					$item->delete_meta( $key );
				} catch ( \Throwable $e ) {
					self::log( "Could not clear {$key}: " . $e->getMessage() );
				}
				continue;
			}

			update_post_meta( $post_id, $key, $value );
			try {
				$item->update_meta_value( $key, $value );
			} catch ( \Throwable $e ) {
				self::log( "Could not set {$key}: " . $e->getMessage() );
			}
		}

		if ( trim( (string) $audio_url ) ) {
			try {
				( new \CP_Library\Controllers\Item( $post_id ) )->do_enclosure();
			} catch ( \Throwable $e ) {
				self::log( 'Could not write enclosure: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Find a post of the given type by its stored external id, adopting an exact
	 * title match if the external id is not yet stamped, otherwise creating it.
	 *
	 * @since 1.6.0
	 *
	 * @param string $post_type    The related post type ( series / speaker ).
	 * @param string $external_key The namespaced external id ( unique per source record ).
	 * @param string $title        The record title/name.
	 * @return int|false The post id, or false on failure.
	 */
	protected static function resolve_or_create_post( $post_type, $external_key, $title ) {
		// 1) Existing record previously synced with this external id.
		$existing = get_posts(
			[
				'post_type'        => $post_type,
				'post_status'      => 'any',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'meta_key'         => self::EXTERNAL_ID_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- bounded single-row lookup by an indexed synthetic key.
				'meta_value'       => $external_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- see above.
				'suppress_filters' => false,
			]
		);

		if ( ! empty( $existing ) ) {
			$post_id = (int) $existing[0];

			// Keep the title in sync with the source.
			if ( get_the_title( $post_id ) !== $title ) {
				wp_update_post( [ 'ID' => $post_id, 'post_title' => $title ] );
			}

			return $post_id;
		}

		// 2) Adopt an existing record with a matching title ( e.g. created by hand or a
		//    previous CSV import ) so we do not create a duplicate.
		$match = get_posts(
			[
				'post_type'        => $post_type,
				'post_status'      => 'any',
				'title'            => $title,
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'suppress_filters' => false,
			]
		);

		if ( ! empty( $match ) ) {
			$post_id = (int) $match[0];
			update_post_meta( $post_id, self::EXTERNAL_ID_META, $external_key );
			return $post_id;
		}

		// 3) Create a new record.
		$post_id = wp_insert_post(
			[
				'post_type'   => $post_type,
				'post_title'  => $title,
				'post_status' => 'publish',
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			self::log( 'Could not create ' . $post_type . ' "' . $title . '": ' . $post_id->get_error_message() );
			return false;
		}

		update_post_meta( $post_id, self::EXTERNAL_ID_META, $external_key );

		return (int) $post_id;
	}

	/**
	 * Assign taxonomy terms, creating any that are missing.
	 *
	 * @since 1.6.0
	 *
	 * @param int    $post_id  The item post id.
	 * @param array  $names    List of term names.
	 * @param string $taxonomy The taxonomy slug.
	 * @return void
	 */
	protected static function maybe_set_terms( $post_id, $names, $taxonomy ) {
		$names = array_values( array_filter( array_map( 'trim', (array) $names ) ) );

		if ( empty( $names ) || ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		$term_ids = [];

		foreach ( $names as $name ) {
			$term = get_term_by( 'name', $name, $taxonomy );

			if ( ! $term ) {
				$created = wp_insert_term( $name, $taxonomy );
				if ( is_wp_error( $created ) ) {
					continue;
				}
				$term_ids[] = (int) $created['term_id'];
			} else {
				$term_ids[] = (int) $term->term_id;
			}
		}

		if ( ! empty( $term_ids ) ) {
			wp_set_object_terms( $post_id, $term_ids, $taxonomy );
		}
	}

	/**
	 * Log a message through the CP Library logger when available.
	 *
	 * @since 1.6.0
	 *
	 * @param string $message The message.
	 * @return void
	 */
	protected static function log( $message ) {
		if ( function_exists( 'cp_library' ) && isset( cp_library()->logging ) ) {
			cp_library()->logging->log( 'SermonSync: ' . $message );
		}
	}
}
