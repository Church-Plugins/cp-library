<?php
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
		if ( ! self::$_instance instanceof Podcast ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor
	 */
	protected function __construct() {
		add_action( 'init', [ $this, 'add_feed' ] );
	}

	/**
	 * Whether Podcast is enabled
	 *
	 * @since  1.0.4
	 *
	 * @return mixed|void
	 * @author Tanner Moushey, 4/13/23
	 */
	public function is_enabled() {
		$enabled = (bool) \CP_Library\Admin\Settings::get( 'podcast_feed_enable', false, 'cpl_advanced_options' );
		return apply_filters( 'cpl_enable_podcast', $enabled );
	}

	public function add_feed() {
		add_feed( $this->get_feed_name(), [ $this, 'output_feed' ] );
		add_action( 'pre_get_posts', [ $this, 'feed_query' ] );
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
	 * @param \WP_Query $query The query object.
	 *
	 * @author Tanner Moushey, 4/11/23
	 */
	public function feed_query( $query ) {
		if ( ! $query->is_main_query() || ! is_feed( $this->get_feed_name() ) ) {
			return;
		}

		$is_tax = false;

		foreach ( cp_library()->setup->taxonomies->get_taxonomies() as $taxonomy ) {
			if ( $query->get( $taxonomy ) ) {
				$is_tax = true;
				$query->set( 'post_type', cp_library()->setup->post_types->item->post_type );
			}
		}

		if ( $query->get( 'post_type' ) !== cp_library()->setup->post_types->item->post_type && ! $is_tax ) {
			return;
		}

		if ( 'feed' === $query->get( cp_library()->setup->post_types->item->post_type ) ) {
			unset( $query->query_vars[ cp_library()->setup->post_types->item->post_type ] );
			unset( $query->query[ cp_library()->setup->post_types->item->post_type ] );
		}

		if ( 'feed' === $query->get( 'name' ) ) {
			unset( $query->query_vars[ 'name' ] );
			unset( $query->query[ 'name' ] );
		}

		$query->set( 'post_status', 'publish' );
		$query->is_comment_feed = false;
		$query->is_single       = false;
		$query->is_singular     = false;
		$query->is_archive      = true;

		// Only sermons having an enclosure.
		$query->set(
			'meta_query',
			array(
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
			)
		);
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
}
