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

		// Add widget areas to single and archive templates
		add_action( 'widgets_init', [ $this, 'register_sidebars' ] );

		add_action( 'cpl_before_cpl_single_item', [ $this, 'item_before_sidebar' ] );
		add_action( 'cpl_before_cpl_single_item-type', [ $this, 'item_type_before_sidebar' ] );

		add_action( 'cpl_after_cpl_single_item', [ $this, 'item_after_sidebar' ] );
		add_action( 'cpl_after_cpl_single_item-type', [ $this, 'item_type_after_sidebar' ] );

		add_action( 'cpl_before_archive_item', [ $this, 'item_archive_before_sidebar' ] );
		add_action( 'cpl_before_archive_item-type', [ $this, 'item_type_archive_before_sidebar' ] );

		add_action( 'cpl_after_archive_item', [ $this, 'item_archive_after_sidebar' ] );
		add_action( 'cpl_after_archive_item-type', [ $this, 'item_type_archive_after_sidebar' ] );
	}

	/**
	 * Register sidebars for sermons and series
	 *
	 * @since  1.5.2
	 *
	 * @author Tanner Moushey, 12/31/24
	 */
	public function register_sidebars() {

		$sidebars = [
			'cpl-item-archive-before' => [ cp_library()->setup->post_types->item->single_label, __( 'archive', 'cp-library' ), __( 'before', 'cp-library' ) ],
			'cpl-item-archive-after' => [ cp_library()->setup->post_types->item->single_label, __( 'archive', 'cp-library' ), __( 'after', 'cp-library' ) ],
			'cpl-item-single-before' => [ cp_library()->setup->post_types->item->single_label, __( 'single', 'cp-library' ), __( 'before', 'cp-library' ) ],
			'cpl-item-single-after' => [ cp_library()->setup->post_types->item->single_label, __( 'single', 'cp-library' ), __( 'after', 'cp-library' ) ],
		];

		if ( cp_library()->setup->post_types->item_type_enabled() ) {
			$sidebars['cpl-item-type-archive-before'] = [ cp_library()->setup->post_types->item_type->single_label, __( 'archive', 'cp-library' ), __( 'before', 'cp-library' ) ];
			$sidebars['cpl-item-type-archive-after'] = [ cp_library()->setup->post_types->item_type->single_label, __( 'archive', 'cp-library' ), __( 'after', 'cp-library' ) ];
			$sidebars['cpl-item-type-single-before'] = [ cp_library()->setup->post_types->item_type->single_label, __( 'single', 'cp-library' ), __( 'before', 'cp-library' ) ];
			$sidebars['cpl-item-type-single-after'] = [ cp_library()->setup->post_types->item_type->single_label, __( 'single', 'cp-library' ), __( 'after', 'cp-library' ) ];
		}

		foreach( $sidebars as $id => $args ) {
			register_sidebar( [
				'name'          => sprintf( '%s %s %s', $args[0], ucwords( $args[1] ), ucwords( $args[2] ) ),
				'id'            => $id,
				'description'   => sprintf( __( 'Widgets in this area will be shown %s the %s %s pages.', 'cp-library' ), $args[2], $args[0], $args[1] ),
				'before_widget' => '<section id="%1$s" class="widget %2$s">',
				'after_widget'  => '</section>',
				'before_title'  => '<h2 class="widget-title">',
				'after_title'   => '</h2>',
			] );
		}

	}

	public function item_archive_before_sidebar() {
		$this->print_sidebar( 'cpl-item-archive-before' );
	}

	public function item_archive_after_sidebar() {
		$this->print_sidebar( 'cpl-item-archive-after' );
	}

	public function item_before_sidebar() {
		$this->print_sidebar( 'cpl-item-single-before' );
	}

	public function item_after_sidebar() {
		$this->print_sidebar( 'cpl-item-single-after' );
	}

	public function item_type_archive_before_sidebar() {
		$this->print_sidebar( 'cpl-item-type-archive-before' );
	}

	public function item_type_archive_after_sidebar() {
		$this->print_sidebar( 'cpl-item-type-archive-after' );
	}

	public function item_type_before_sidebar() {
		$this->print_sidebar( 'cpl-item-type-single-before' );
	}

	public function item_type_after_sidebar() {
		$this->print_sidebar( 'cpl-item-type-single-after' );
	}

	protected function print_sidebar( $id ) {
		if ( is_active_sidebar( $id ) ) {
			echo '<div class="cpl-sidebar cpl-sidebar--' . str_replace( 'cpl-', '', $id ) . '">';
			dynamic_sidebar( $id );
			echo '</div>';
		}
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
