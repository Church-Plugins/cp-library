<?php
/**
 * Templating functionality for Church Plugins Library
 */

namespace CP_Library;

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}


/**
 * Handle views and template files.
 */
class Templates extends \ChurchPlugins\Templates {

	/**
	 * @var bool Is wp_head complete?
	 */
	public static $wpHeadComplete = false;

	/**
	 * @var bool Is this the main loop?
	 */
	public static $isMainLoop = false;

	/**
	 * The template name currently being used
	 */
	protected static $template = false;

	/*
	 * List of templates which have compatibility fixes
	 */
	public static $themes_with_compatibility_fixes = [];

	/**
	 * Initialize the Template Yumminess!
	 */
	protected function __construct() {
		parent::__construct();

		add_action( 'cpl_after_archive', 'the_posts_pagination' );
	}

	public function posts_per_page( $post_type = 'post' ) {
		$posts_per_page = get_option( 'posts_per_page', 10 );

		return apply_filters( 'cpl_posts_per_page', $posts_per_page, $post_type );
	}


	public static function get_type( $type = false ) {
		if ( ! $type ) {
			$type = get_post_type();
		}

		return str_replace( [ 'cpl_', '_' ], [ '', '-' ], $type );
	}

	/**
	 * Include page template body class
	 *
	 * @param array $classes List of classes to filter
	 *
	 * @return mixed
	 */
	public function template_body_class( $classes ) {
		$classes = parent::template_body_class( $classes );

		$template_filename = basename( self::$template );

		$classes[] = 'cpl-template';

		if ( $template_filename == 'default-template.php' ) {
			$classes[] = 'cpl-page-template';
		} else {
			$classes[] = 'page-template-' . sanitize_title( $template_filename );
		}

		return $classes;
	}

	/**
	 * Add type switcher to archive header
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public static function type_switcher() {

		if( is_tax() ) {
			return;
		}

		$type   = self::get_type();
		$link   = '';
		$button = '';
		$item_cpt = get_post_type_object(cp_library()->setup->post_types->item->post_type);
		$item_type_cpt = get_post_type_object(cp_library()->setup->post_types->item_type->post_type);

		$split = explode( '?', $_SERVER['REQUEST_URI'] );
		$req_uri = $split[0] ?? '';
		$query_params = $split[1] ?? '';

		// Extract all path components up to the page delimiter
		$uri_split = explode( "/", $req_uri );
		$exclusions = [ $item_type_cpt->rewrite['slug'], $item_cpt->rewrite['slug'] ];
		$have_target = false;
		foreach( $uri_split as $token ) {
			if( 'page' === $token ) { $have_target = true; }
			if( $have_target ) { continue; }
			if( strlen( trim( $token ) ) > 0 && !in_array( $token, $exclusions ) ) {
				$link .= trailingslashit( $token );
			}
		}

		switch( $type ) {
			case 'item':
				$link .= $item_type_cpt->rewrite['slug'];
				$button = sprintf( __( 'Switch to %s' ), $item_type_cpt->label );
				break;
			case 'item-type':
				$link .= $item_cpt->rewrite['slug'];
				$button = sprintf( __( 'Switch to %s' ), $item_cpt->label );
				break;
		}

		if ( empty( $link ) ) {
			return;
		}

		// normalize the output
		if( 1 !== preg_match( "/^\//", $link ) ) {
			$link = '/' . $link;
		}
		$link = trailingslashit( $link );

		printf( '<a class="cpl-archive--item-switcher" href="%s">%s</a>', $link, $button );
	}

	/**
	 * Return the post types for this plugin
	 *
	 * @since  1.0.11
	 *
	 * @return mixed
	 * @author Tanner Moushey, 5/11/23
	 */
	public function get_post_types() {
		return cp_library()->setup->post_types->get_post_types();
	}

	/**
	 * Return the taxonomies for this plugin
	 *
	 * @since  1.0.11
	 *
	 * @return mixed
	 * @author Tanner Moushey, 5/11/23
	 */
	public function get_taxonomies() {
		return cp_library()->setup->taxonomies->get_taxonomies();
	}

	/**
	 * Return the plugin path for the current plugin
	 *
	 * @since  1.5.0
	 *
	 * @return mixed
	 */
	public function get_plugin_path() {
		return cp_library()->get_plugin_path();
	}

	/**
	 * Get the slug / id for the current plugin
	 *
	 * @since  1.0.11
	 *
	 * @return mixed
	 * @author Tanner Moushey, 5/11/23
	 */
	public function get_plugin_id() {
		return cp_library()->get_id();
	}

}
