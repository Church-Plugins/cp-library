<?php
/**
 * Match a SermonAudio sermon to one existing sermon on the same local day.
 *
 * SermonAudio sync used to insert a new cpl_item whenever no post already
 * stored that sermonID as external_id. A sermon typed in by hand, or brought
 * in from a CSV, has no external_id, so the next sync created a second post
 * for the same service. This class decides whether exactly one existing
 * sermon is that service. The SQL that loads the day's posts is separate
 * from the decision, so the decision can be tested without the database.
 *
 * @package CP_Library
 * @since 1.8.2
 */

namespace CP_Library\Adapters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Title-and-day matching for the SermonAudio adapter.
 *
 * @since 1.8.2
 */
class SermonAudioMatch {

	/**
	 * Post meta set on a sermon this matcher linked.
	 *
	 * Later syncs of that sermon keep non-empty local content and scripture.
	 * A sermon that already had this SermonAudio id, and was never matched
	 * here, does not have the meta and still takes SermonAudio's copy.
	 */
	const LINKED_META = '_cpl_sa_match_linked';

	/**
	 * Snapshot of the notes and scripture SermonAudio last supplied.
	 *
	 * A later sync updates or clears one of those fields only when the feed
	 * value changed and the sermon still has the snapshotted value. A local
	 * edit does not match the snapshot, so it is left alone. An empty feed
	 * value does not erase a local value that SermonAudio did not supply.
	 */
	const SUPPLIED_META = '_cpl_sa_supplied';

	/**
	 * Item flag set when this sync batch has two sermons with the same
	 * normalized title on the same site day.
	 *
	 * Not post meta. load_item() removes it before the post is saved. A
	 * sermon that already has this SermonAudio id still updates.
	 */
	const BATCH_AMBIGUOUS = '_cpl_sa_match_batch_ambiguous';

	/**
	 * Set on a formatted sermon whose post_date came from publishTimestamp.
	 *
	 * The site-local value is written only when the sermon is inserted.
	 * An update keeps the post_date already stored.
	 */
	const TIMESTAMP_DATE = '_cpl_sa_timestamp_date';

	/**
	 * Comparison form of a title.
	 *
	 * Order is fixed: decode HTML entities once, trim, lowercase, collapse
	 * whitespace. A second decode is not applied, and punctuation is left in
	 * place, so "Hello!" and "Hello" stay different. A non-string, an empty
	 * string, and invalid UTF-8 are empty, and an empty result never matches.
	 *
	 * @param mixed $title Raw title.
	 * @return string
	 */
	public static function normalize_title( $title ) {
		if ( ! is_string( $title ) || '' === $title ) {
			return '';
		}

		if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $title, 'UTF-8' ) ) {
			return '';
		}

		$title = html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$title = trim( $title );

		if ( function_exists( 'mb_strtolower' ) ) {
			$title = mb_strtolower( $title, 'UTF-8' );
		} else {
			$title = strtolower( $title );
		}

		$collapsed = preg_replace( '/\s+/u', ' ', $title );

		return is_string( $collapsed ) ? $collapsed : '';
	}

	/**
	 * Site-local calendar day of a MySQL post_date, or '' when it is not one.
	 *
	 * post_date is already the site timezone. The Y-m-d prefix is that
	 * calendar day. The value is not converted through GMT, so 23:30 and
	 * 09:00 on the same date match and the next date does not.
	 *
	 * @param mixed $mysql_datetime post_date value.
	 * @return string Y-m-d, or ''.
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
	 * The next calendar day, as Y-m-d, or '' when $day is not a real date.
	 *
	 * Date math runs in UTC so a DST transition cannot skip or repeat a day.
	 * The result is only used as the exclusive upper bound on post_date,
	 * which is a wall-clock string in the site timezone.
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
	 * The one post that may be linked, or null to create a new sermon.
	 *
	 * $candidates are sermons already limited to one calendar day. A link
	 * requires exactly one candidate with the same normalized title. Media
	 * can only keep that candidate or remove it: the same file confirms it,
	 * and the same kind of file on one host with a different path removes
	 * it. A shared file does not link a sermon whose title differs. Two or
	 * more that still qualify, and a post that already has a different
	 * external id, are not linked.
	 *
	 * @param array<int,array{ID:int,post_title:string,external_id?:string,media?:array}> $candidates Same-day sermons.
	 * @param mixed                                                                       $incoming_title Raw title.
	 * @param string                                                                      $incoming_external_id SermonAudio sermon ID.
	 * @param array                                                                       $incoming_media audio, video, and redirect URL lists.
	 * @return int|null
	 */
	public static function select_match( array $candidates, $incoming_title, $incoming_external_id, array $incoming_media = array() ) {
		$target = self::normalize_title( $incoming_title );

		if ( '' === $target ) {
			return null;
		}

		$matches = array();

		foreach ( $candidates as $candidate ) {
			$candidate_title = isset( $candidate['post_title'] ) ? $candidate['post_title'] : '';

			if ( self::normalize_title( $candidate_title ) !== $target ) {
				continue;
			}

			$local_media = isset( $candidate['media'] ) && is_array( $candidate['media'] ) ? $candidate['media'] : array();

			if ( 'block' === self::media_relation( $local_media, $incoming_media ) ) {
				continue;
			}

			$matches[] = $candidate;
		}

		if ( 1 !== count( $matches ) ) {
			return null;
		}

		return self::linkable_id( $matches[0], $incoming_external_id );
	}

	/**
	 * Whether two sermons' audio or video is the same file, a conflict, or neither.
	 *
	 * Forms compared are audio_url, video_url, and a stored redirect target.
	 * Scheme, a leading www, the query string, and a trailing slash are
	 * ignored. One shared form is the same media. A conflict is only a pair
	 * of the same kind on one host whose paths name different files.
	 * Different hosts, and a side with no URL of that kind, are neither.
	 *
	 * @param array $local    audio, video, and redirect URL lists.
	 * @param array $incoming audio, video, and redirect URL lists.
	 * @return string same, block, or neutral.
	 */
	public static function media_relation( array $local, array $incoming ) {
		$local_ids    = self::media_identities( $local );
		$incoming_ids = self::media_identities( $incoming );

		foreach ( $local_ids as $identity => $_kept ) {
			if ( isset( $incoming_ids[ $identity ] ) ) {
				return 'same';
			}
		}

		foreach ( array( 'audio', 'video' ) as $kind ) {
			if ( self::kind_conflicts( $local, $incoming, $kind ) ) {
				return 'block';
			}
		}

		return 'neutral';
	}

	/**
	 * Host and path used to compare a media URL, or null when it is not one.
	 *
	 * @param mixed $url Raw URL, embed HTML, or the "found" redirect sentinel.
	 * @return array{host:string,path:string}|null
	 */
	public static function media_parts( $url ) {
		if ( ! is_string( $url ) ) {
			return null;
		}

		$url = trim( $url );

		if ( '' === $url || 'found' === $url ) {
			return null;
		}

		$parsed = parse_url( $url );

		if ( ! is_array( $parsed ) || empty( $parsed['host'] ) || ! is_string( $parsed['host'] ) ) {
			return null;
		}

		$host = strtolower( $parsed['host'] );

		if ( 0 === strpos( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}

		$path = isset( $parsed['path'] ) && is_string( $parsed['path'] ) ? strtolower( $parsed['path'] ) : '';

		return array(
			'host' => $host,
			'path' => rtrim( $path, '/' ),
		);
	}

	/**
	 * Post id to link when it has no other external id.
	 *
	 * @param array  $candidate             One same-day sermon.
	 * @param string $incoming_external_id  SermonAudio sermon ID.
	 * @return int|null
	 */
	private static function linkable_id( array $candidate, $incoming_external_id ) {
		$existing_external_id = isset( $candidate['external_id'] ) ? trim( (string) $candidate['external_id'] ) : '';

		if ( '' !== $existing_external_id && (string) $incoming_external_id !== $existing_external_id ) {
			return null;
		}

		$id = isset( $candidate['ID'] ) ? (int) $candidate['ID'] : 0;

		return $id > 0 ? $id : null;
	}

	/**
	 * Identity strings for every usable URL on one side.
	 *
	 * @param array $side audio, video, and redirect lists.
	 * @return array<string,bool>
	 */
	private static function media_identities( array $side ) {
		$identities = array();

		foreach ( array( 'audio', 'video', 'redirect' ) as $kind ) {
			foreach ( self::url_list( $side, $kind ) as $url ) {
				$parts = self::media_parts( $url );

				if ( null === $parts ) {
					continue;
				}

				$identities[ $parts['host'] . $parts['path'] ] = true;
			}
		}

		return $identities;
	}

	/**
	 * Whether this kind of media is on one host and names two different files.
	 *
	 * @param array  $local    Local URL lists.
	 * @param array  $incoming Incoming URL lists.
	 * @param string $kind     audio or video.
	 * @return bool
	 */
	private static function kind_conflicts( array $local, array $incoming, $kind ) {
		$left  = self::parts_list( $local, $kind );
		$right = self::parts_list( $incoming, $kind );

		if ( empty( $left ) || empty( $right ) ) {
			return false;
		}

		$hosts       = array();
		$left_paths  = array();
		$right_paths = array();

		foreach ( $left as $part ) {
			$hosts[ $part['host'] ]      = true;
			$left_paths[ $part['path'] ] = true;
		}

		foreach ( $right as $part ) {
			$hosts[ $part['host'] ]       = true;
			$right_paths[ $part['path'] ] = true;
		}

		if ( 1 !== count( $hosts ) ) {
			return false;
		}

		foreach ( $left_paths as $path => $_kept ) {
			if ( isset( $right_paths[ $path ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Parsed URLs of one kind.
	 *
	 * @param array  $side URL lists.
	 * @param string $kind audio, video, or redirect.
	 * @return array<int,array{host:string,path:string}>
	 */
	private static function parts_list( array $side, $kind ) {
		$parts = array();

		foreach ( self::url_list( $side, $kind ) as $url ) {
			$parsed = self::media_parts( $url );

			if ( null !== $parsed ) {
				$parts[] = $parsed;
			}
		}

		return $parts;
	}

	/**
	 * @param array  $side URL lists.
	 * @param string $kind audio, video, or redirect.
	 * @return string[]
	 */
	private static function url_list( array $side, $kind ) {
		if ( ! isset( $side[ $kind ] ) ) {
			return array();
		}

		$urls = $side[ $kind ];

		if ( is_string( $urls ) ) {
			$urls = array( $urls );
		}

		if ( ! is_array( $urls ) ) {
			return array();
		}

		$list = array();

		foreach ( $urls as $url ) {
			if ( is_string( $url ) && '' !== trim( $url ) ) {
				$list[] = $url;
			}
		}

		return $list;
	}

	/**
	 * Audio and video on the formatted SermonAudio item.
	 *
	 * @param array $item Formatted sermon.
	 * @return array{audio:string[],video:string[],redirect:array}
	 */
	private static function incoming_media( array $item ) {
		$meta = isset( $item['meta_input'] ) && is_array( $item['meta_input'] ) ? $item['meta_input'] : array();

		return array(
			'audio'    => array( isset( $meta['audio_url'] ) ? (string) $meta['audio_url'] : '' ),
			'video'    => array( isset( $meta['video_url'] ) ? (string) $meta['video_url'] : '' ),
			'redirect' => array(),
		);
	}

	/**
	 * Add audio, video, and redirect URLs stored for each candidate.
	 *
	 * CSV import and the sermon editor write audio_url and video_url to post
	 * meta and to the item meta table. SermonAudio writes those same keys to
	 * the item meta table. Playback of a SermonAudio video can also store the
	 * CDN target in the _video_url_redirect post meta. The day's posts are
	 * few, so this reads only those ids.
	 *
	 * @param array $candidates Same-day sermons.
	 * @return array
	 */
	private static function with_stored_media( array $candidates ) {
		if ( empty( $candidates ) ) {
			return $candidates;
		}

		global $wpdb;

		if ( ! isset( $wpdb->postmeta ) ) {
			return $candidates;
		}

		$ids = array();

		foreach ( $candidates as $candidate ) {
			$id = isset( $candidate['ID'] ) ? (int) $candidate['ID'] : 0;

			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		$ids = array_values( array_unique( $ids ) );

		if ( empty( $ids ) ) {
			return $candidates;
		}

		$id_sql = implode( ',', $ids );
		$stored = array();
		$rows   = $wpdb->get_results(
			"SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ( {$id_sql} ) AND meta_key IN ( 'audio_url', 'video_url', '_video_url_redirect' )",
			ARRAY_A
		);

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				self::remember_media( $stored, isset( $row['post_id'] ) ? (int) $row['post_id'] : 0, isset( $row['meta_key'] ) ? (string) $row['meta_key'] : '', isset( $row['meta_value'] ) ? (string) $row['meta_value'] : '' );
			}
		}

		if ( class_exists( '\CP_Library\Models\Item' ) ) {
			$meta_table = \CP_Library\Models\Item::get_prop( 'meta_table_name' );
			$item_table = \CP_Library\Models\Item::get_prop( 'table_name' );

			if ( is_string( $meta_table ) && '' !== $meta_table && is_string( $item_table ) && '' !== $item_table ) {
				$table_rows = $wpdb->get_results(
					"SELECT i.origin_id AS post_id, m.`key` AS meta_key, m.`value` AS meta_value FROM {$meta_table} m INNER JOIN {$item_table} i ON i.id = m.item_id WHERE i.origin_id IN ( {$id_sql} ) AND m.`key` IN ( 'audio_url', 'video_url' )",
					ARRAY_A
				);

				if ( is_array( $table_rows ) ) {
					foreach ( $table_rows as $row ) {
						self::remember_media( $stored, isset( $row['post_id'] ) ? (int) $row['post_id'] : 0, isset( $row['meta_key'] ) ? (string) $row['meta_key'] : '', isset( $row['meta_value'] ) ? (string) $row['meta_value'] : '' );
					}
				}
			}
		}

		foreach ( $candidates as $index => $candidate ) {
			$id = isset( $candidate['ID'] ) ? (int) $candidate['ID'] : 0;
			$candidates[ $index ]['media'] = isset( $stored[ $id ] ) ? $stored[ $id ] : array(
				'audio'    => array(),
				'video'    => array(),
				'redirect' => array(),
			);
		}

		return $candidates;
	}

	/**
	 * @param array  $stored  Post id => media lists.
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param string $value   Meta value.
	 */
	private static function remember_media( array &$stored, $post_id, $key, $value ) {
		if ( $post_id <= 0 || '' === trim( $value ) ) {
			return;
		}

		if ( ! isset( $stored[ $post_id ] ) ) {
			$stored[ $post_id ] = array(
				'audio'    => array(),
				'video'    => array(),
				'redirect' => array(),
			);
		}

		if ( 'audio_url' === $key ) {
			$stored[ $post_id ]['audio'][] = $value;
		} elseif ( 'video_url' === $key ) {
			$stored[ $post_id ]['video'][] = $value;
		} elseif ( '_video_url_redirect' === $key ) {
			$stored[ $post_id ]['redirect'][] = $value;
		}
	}

	/**
	 * Value to store for one supplied field, and whether the sermon should take it.
	 *
	 * With no snapshot yet, a non-empty local value is locally owned and is
	 * never taken from the feed. An empty local value is filled from the
	 * feed on this sync. After that, only a field SermonAudio filled is
	 * updated or cleared, and only when the feed changed and the local
	 * value still equals the value stored after save filters. A locally
	 * owned field is never changed.
	 *
	 * @param mixed $local            Current local value.
	 * @param mixed $feed             Value on this SermonAudio item.
	 * @param bool  $has_snapshot     Whether this sermon has a supplied snapshot.
	 * @param mixed $previous_feed    Feed value last recorded.
	 * @param mixed $previous_stored  Value read back after the last save.
	 * @param bool  $locally_owned    Whether the field belonged to the site at link time.
	 * @return array{value:string,apply:bool,owned:bool}
	 */
	public static function reconcile_supplied_field( $local, $feed, $has_snapshot, $previous_feed, $previous_stored, $locally_owned = false ) {
		$local_text = self::blank_text( $local );
		$feed_text  = self::blank_text( $feed );

		if ( $locally_owned ) {
			return array(
				'value' => $local_text,
				'apply' => false,
				'owned' => true,
			);
		}

		if ( ! $has_snapshot ) {
			if ( '' !== $local_text ) {
				return array(
					'value' => $local_text,
					'apply' => false,
					'owned' => true,
				);
			}

			return array(
				'value' => $feed_text,
				'apply' => true,
				'owned' => false,
			);
		}

		$previous_feed_text   = self::blank_text( $previous_feed );
		$previous_stored_text = self::blank_text( $previous_stored );

		if ( $feed_text === $previous_feed_text || $local_text !== $previous_stored_text ) {
			return array(
				'value' => $local_text,
				'apply' => false,
				'owned' => false,
			);
		}

		return array(
			'value' => $feed_text,
			'apply' => true,
			'owned' => false,
		);
	}

	/**
	 * Notes and scripture from a formatted SermonAudio item.
	 *
	 * @param array $item Formatted sermon.
	 * @return array{content:string,scripture:string}
	 */
	public static function supplied_fields_from_item( array $item ) {
		$scripture = '';

		if ( isset( $item['cpl_data']['scripture'] ) && is_string( $item['cpl_data']['scripture'] ) ) {
			$scripture = self::scripture_text( $item['cpl_data']['scripture'] );
		}

		return array(
			'content'   => self::blank_text( isset( $item['post_content'] ) ? $item['post_content'] : '' ),
			'scripture' => $scripture,
		);
	}

	/**
	 * Comparable scripture text.
	 *
	 * @param mixed $passages A passage string or a list of passages.
	 * @return string
	 */
	public static function scripture_text( $passages ) {
		if ( is_string( $passages ) ) {
			$passages = explode( ';', $passages );
		}

		if ( ! is_array( $passages ) ) {
			return '';
		}

		$clean = array();

		foreach ( $passages as $passage ) {
			if ( ! is_string( $passage ) ) {
				continue;
			}

			$passage = trim( $passage );

			if ( '' !== $passage ) {
				$clean[] = $passage;
			}
		}

		return implode( '; ', $clean );
	}

	/**
	 * Keep or replace notes and scripture on an existing sermon.
	 *
	 * The returned item is what wp_insert_post() should receive. Scripture
	 * that must be removed is not left on cpl_data; the caller deletes it
	 * after the post is saved, because an unset scripture key does not
	 * clear passages.
	 *
	 * @param array $item    Formatted sermon.
	 * @param int   $post_id Existing sermon.
	 * @return array{item:array,clear_scripture:bool}
	 */
	public static function apply_supplied_snapshot( array $item, $post_id ) {
		$post     = get_post( $post_id );
		$local    = array(
			'content'   => ( $post instanceof \WP_Post ) ? $post->post_content : '',
			'scripture' => self::scripture_text( self::stored_passages( $post_id ) ),
		);
		$feed     = self::supplied_fields_from_item( $item );
		$stored   = get_post_meta( $post_id, self::SUPPLIED_META, true );
		$has      = is_array( $stored );
		$content   = self::reconcile_supplied_field(
			$local['content'],
			$feed['content'],
			$has,
			self::snapshot_part( $stored, 'content', 'feed' ),
			self::snapshot_part( $stored, 'content', 'stored' ),
			'1' === self::snapshot_part( $stored, 'content', 'owned' )
		);
		$scripture = self::reconcile_supplied_field(
			$local['scripture'],
			$feed['scripture'],
			$has,
			self::snapshot_part( $stored, 'scripture', 'feed' ),
			self::snapshot_part( $stored, 'scripture', 'stored' ),
			'1' === self::snapshot_part( $stored, 'scripture', 'owned' )
		);

		if ( ! $content['apply'] && $post instanceof \WP_Post ) {
			$item['post_content'] = wp_slash( $post->post_content );
		} elseif ( $content['apply'] ) {
			$item['post_content'] = $feed['content'];
		}

		$clear = false;

		if ( ! isset( $item['cpl_data'] ) || ! is_array( $item['cpl_data'] ) ) {
			$item['cpl_data'] = array();
		}

		if ( ! $scripture['apply'] ) {
			unset( $item['cpl_data']['scripture'] );
		} elseif ( '' === $feed['scripture'] ) {
			unset( $item['cpl_data']['scripture'] );
			$clear = true;
		} else {
			$item['cpl_data']['scripture'] = $feed['scripture'];
		}

		return array(
			'item'            => $item,
			'clear_scripture' => $clear,
			'wrote_content'   => (bool) $content['apply'],
			'wrote_scripture' => (bool) $scripture['apply'],
			'own_content'     => (bool) $content['owned'],
			'own_scripture'   => (bool) $scripture['owned'],
		);
	}

	/**
	 * One side of a supplied snapshot: the raw feed text or the stored text.
	 *
	 * @param mixed  $snapshot Stored meta, when it is an array.
	 * @param string $field    content or scripture.
	 * @param string $part     feed or stored.
	 * @return string
	 */
	public static function snapshot_part( $snapshot, $field, $part ) {
		if ( ! is_array( $snapshot ) || ! isset( $snapshot[ $field ] ) || ! is_array( $snapshot[ $field ] ) ) {
			return '';
		}

		return isset( $snapshot[ $field ][ $part ] ) ? self::blank_text( $snapshot[ $field ][ $part ] ) : '';
	}

	/**
	 * Record the feed text and the value read back after save filters.
	 *
	 * The first snapshot marks a non-empty local field as owned. A field this
	 * save filled records the value read back after save filters. Later
	 * syncs replace that stored value only when the field was written again.
	 * An owned field stays owned.
	 *
	 * @param int   $post_id         Sermon the matcher linked.
	 * @param array $feed            content and scripture from this feed item.
	 * @param bool  $wrote_content   Whether this save wrote notes.
	 * @param bool  $wrote_scripture Whether this save wrote or cleared scripture.
	 * @param bool  $own_content     Whether notes were already on the sermon.
	 * @param bool  $own_scripture   Whether scripture was already on the sermon.
	 */
	public static function record_supplied_snapshot( $post_id, array $feed, $wrote_content, $wrote_scripture, $own_content = false, $own_scripture = false ) {
		$existing          = get_post_meta( $post_id, self::SUPPLIED_META, true );
		$has               = is_array( $existing );
		$post              = get_post( $post_id );
		$content_stored    = ( $post instanceof \WP_Post ) ? self::blank_text( $post->post_content ) : '';
		$scripture_stored  = self::scripture_text( self::stored_passages( $post_id ) );

		if ( ! $has ) {
			$snapshot = array(
				'content'   => array(
					'feed'   => $feed['content'],
					'stored' => $content_stored,
					'owned'  => $own_content ? '1' : '',
				),
				'scripture' => array(
					'feed'   => $feed['scripture'],
					'stored' => $scripture_stored,
					'owned'  => $own_scripture ? '1' : '',
				),
			);
		} else {
			$snapshot = array(
				'content'   => array(
					'feed'   => $feed['content'],
					'stored' => $wrote_content ? $content_stored : self::snapshot_part( $existing, 'content', 'stored' ),
					'owned'  => self::snapshot_part( $existing, 'content', 'owned' ),
				),
				'scripture' => array(
					'feed'   => $feed['scripture'],
					'stored' => $wrote_scripture ? $scripture_stored : self::snapshot_part( $existing, 'scripture', 'stored' ),
					'owned'  => self::snapshot_part( $existing, 'scripture', 'owned' ),
				),
			);
		}

		update_post_meta( $post_id, self::SUPPLIED_META, $snapshot );
	}

	/**
	 * @param mixed $value Field value.
	 * @return string
	 */
	public static function blank_text( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		return trim( $value );
	}

	/**
	 * Whether a match-linked save must leave each local value in place.
	 *
	 * Content is kept when it has a non-whitespace character. Scripture is
	 * kept when at least one passage is non-empty. An empty value is filled
	 * from SermonAudio on this save. The same rule runs on the first link
	 * and on every later sync of a sermon this matcher linked, so the next
	 * run cannot replace what the first run kept.
	 *
	 * @param mixed $content  Stored post_content.
	 * @param mixed $passages Stored passages.
	 * @return array{content:bool,scripture:bool}
	 */
	public static function preserved_fields( $content, $passages ) {
		return array(
			'content'   => is_string( $content ) && '' !== trim( $content ),
			'scripture' => self::passages_are_nonempty( $passages ),
		);
	}

	/**
	 * Whether this matcher linked the post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_match_linked( $post_id ) {
		return '1' === (string) get_post_meta( $post_id, self::LINKED_META, true );
	}

	/**
	 * Mark sermons that must not be title-matched because the batch itself
	 * is ambiguous.
	 *
	 * A 9:00 and an 11:00 sermon can share a title and a site day. Linking
	 * either one to the single local sermon with that title would pick the
	 * wrong service. Both are flagged, and the sync inserts new posts.
	 * Keys on the array are preserved. An empty normalized title is never
	 * a collision.
	 *
	 * @param array $items Formatted sermons from one sync batch.
	 * @return array
	 */
	public static function flag_batch_ambiguities( array $items ) {
		$counts = array();
		$keys   = array();

		foreach ( $items as $index => $item ) {
			$keys[ $index ] = self::day_title_key( $item );

			if ( '' === $keys[ $index ] ) {
				continue;
			}

			if ( ! isset( $counts[ $keys[ $index ] ] ) ) {
				$counts[ $keys[ $index ] ] = 0;
			}

			$counts[ $keys[ $index ] ]++;
		}

		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$key = $keys[ $index ];

			if ( '' !== $key && isset( $counts[ $key ] ) && $counts[ $key ] >= 2 ) {
				$items[ $index ][ self::BATCH_AMBIGUOUS ] = 1;
			}
		}

		return $items;
	}

	/**
	 * Site day and normalized title, or '' when either is missing.
	 *
	 * @param mixed $item Formatted sermon.
	 * @return string
	 */
	public static function day_title_key( $item ) {
		if ( ! is_array( $item ) ) {
			return '';
		}

		$day   = self::calendar_day( isset( $item['post_date'] ) ? $item['post_date'] : '' );
		$title = self::normalize_title( isset( $item['post_title'] ) ? $item['post_title'] : '' );

		if ( '' === $day || '' === $title ) {
			return '';
		}

		return $day . "\n" . $title;
	}

	/**
	 * Existing sermon to link, or null when the sync should create one.
	 *
	 * The lookup is the posts whose post_date falls on the incoming
	 * calendar day, for this post type only. Speakers and series are not
	 * candidates. There is no unbounded scan of cpl_item.
	 *
	 * @param array $item Formatted sermon, with post_type, post_title, post_date, and external_id.
	 * @return int|null
	 */
	public static function find_link( array $item ) {
		$post_type = isset( $item['post_type'] ) ? (string) $item['post_type'] : '';
		$title     = isset( $item['post_title'] ) ? $item['post_title'] : '';
		$external  = isset( $item['external_id'] ) ? (string) $item['external_id'] : '';
		$day       = self::calendar_day( isset( $item['post_date'] ) ? $item['post_date'] : '' );

		if ( '' === $post_type || '' === $day || '' === self::normalize_title( $title ) ) {
			return null;
		}

		return self::select_match(
			self::with_stored_media( self::posts_on_day( $post_type, $day ) ),
			$title,
			$external,
			self::incoming_media( $item )
		);
	}

	/**
	 * Put non-empty local content and scripture back onto a sync item.
	 *
	 * wp_insert_post() unslashes its input, so the stored content is slashed
	 * here. The value is read from the database on each save, so a second
	 * sync slashes the stored content once and not a value already slashed.
	 *
	 * @param array $item    Formatted sermon.
	 * @param int   $post_id Existing sermon.
	 * @return array
	 */
	public static function keep_nonempty_local_fields( array $item, $post_id ) {
		$post     = get_post( $post_id );
		$content  = ( $post instanceof \WP_Post ) ? $post->post_content : '';
		$passages = self::stored_passages( $post_id );
		$keep     = self::preserved_fields( $content, $passages );

		if ( $keep['content'] && $post instanceof \WP_Post ) {
			$item['post_content'] = wp_slash( $post->post_content );
		}

		if ( $keep['scripture'] && isset( $item['cpl_data'] ) && is_array( $item['cpl_data'] ) ) {
			unset( $item['cpl_data']['scripture'] );
		}

		return $item;
	}

	/**
	 * Passages stored for a sermon, or an empty array when scripture is unavailable.
	 *
	 * @param int $post_id Post ID.
	 * @return mixed
	 */
	private static function stored_passages( $post_id ) {
		if ( ! function_exists( 'cp_library' ) ) {
			return array();
		}

		$library = cp_library();

		if ( empty( $library->setup->taxonomies->scripture ) ) {
			return array();
		}

		return $library->setup->taxonomies->scripture->get_object_passages( $post_id );
	}

	/**
	 * cpl_item rows whose post_date falls on $day, one entry per post.
	 *
	 * Statuses are an equality list so MySQL can use type_status_date and
	 * range-scan post_date instead of reading every sermon. Auto-drafts,
	 * revisions, and trash are not in the list. A trashed sermon is not a
	 * candidate, so the sync cannot update it or publish it again.
	 *
	 * @param string $post_type Item post type.
	 * @param string $day       Y-m-d.
	 * @return array<int,array{ID:int,post_title:string,external_id:string}>
	 */
	private static function posts_on_day( $post_type, $day ) {
		global $wpdb;

		$next = self::next_day( $day );

		if ( '' === $next || ! isset( $wpdb->posts, $wpdb->postmeta ) ) {
			return array();
		}

		$statuses = array( 'publish', 'draft', 'pending', 'private', 'future' );
		$in       = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
		$sql      = "SELECT p.ID, p.post_title, pm.meta_value AS external_id
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm
				ON pm.post_id = p.ID AND pm.meta_key = 'external_id'
			WHERE p.post_type = %s
				AND p.post_status IN ( $in )
				AND p.post_date >= %s
				AND p.post_date < %s";
		$args     = array_merge(
			array( $post_type ),
			$statuses,
			array( $day . ' 00:00:00', $next . ' 00:00:00' )
		);

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$by_id = array();

		foreach ( $rows as $row ) {
			$id = isset( $row['ID'] ) ? (int) $row['ID'] : 0;

			if ( $id <= 0 ) {
				continue;
			}

			if ( ! isset( $by_id[ $id ] ) ) {
				$by_id[ $id ] = array(
					'ID'          => $id,
					'post_title'  => isset( $row['post_title'] ) ? (string) $row['post_title'] : '',
					'external_id' => '',
				);
			}

			$external_id = isset( $row['external_id'] ) ? trim( (string) $row['external_id'] ) : '';

			if ( '' !== $external_id && '' === $by_id[ $id ]['external_id'] ) {
				$by_id[ $id ]['external_id'] = $external_id;
			}
		}

		return array_values( $by_id );
	}

	/**
	 * Whether any passage string is non-empty.
	 *
	 * @param mixed $passages Stored passages.
	 * @return bool
	 */
	private static function passages_are_nonempty( $passages ) {
		if ( is_string( $passages ) ) {
			return '' !== trim( $passages );
		}

		if ( ! is_array( $passages ) ) {
			return false;
		}

		foreach ( $passages as $passage ) {
			if ( is_string( $passage ) && '' !== trim( $passage ) ) {
				return true;
			}
		}

		return false;
	}
}
