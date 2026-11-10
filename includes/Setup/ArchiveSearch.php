<?php
/**
 * Apply the archive filter's search term to the main query.
 *
 * @package CP_Library
 */

namespace CP_Library\Setup;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * The sermon and series archives submit `cpl_search` instead of `s`.
 *
 * WordPress fills `posts_per_page` from Settings → Reading before
 * `pre_get_posts`. The archive hooks used to return as soon as that var was
 * set, which skipped the search. The page still showed the search chip, and
 * the list underneath was the unfiltered library, so a search for a sermon
 * that exists ("Prisoners" / "Take No prisoners") looked like it found nothing.
 */
class ArchiveSearch {

	/**
	 * Narrow the main query to the requested archive search, when there is one.
	 *
	 * Secondary loops on the same request (a widget, a related list) are left
	 * alone. An empty or non-string term does not set `s`, so it cannot turn
	 * the archive into a search for nothing.
	 *
	 * @param object $query Query passed to pre_get_posts.
	 * @return void
	 */
	public static function apply( $query ) {
		if ( ! is_object( $query ) || ! method_exists( $query, 'is_main_query' ) || ! $query->is_main_query() ) {
			return;
		}

		if ( ! isset( $_GET['cpl_search'] ) || ! is_string( $_GET['cpl_search'] ) ) {
			return;
		}

		$search = sanitize_text_field( wp_unslash( $_GET['cpl_search'] ) );

		if ( '' === $search ) {
			return;
		}

		$query->set( 's', $search );
	}
}
