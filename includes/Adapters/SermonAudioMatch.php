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
	 * happens only when the incoming title normalizes to something non-empty
	 * and exactly one candidate has that title, and that post's external_id
	 * is empty or already this SermonAudio id. Zero matches, two or more
	 * matches, and one match that already has a different external id all
	 * return null.
	 *
	 * @param array<int,array{ID:int,post_title:string,external_id?:string}> $candidates Same-day sermons.
	 * @param mixed                                                           $incoming_title Raw title.
	 * @param string                                                          $incoming_external_id SermonAudio sermon ID.
	 * @return int|null
	 */
	public static function select_match( array $candidates, $incoming_title, $incoming_external_id ) {
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

			$matches[] = $candidate;
		}

		if ( 1 !== count( $matches ) ) {
			return null;
		}

		$existing_external_id = isset( $matches[0]['external_id'] ) ? trim( (string) $matches[0]['external_id'] ) : '';

		if ( '' !== $existing_external_id && (string) $incoming_external_id !== $existing_external_id ) {
			return null;
		}

		$id = isset( $matches[0]['ID'] ) ? (int) $matches[0]['ID'] : 0;

		return $id > 0 ? $id : null;
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

		return self::select_match( self::posts_on_day( $post_type, $day ), $title, $external );
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
	 * range-scan post_date instead of reading every sermon. Auto-drafts and
	 * revisions are not in the list. Trash is: a trashed sermon is still the
	 * sermon for that day, and linking it does not publish a second copy.
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

		$statuses = array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' );
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
