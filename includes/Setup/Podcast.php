<?php // phpcs:ignore

namespace CP_Library\Setup;

use CP_Library\Templates;

/**
 * Podcast controller class
 */
class Podcast
{

	/**
	 * Singleton instance
	 *
	 * @var Podcast
	 */
	protected static $_instance;

	/**
	 * Enforce singleton instantiation
	 *
	 * @return Podcast
	 */
	public static function get_instance() {
		if( !self::$_instance instanceof Podcast ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor
	 */
	protected function __construct() {
		add_action( 'init', [ $this, 'add_feed' ] );

		add_filter( 'cpl_podcast_content', 'convert_chars' );
		add_filter( 'cpl_podcast_content', 'ent2ncr' );
		add_filter( 'cpl_podcast_content', [ $this, 'convert_amp' ] );

		add_filter( 'cpl_podcast_text', 'strip_tags' );
		add_filter( 'cpl_podcast_text', 'ent2ncr' );
		add_filter( 'cpl_podcast_text', 'esc_html' );
		add_filter( 'cpl_podcast_text', [ $this, 'convert_amp' ] );
		add_action( 'parse_query', array( $this, 'setup_rss_feed_query' ) );
	}

	/**
	 * Convert &amp; to and
	 *
	 * @since  1.2.1
	 *
	 * @param $content
	 *
	 * @return array|string|string[]
	 * @author Tanner Moushey, 9/12/23
	 */
	public function convert_amp( $content ) {
		$content = str_replace( '&amp;', 'and', $content );
		$content = str_replace( '&#38;', 'and', $content );

		return $content;
	}

	/**
	 * Whether Podcast is enabled
	 *
	 * @since  1.0.4
	 *
	 *
	 * @return mixed|void
	 * @author Tanner Moushey, 4/13/23
	 */
	public function is_enabled() {
		$enabled = (bool) \CP_Library\Admin\Settings::get( 'podcast_feed_enable', false, 'cpl_advanced_options' );
		return apply_filters( 'cpl_enable_podcast', $enabled );
	}

	/**
	 * Get the podcast feed name and allow for pages to use this feed name
	 *
	 * @since  1.2.5
	 * @updated 1.3.1 - allow for pages to use this feed name
	 *
	 * @author Tanner Moushey, 12/14/23
	 */
	public function add_feed() {
		add_feed( $this->get_feed_name(), [ $this, 'output_feed' ] );
		add_action( 'pre_get_posts', [ $this, 'feed_query' ] );

		// allow the feed name to also be a page
		add_rewrite_rule( '^' . $this->get_feed_name() . '/?$', 'index.php?pagename=' . $this->get_feed_name(), 'top' );
		add_filter( 'wp_unique_post_slug', [ $this, 'unique_post_slug' ], 10, 6 );
	}

	/**
	 * Allow page to use same slug as feed
	 *
	 * @since  1.3.1
	 *
	 * @param $slug
	 * @param $post_ID
	 * @param $post_status
	 * @param $post_type
	 * @param $post_parent
	 * @param $original_slug
	 *
	 * @return mixed|void
	 * @author Tanner Moushey, 12/14/23
	 */
	public function unique_post_slug( $slug, $post_ID, $post_status, $post_type, $post_parent, $original_slug ) {
		if ( $this->get_feed_name() === $original_slug && $original_slug . '-2' == $slug ) {
			$slug = $original_slug;
		}

		return $slug;
	}

	public function output_feed() {
		Templates::get_template_part( 'podcast' );
	}

	/**
	 * Query params for podcast feed
	 *
	 * @since  1.0.4
	 * @updated 1.2.0 - Added support for taxonomies
	 *
	 * @param $query \WP_Query
	 *
	 * @author Tanner Moushey, 4/11/23
	 */
	public function feed_query( $query ) {
		if ( ! $query->is_main_query() || ! is_feed( $this->get_feed_name() ) ) {
			return;
		}

		$is_tax = false;

		foreach( cp_library()->setup->taxonomies->get_taxonomies() as $taxonomy ) {
			if ( $query->get( $taxonomy ) ) {
				$is_tax = true;
				$query->set( 'post_type', cp_library()->setup->post_types->item->post_type );
			}
		}

		if( $query->get( 'post_type' ) !== cp_library()->setup->post_types->item->post_type && ! $is_tax ) {
			return;
		}

		if ( 'feed' == $query->get( cp_library()->setup->post_types->item->post_type ) ) {
			unset( $query->query_vars[ cp_library()->setup->post_types->item->post_type ] );
			unset( $query->query[ cp_library()->setup->post_types->item->post_type ] );
		}

		if ( 'feed' == $query->get( 'name' ) ) {
			unset( $query->query_vars[ 'name' ] );
			unset( $query->query[ 'name' ] );
		}

		$query->set( 'post_status', 'publish' );
		$query->is_comment_feed = false;
		$query->is_single       = false;
		$query->is_singular     = false;
		$query->is_archive      = true;

		// Only sermons having an enclosure.
		$query->set( 'meta_query', array(
			'relation' => 'AND',
			array(
				'key'     => 'enclosure',
				'value'   => '',
				'compare' => '!=',
			),
			array(
				'relation' => 'OR',
				array(
					'key'     => 'podcast_exclude',
					'value'   => '',
					'compare' => '=',
				),
				array(
					'key'     => 'podcast_exclude',
					'value'   => '',
					// empty required for back compat with WP 3.8 and below (core bug).
					'compare' => 'NOT EXISTS',
					// field did not always exist, so don't just check empty; check not exist and include those.
				),
			),
		) );
	}

	/**
	 * Return the feed name for the podcast
	 *
	 * @since  1.0.4
	 *
	 * @return mixed|void
	 * @author Tanner Moushey, 4/10/23
	 */
	public function get_feed_name() {
		return apply_filters( 'cpl_podcast_feed_name', 'podcast' );
	}

	/**
	 * Return the feed url for the podcast
	 *
	 * @since  1.0.4
	 * @updated 1.2.0 to retrieve feed url from post type archive feed link
	 *
	 * @return mixed|void
	 * @author Tanner Moushey, 4/10/23
	 */
	public function get_feed_url() {
		$feed_link = get_post_type_archive_feed_link( cp_library()->setup->post_types->item->post_type, $this->get_feed_name() );
		return apply_filters( 'cpl_podcast_feed_url', $feed_link );
	}

	/**
	 * Return the feed uri for the podcast
	 *
	 * @since  1.2.0
	 *
	 * @return mixed|void
	 * @author Tanner Moushey, 9/5/23
	 */
	public function get_feed_uri() {
		$feed_link = get_feed_link( $this->get_feed_name() );
		return apply_filters( 'cpl_podcast_feed_uri', str_replace( home_url(), '', $feed_link ) );
	}

	/**
	 * Modifies the $wp_query object if it is for a comment feed and adds our own query parameter.
	 * This is to replace the default feed for single posts (comments) with our custom relational post types.
	 *
	 * @param \WP_Query $query The query object.
	 * @since 1.3.0
	 */
	public function setup_rss_feed_query( $query ) {
		if ( $query->is_feed && $query->get( 'feed' ) === $this->get_feed_name() && $query->is_comment_feed() && in_array( $query->get( 'post_type' ), cp_library()->setup->post_types->get_post_types(), true ) ) {
			$query->set( 'cpl_relation_feed', true );
			$query->is_comment_feed = false;
			$query->set( 'is_comment_feed', false );

			if ( isset( $_GET['show-all'] ) ) {
				$query->set( 'posts_per_rss', 9999 );
			}
		}
	}
}
