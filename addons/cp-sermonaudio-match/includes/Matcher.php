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
 * On a sermon this add-on linked, post content and scripture are left as
 * they were. Content is put back in wp_insert_post_data, which runs after
 * kses. Scripture is removed from cpl_data before load_item:
 * SermonAudio::process_cpl_data() calls update_scripture() only when that
 * key is set, and update_scripture() has no filter of its own.
 */
class Matcher {

	/**
	 * Post meta that marks a sermon this add-on linked to an existing post.
	 */
	const LINKED_META = '_cpl_sa_match_linked';

	/**
	 * Skips recorded during this request. Tests read this. The same line is
	 * written with error_log.
	 *
	 * @var array<int,array{title:string,date:string,candidate_ids:int[],reason:string}>
	 */
	public static $skipped = array();

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
		self::$logged_skip_ids = array();
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

		update_post_meta( $post_id, 'external_id', $external_id );
		update_post_meta( $post_id, self::LINKED_META, '1' );

		$item['ID'] = $post_id;

		return self::protect_linked_fields( $item, $post_id );
	}

	/**
	 * Put the linked sermon's stored content back after wp_insert_post() sanitizes.
	 *
	 * The filter receives slashed post data. WordPress unslashes it before
	 * the database write, so the stored content is slashed here. Scripture
	 * is not part of this write.
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

		if ( $post_id <= 0 || ! self::is_linked( $post_id ) ) {
			return $data;
		}

		$post_type = isset( $data['post_type'] ) ? (string) $data['post_type'] : '';

		if ( self::item_post_type() !== $post_type ) {
			return $data;
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
	 * Keep the sermon's current content in the payload and do not send scripture.
	 *
	 * Reading the post from the database means a second pass of this filter
	 * slashes the stored content once, not the value a previous pass already
	 * slashed. wp_insert_post_data() writes that same stored content again
	 * after kses, which is what actually stops the overwrite.
	 *
	 * @param array $item    Formatted sermon.
	 * @param int   $post_id Existing sermon.
	 * @return array
	 */
	private static function protect_linked_fields( array $item, $post_id ) {
		$existing = get_post( $post_id );

		if ( $existing instanceof \WP_Post ) {
			$item['post_content'] = wp_slash( $existing->post_content );
		}

		if ( isset( $item['cpl_data'] ) && is_array( $item['cpl_data'] ) ) {
			unset( $item['cpl_data']['scripture'] );
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

		$entry = array(
			'title'         => (string) $title,
			'date'          => (string) $date,
			'candidate_ids' => $ids,
			'reason'        => (string) $reason,
		);

		self::$skipped[] = $entry;

		$message = sprintf(
			'CP SermonAudio Match Existing: skipped title %s on %s; candidate IDs: %s',
			function_exists( 'wp_json_encode' ) ? wp_json_encode( $entry['title'] ) : $entry['title'],
			'' === $entry['date'] ? '(none)' : $entry['date'],
			empty( $ids ) ? '(none)' : implode( ', ', $ids )
		);

		if ( function_exists( 'cp_library' ) ) {
			$library = cp_library();

			if ( is_object( $library ) && isset( $library->logging ) && is_object( $library->logging ) && method_exists( $library->logging, 'log' ) ) {
				$library->logging->log( $message );
			}
		}

		error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
