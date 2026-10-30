<?php
/**
 * Decide whether a SermonAudio sermon is one that already exists.
 *
 * @package CP_SermonAudio_Match
 */

namespace CP_SermonAudio_Match;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Links a SermonAudio row to one existing sermon, or leaves it alone.
 *
 * Adapter::load_item() looks the sermon up by external_id meta. When that
 * meta is missing it inserts a new cpl_item, which duplicates a sermon that
 * was created earlier (a CSV import stores the preach date at 09:00;
 * SermonAudio stores the same calendar day at noon). The formatted item is
 * filtered first, on cp_library_sermon_audio_item and
 * cpl_sermon_audio_adapter_item. Setting ID there makes load_item update
 * the existing post. This add-on also writes the external_id meta and its
 * own linked meta.
 *
 * On a sermon this add-on linked, a SermonAudio save does not replace post
 * content or scripture. Content is put back in wp_insert_post_data only when
 * the item being saved carries this add-on's marker. A request-level flag is
 * not armed in the filter: a sync that fetches and then drops the item must
 * not make a later edit in that same request restore the old body. The marker
 * lives on the queued item, so a background worker still has it. Scripture is
 * removed from that same item's cpl_data. That key is not part of an admin
 * save. SermonAudio::process_cpl_data() writes scripture only when the key is
 * set, and update_scripture() has no filter of its own.
 *
 * external_id and the linked flag are not written in the filter. The filter
 * sets the item ID so load_item() updates that post, and puts the SermonAudio
 * id on the item. wp_insert_post_data stages that id only for the save that
 * carries the marker. save_post writes the metas after the row is stored. A
 * later save in the same request that does not carry the marker clears the
 * staging and does not link.
 */
class Matcher {

	/**
	 * Post meta that marks a sermon this add-on linked to an existing post.
	 */
	const LINKED_META = '_cpl_sa_match_linked';

	/**
	 * Item arg that tells wp_insert_post_data this save is a SermonAudio sync.
	 * Not post meta. Admin saves do not send it.
	 */
	const KEEP_CONTENT_ARG = '_cpl_sa_match_keep_content';

	/**
	 * Item arg carrying the SermonAudio id to store after this insert succeeds.
	 * Not post meta. Absent when the sermon was already linked.
	 */
	const PENDING_LINK_ARG = '_cpl_sa_match_pending_link';

	/**
	 * Skips recorded during this request. Tests read this. The same line is
	 * written with error_log.
	 *
	 * @var array<int,array{title:string,date:string,candidate_ids:int[],reason:string}>
	 */
	public static $skipped = array();

	/**
	 * Zero-candidate skips recorded while debug logging is on.
	 *
	 * A new sermon matches nothing. Logging that at the normal level buried
	 * the skips that name real candidates. Those stay in $skipped.
	 *
	 * @var array<int,array{title:string,date:string,candidate_ids:int[],reason:string}>
	 */
	public static $debug_skipped = array();

	/**
	 * Leftover content-shield ids. The filter does not arm this.
	 *
	 * A save whose postarr has no keep-content marker clears its id here so
	 * a sync that never saved cannot make a later edit restore old content.
	 *
	 * @var array<int,bool>
	 */
	private static $protect_content = array();

	/**
	 * SermonAudio ids staged by the save that is in wp_insert_post_data now.
	 *
	 * Staged only when that postarr carries the link marker. A save without
	 * the marker clears the id before save_post, so a failed insert cannot
	 * mark the next plain save as linked.
	 *
	 * @var array<int,string>
	 */
	private static $pending_link = array();

	/**
	 * external_id values already logged during this request.
	 *
	 * format_and_process() and the queue both filter the same sermon before
	 * anything is saved. A skip has no external_id meta yet, so the second
	 * filter would log the same title, date, and candidate IDs again.
	 *
	 * @var array<string,bool>
	 */
	private static $logged_skip_ids = array();

	/**
	 * Forget skips. Integration tests call this between cases.
	 *
	 * @return void
	 */
	public static function reset_skipped() {
		self::$skipped         = array();
		self::$debug_skipped   = array();
		self::$logged_skip_ids = array();
		self::clear_sync_flags();
	}

	/**
	 * Drop in-request sync flags. The queued item still carries its own markers.
	 *
	 * @return void
	 */
	public static function clear_sync_flags() {
		self::$protect_content = array();
		self::$pending_link    = array();
	}

	/**
	 * Filter a formatted SermonAudio item before it is inserted.
	 *
	 * Speakers and series use the adapter-item filter too. Only a sermon
	 * (cpl_item, or the renamed item post type) is considered.
	 *
	 * @param mixed $item    Formatted item.
	 * @param mixed $adapter Adapter instance, when the hook passes one.
	 * @return mixed
	 */
	public static function filter_item( $item, $adapter = null ) {
		unset( $adapter );

		if ( ! is_array( $item ) ) {
			return $item;
		}

		$post_type = self::item_post_type();

		if ( ! isset( $item['post_type'] ) || $post_type !== $item['post_type'] ) {
			return $item;
		}

		if ( ! isset( $item['external_id'] ) || '' === (string) $item['external_id'] ) {
			return $item;
		}

		$external_id = (string) $item['external_id'];
		$existing_id = self::post_id_for_external_id( $external_id );

		if ( $existing_id > 0 ) {
			if ( self::is_linked( $existing_id ) ) {
				$item['ID'] = $existing_id;
				$item       = self::protect_linked_fields( $item, $existing_id );
			}

			return $item;
		}

		$title = isset( $item['post_title'] ) && is_string( $item['post_title'] ) ? $item['post_title'] : '';
		$day   = self::calendar_day( isset( $item['post_date'] ) ? $item['post_date'] : '' );

		if ( '' === $day ) {
			self::log_skip( $external_id, $title, '', array(), 'no-date' );
			return $item;
		}

		$choice = self::choose( self::posts_on_day( $post_type, $day ), $title, $external_id );

		if ( 'link' !== $choice['action'] ) {
			self::log_skip( $external_id, $title, $day, $choice['candidate_ids'], $choice['reason'] );
			return $item;
		}

		$post_id = (int) $choice['post_id'];

		if ( ! self::post_exists( $post_id ) ) {
			self::log_skip( $external_id, $title, $day, array( $post_id ), 'missing-post' );
			return $item;
		}

		// ID only. external_id and the linked flag are written on save_post
		// after this insert is stored. The pending id travels on the item so
		// a queue worker, which does not share this request's statics, still
		// has it when load_item() runs.
		$item['ID'] = $post_id;

		return self::protect_linked_fields( $item, $post_id, $external_id );
	}

	/**
	 * Write link metas after a sync insert that carried the link marker.
	 *
	 * keep_linked_content() stages the id only for that save. A later save
	 * without the marker clears the staging before this runs, so this does
	 * not link a plain edit that follows a failed insert.
	 *
	 * @param int $post_id Saved post.
	 * @return void
	 */
	public static function finish_sync_save( $post_id ) {
		$post_id = (int) $post_id;

		if ( ! isset( self::$pending_link[ $post_id ] ) ) {
			unset( self::$protect_content[ $post_id ] );
			return;
		}

		update_post_meta( $post_id, 'external_id', self::$pending_link[ $post_id ] );
		update_post_meta( $post_id, self::LINKED_META, '1' );
		unset( self::$pending_link[ $post_id ] );
		unset( self::$protect_content[ $post_id ] );
	}

	/**
	 * Put the sermon's stored content back during a SermonAudio sync save.
	 *
	 * The filter receives slashed post data. WordPress unslashes it before
	 * the database write, so the stored content is slashed here. Only the
	 * marker on this postarr counts. If it is absent, any id left from a
	 * sync that did not finish is dropped and the save is left unchanged.
	 * Scripture is not part of this write.
	 *
	 * @param array $data    Slashed post data about to be saved.
	 * @param array $postarr Post data passed to wp_insert_post().
	 * @return array
	 */
	public static function keep_linked_content( $data, $postarr ) {
		if ( ! is_array( $data ) || ! is_array( $postarr ) ) {
			return $data;
		}

		$post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		$marked  = ! empty( $postarr[ self::KEEP_CONTENT_ARG ] );

		if ( ! $marked ) {
			if ( $post_id > 0 ) {
				unset( self::$protect_content[ $post_id ] );
				unset( self::$pending_link[ $post_id ] );
			}

			return $data;
		}

		if ( $post_id <= 0 ) {
			return $data;
		}

		$post_type = isset( $data['post_type'] ) ? (string) $data['post_type'] : '';

		if ( self::item_post_type() !== $post_type ) {
			return $data;
		}

		if ( isset( $postarr[ self::PENDING_LINK_ARG ] ) && '' !== (string) $postarr[ self::PENDING_LINK_ARG ] ) {
			self::$pending_link[ $post_id ] = (string) $postarr[ self::PENDING_LINK_ARG ];
		} else {
			unset( self::$pending_link[ $post_id ] );
		}

		$existing = get_post( $post_id );

		if ( ! $existing instanceof \WP_Post ) {
			return $data;
		}

		$data['post_content'] = wp_slash( $existing->post_content );

		return $data;
	}

	/**
	 * Pick the one post that may be linked, or refuse.
	 *
	 * $candidates are sermons already limited to one calendar day. A link
	 * happens only when exactly one of them has the same normalized title
	 * and that post has no external_id, or the same external_id. Zero
	 * matches, two or more matches, and a single match that already carries
	 * a different external_id are all skips. Candidate IDs are the title
	 * matches, sorted, including the different-id case.
	 *
	 * @param array<int,array{ID:int,post_title:string,external_id:string}> $candidates Same-day sermons.
	 * @param string                                                         $target_title fullTitle, else displayTitle.
	 * @param string                                                         $external_id  SermonAudio sermon ID.
	 * @return array{action:string,post_id:int,candidate_ids:int[],reason:string}
	 */
	public static function choose( array $candidates, $target_title, $external_id ) {
		$normalized = TitleNormalizer::normalize( $target_title );
		$matches    = array();

		foreach ( $candidates as $candidate ) {
			$candidate_title = isset( $candidate['post_title'] ) ? $candidate['post_title'] : '';

			if ( TitleNormalizer::normalize( $candidate_title ) !== $normalized ) {
				continue;
			}

			$matches[] = $candidate;
		}

		$ids = array();

		foreach ( $matches as $match ) {
			$ids[] = (int) $match['ID'];
		}

		sort( $ids );

		if ( 1 !== count( $matches ) ) {
			return array(
				'action'        => 'skip',
				'post_id'       => 0,
				'candidate_ids' => $ids,
				'reason'        => 0 === count( $matches ) ? 'none' : 'multiple',
			);
		}

		$existing_external_id = isset( $matches[0]['external_id'] ) ? (string) $matches[0]['external_id'] : '';

		if ( '' !== $existing_external_id && (string) $external_id !== $existing_external_id ) {
			return array(
				'action'        => 'skip',
				'post_id'       => 0,
				'candidate_ids' => $ids,
				'reason'        => 'different_external_id',
			);
		}

		return array(
			'action'        => 'link',
			'post_id'       => (int) $matches[0]['ID'],
			'candidate_ids' => $ids,
			'reason'        => '',
		);
	}

	/**
	 * Calendar day of a MySQL post_date, or '' when the value is not one.
	 *
	 * SermonAudio writes the preach date at 12:00:00. The CSV importer writes
	 * the same date at 09:00:00. Both strings share Y-m-d. The comparison
	 * uses that prefix and does not pass the value through a timezone, so
	 * 09:00 and 12:00 on one date match and the next date does not.
	 *
	 * @param mixed $mysql_datetime post_date value.
	 * @return string Y-m-d or ''.
	 */
	public static function calendar_day( $mysql_datetime ) {
		if ( ! is_string( $mysql_datetime ) ) {
			return '';
		}

		if ( ! preg_match( '/^(\d{4}-\d{2}-\d{2})(?:\s+\d{2}:\d{2}:\d{2})?$/', trim( $mysql_datetime ), $matches ) ) {
			return '';
		}

		return $matches[1];
	}

	/**
	 * The next calendar day, as Y-m-d, or '' when $day is not Y-m-d.
	 *
	 * @param string $day Y-m-d.
	 * @return string
	 */
	public static function next_day( $day ) {
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $day, new \DateTimeZone( 'UTC' ) );

		if ( ! $date instanceof \DateTimeImmutable || $date->format( 'Y-m-d' ) !== $day ) {
			return '';
		}

		return $date->modify( '+1 day' )->format( 'Y-m-d' );
	}

	/**
	 * cpl_item posts whose post_date falls on $day, with any external_id.
	 *
	 * Auto-drafts are skipped. Every other status is included, so a draft
	 * or private sermon from an import can still be the one match.
	 *
	 * @param string $post_type Item post type.
	 * @param string $day       Y-m-d.
	 * @return array<int,array{ID:int,post_title:string,external_id:string}>
	 */
	public static function posts_on_day( $post_type, $day ) {
		global $wpdb;

		$next = self::next_day( $day );

		if ( '' === $next ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, pm.meta_value AS external_id
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm
					ON pm.post_id = p.ID AND pm.meta_key = 'external_id'
				WHERE p.post_type = %s
					AND p.post_status <> 'auto-draft'
					AND p.post_date >= %s
					AND p.post_date < %s",
				$post_type,
				$day . ' 00:00:00',
				$next . ' 00:00:00'
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$by_id = array();

		foreach ( $rows as $row ) {
			$id = (int) $row['ID'];

			if ( ! isset( $by_id[ $id ] ) ) {
				$by_id[ $id ] = array(
					'ID'          => $id,
					'post_title'  => isset( $row['post_title'] ) ? (string) $row['post_title'] : '',
					'external_id' => '',
				);
			}

			if ( isset( $row['external_id'] ) && '' !== (string) $row['external_id'] && '' === $by_id[ $id ]['external_id'] ) {
				$by_id[ $id ]['external_id'] = (string) $row['external_id'];
			}
		}

		return array_values( $by_id );
	}

	/**
	 * Whether this add-on linked the post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_linked( $post_id ) {
		return '1' === (string) get_post_meta( $post_id, self::LINKED_META, true );
	}

	/**
	 * First post id stored under this external_id, the same lookup load_item() uses.
	 *
	 * @param string $external_id SermonAudio id.
	 * @return int
	 */
	public static function post_id_for_external_id( $external_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'external_id' AND meta_value = %s LIMIT 1",
				(string) $external_id
			)
		);

		if ( null === $post_id || '' === $post_id ) {
			return 0;
		}

		return (int) $post_id;
	}

	/**
	 * Keep the sermon's current content on this sync item, and do not send scripture.
	 *
	 * Reading the post from the database means a second pass of this filter
	 * slashes the stored content once, not the value a previous pass already
	 * slashed. The keep-content arg is what wp_insert_post_data uses to tell
	 * this sync save from an editor save. This does not arm a request flag.
	 * Scripture is dropped only from this item. An admin save never builds a
	 * cpl_data payload, so this does not shield those edits.
	 *
	 * Pass $external_id when this save should record the link afterwards.
	 * Omit it on a sermon that is already linked.
	 *
	 * @param array       $item        Formatted sermon.
	 * @param int         $post_id     Existing sermon.
	 * @param string|null $external_id SermonAudio id for a new link.
	 * @return array
	 */
	private static function protect_linked_fields( array $item, $post_id, $external_id = null ) {
		$existing = get_post( $post_id );

		if ( $existing instanceof \WP_Post ) {
			$item['post_content'] = wp_slash( $existing->post_content );
		}

		if ( isset( $item['cpl_data'] ) && is_array( $item['cpl_data'] ) ) {
			unset( $item['cpl_data']['scripture'] );
		}

		$item[ self::KEEP_CONTENT_ARG ] = 1;

		if ( null !== $external_id ) {
			$item[ self::PENDING_LINK_ARG ] = (string) $external_id;
		}

		return $item;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private static function post_exists( $post_id ) {
		$post = get_post( $post_id );

		return $post instanceof \WP_Post;
	}

	/**
	 * Item post type. Falls back to cpl_item when CP Library is not booted.
	 *
	 * @return string
	 */
	private static function item_post_type() {
		if ( function_exists( 'cp_library' ) ) {
			$library = cp_library();

			if ( is_object( $library ) && isset( $library->setup->post_types->item->post_type ) ) {
				return (string) $library->setup->post_types->item->post_type;
			}
		}

		return 'cpl_item';
	}

	/**
	 * Where a skip is written.
	 *
	 * A skip with at least one candidate (two sermons with the same title, or
	 * one that already has a different SermonAudio id) is a normal log. A
	 * skip with no candidates is the usual case for a new sermon. That one
	 * is written only when debug logging is on, and otherwise dropped.
	 *
	 * @param int[] $candidate_ids Title matches.
	 * @param bool  $debug         Whether empty skips are being recorded.
	 * @return string normal, debug, or silent.
	 */
	public static function skip_log_channel( array $candidate_ids, $debug ) {
		if ( count( $candidate_ids ) > 0 ) {
			return 'normal';
		}

		return $debug ? 'debug' : 'silent';
	}

	/**
	 * Record a skip: title, calendar day, and the candidate post IDs.
	 *
	 * @param string $external_id   SermonAudio id. One skip is logged per id per request.
	 * @param string $title         Title from the formatted item.
	 * @param string $date          Y-m-d, or '' when the item had no date.
	 * @param int[]  $candidate_ids Title matches. Empty when nothing matched.
	 * @param string $reason        none, multiple, different_external_id, no-date, or missing-post.
	 * @return void
	 */
	private static function log_skip( $external_id, $title, $date, array $candidate_ids, $reason ) {
		if ( isset( self::$logged_skip_ids[ $external_id ] ) ) {
			return;
		}

		self::$logged_skip_ids[ $external_id ] = true;

		$ids = array_map( 'intval', $candidate_ids );
		sort( $ids );

		$channel = self::skip_log_channel( $ids, self::debug_logging_enabled() );

		if ( 'silent' === $channel ) {
			return;
		}

		$entry = array(
			'title'         => (string) $title,
			'date'          => (string) $date,
			'candidate_ids' => $ids,
			'reason'        => (string) $reason,
		);

		if ( 'debug' === $channel ) {
			self::$debug_skipped[] = $entry;
			self::write_skip_log( $entry, false );
			return;
		}

		self::$skipped[] = $entry;
		self::write_skip_log( $entry, true );
	}

	/**
	 * Empty skips follow WP_DEBUG unless a filter says otherwise.
	 *
	 * @return bool
	 */
	private static function debug_logging_enabled() {
		$enabled = defined( 'WP_DEBUG' ) && WP_DEBUG;

		if ( function_exists( 'apply_filters' ) ) {
			$enabled = (bool) apply_filters( 'cpl_sa_match_log_empty_skips', $enabled );
		}

		return $enabled;
	}

	/**
	 * @param array{title:string,date:string,candidate_ids:int[],reason:string} $entry Skip record.
	 * @param bool                                                               $normal True for the always-on log.
	 * @return void
	 */
	private static function write_skip_log( array $entry, $normal ) {
		$ids     = $entry['candidate_ids'];
		$message = sprintf(
			'CP SermonAudio Match Existing: skipped title %s on %s; candidate IDs: %s',
			function_exists( 'wp_json_encode' ) ? wp_json_encode( $entry['title'] ) : $entry['title'],
			'' === $entry['date'] ? '(none)' : $entry['date'],
			empty( $ids ) ? '(none)' : implode( ', ', $ids )
		);

		if ( $normal && function_exists( 'cp_library' ) ) {
			$library = cp_library();

			if ( is_object( $library ) && isset( $library->logging ) && is_object( $library->logging ) && method_exists( $library->logging, 'log' ) ) {
				$library->logging->log( $message );
			}
		}

		error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
