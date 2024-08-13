<?php

namespace CP_Library\Admin\Settings;


use CP_Library\Admin\Settings;

/**
 * Plugin Podcast
 *
 */
class Podcast {

	/**
	 * @var
	 */
	protected static $_instance;

	/**
	 * Only make one instance of \CP_Library\Settings\Podcast
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
	 * Class constructor. Add admin hooks and actions
	 *
	 */
	protected function __construct() {}

	/**
	 * Get a value from the options table
	 *
	 * @param $key
	 * @param $default
	 *
	 * @return mixed|void
	 * @since  1.0.4
	 * @updated 1.2.0 - special handling for single and taxonomy feeds
	 *
	 * @author Tanner Moushey
	 */
	public static function get( $key, $default = '' ) {
		$options = get_option( 'cpl_podcast_options', [] );
		$value = false;

		if ( is_tax() && $term = get_queried_object() ) {
			switch( $key ) {
				case 'title':
					$value = $term->name;

					if ( ! empty( $options['title'] ) ) {
						$value .= ' - ' . $options['title'];
					}
					break;
				case 'subtitle':
					$value = $term->description;
					break;
			}
		}

		if ( is_singular() ) {
			switch( $key ) {
				case 'title':
					$value = get_the_title_rss();

					if ( ! empty( $options['title'] ) ) {
						$value .= ' - ' . $options['title'];
					}
					break;
				case 'subtitle':
					$value = get_the_excerpt();
					break;
				case 'summary':
					$value = get_the_content_feed();
					break;
			}
		}

		if ( empty( $value ) ) {
			if ( isset( $options[ $key ] ) ) {
				$value = $options[ $key ];
			} else {
				$value = $default;
			}
		}

		if ( 'category' === $key ) {
			$value = str_replace( 'AND', '&', $value );
		}

		return apply_filters( 'cpl_settings_podcast_get', $value, $key );
	}

	/**
	 * Return the valid categories for the podcast settings page
	 *
	 * @since  1.0.4
	 *
	 * @return mixed|void
	 * @author Tanner Moushey, 4/13/23
	 */
	public static function category_map() {
		$map = [
			'Religion AND Spirituality|Christianity'  => __( 'Christianity (Religion)', 'cp-library' ),
			'Government AND Organizations|Non-Profit' => __( 'Non-Profit (Organizations)', 'cp-library' ),
			'none'                                  => __( 'None', 'cp-library' ),
		];

		return apply_filters( 'cpl_settings_podcast_category_map', $map );
	}

	public static function fields() {
		/**
		 * Registers secondary options page, and set main item as parent.
		 */
		$args = array(
			'id'           => 'cpl_podcast_options_page',
			'title'        => 'Settings',
			'object_types' => array( 'options-page' ),
			'option_key'   => 'cpl_podcast_options',
			'parent_slug'  => 'cpl_main_options',
			'tab_group'    => 'cpl_main_options',
			'tab_title'    => __( 'Podcast', 'cp-library' ),
			'display_cb'   => [ Settings::get_instance(), 'options_display_with_tabs' ],
		);

		$options = new_cmb2_box( $args );

		$options->add_field( array(
			'name'    => __( 'Podcast Settings', 'cp-library' ),
			'id'      => 'heading',
			'type'    => 'title',
			'desc'    => __( 'Feed url: ', 'cp-library' ) . sprintf( '<a href="%1$s" target="_blank">%1$s</a>. Or append <code>%2$s</code> to any %3$s or any %4$s taxonomy page.',
					cp_library()->setup->podcast->get_feed_url(),
					cp_library()->setup->podcast->get_feed_uri(),
					cp_library()->setup->post_types->item_type->single_label,
					cp_library()->setup->post_types->item->single_label,
				),
		) );

		$options->add_field( array(
			'name'    => __( 'Image', 'cp-library' ),
			'id'      => 'image',
			'type'    => 'file',
			'desc'    => __( 'Must be between 1400 x 1400 and 3000 x 3000 and in JPG  or PNG format. Required by iTunes.', 'cp-library' )
		) );

		$options->add_field( array(
			'name'    => __( 'Title', 'cp-library' ),
			'desc'    => __( 'The podcast title', 'cp-library' ),
			'id'      => 'title',
			'type'    => 'text',
			'default' => get_bloginfo( 'name' ),
		) );

		$options->add_field( array(
			'name'    => __( 'Subtitle', 'cp-library' ),
			'desc'    => __( 'The podcast subtitle', 'cp-library' ),
			'id'      => 'subtitle',
			'type'    => 'text',
			'default' => get_bloginfo( 'description' ),
		) );

		$options->add_field( array(
			'name'    => __( 'Description', 'cp-library' ),
			'desc'    => __( 'The podcast description', 'cp-library' ),
			'id'      => 'summary',
			'type'    => 'textarea',
			'default' => self::get( 'subtitle', get_bloginfo( 'description' ) ),
		) );

		$options->add_field( array(
			'name'    => __( 'Provider', 'cp-library' ),
			'desc'    => __( 'The name of the podcast provider (probably your church name)', 'cp-library' ),
			'id'      => 'author',
			'type'    => 'text',
			'default' => get_bloginfo( 'name' ),
		) );

		$options->add_field( array(
			'name'    => __( 'Copyright', 'cp-library' ),
			'desc'    => __( 'Copyright message to show on the podcast stream', 'cp-library' ),
			'id'      => 'copyright',
			'type'    => 'text',
			'default' => '© ' . get_bloginfo( 'name' ),
		) );

		$options->add_field( array(
			'name'    => __( 'Link', 'cp-library' ),
			'desc'    => __( 'Link to include in podcast feed (website link)', 'cp-library' ),
			'id'      => 'link',
			'type'    => 'text_url',
			'default' => trailingslashit( home_url() ),
		) );

		$options->add_field( array(
			'name'    => __( 'Email', 'cp-library' ),
			'desc'    => __( 'Email address to include in podcast feed', 'cp-library' ),
			'id'      => 'email',
			'type'    => 'text_email',
		) );

		$options->add_field( array(
			'name'    => __( 'Category', 'cp-library' ),
			'desc'    => __( 'The category to use for this podcast', 'cp-library' ),
			'id'      => 'category',
			'type'    => 'radio_inline',
			'options' => self::category_map(),
			'default' => 'Religion AND Spirituality|Christianity',
		) );

		$options->add_field( array(
			'name'    => __( 'Clean', 'cp-library' ),
			'desc'    => __( 'Podcast does not contain explicit content', 'cp-library' ),
			'id'      => 'not_explicit',
			'type'    => 'checkbox',
			'default' => 1,
		) );

		$options->add_field( array(
			'name'    => __( 'Language', 'cp-library' ),
			'desc'    => __( 'The language for this podcast', 'cp-library' ),
			'id'      => 'language',
			'type'    => 'text',
			'default' => 'en-US',
		) );

		$options->add_field( array(
			'name'    => __( 'iTunes New Feed URL', 'cp-library' ),
			'desc'    => __( 'Use when migrating your feed from a different url (<a href="https://podcasters.apple.com/support/837-change-the-rss-feed-url">more details</a>)', 'cp-library' ),
			'id'      => 'new_url',
			'type'    => 'text_url',
		) );

	}

}
