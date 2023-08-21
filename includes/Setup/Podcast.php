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
		add_action( 'rss2_head', [ $this, 'rss_feed_head' ] );
		add_action( 'rss2_item', [ $this, 'rss_feed_item' ] );
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
	 *
	 * @param $query \WP_Query
	 *
	 * @author Tanner Moushey, 4/11/23
	 */
	public function feed_query( $query ) {
		if ( ! $query->is_main_query() || ! is_feed( $this->get_feed_name() ) ) {
			return;
		}

		$query->set( 'post_type', cp_library()->setup->post_types->item->post_type );

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
		return apply_filters( 'cpl_podcast_feed_name', 'sermon-podcast' );
	}

	/**
	 * Return the feed url for the podcast
	 *
	 * @since  1.0.4
	 *
	 * @return mixed|void
	 * @author Tanner Moushey, 4/10/23
	 */
	public function get_feed_url() {
		return apply_filters( 'cpl_podcast_feed_url', get_feed_link( $this->get_feed_name() ) );
	}

	/**
	 * Adds custom podcast meta to head
	 */
	public function rss_feed_head() {
		if( get_post_type() !== cp_library()->setup->post_types->item->post_type ) {
			return;
		}

		Templates::get_template_part( 'podcast-meta' );
	}

	/**
	 * Adds custom podcast meta to each item in the feed
	 */
	public function rss_feed_item() {
		if( get_post_type() !== cp_library()->setup->post_types->item->post_type ) {
			return;
		}

		Templates::get_template_part( 'parts/podcast-item-meta' );
	}
}
