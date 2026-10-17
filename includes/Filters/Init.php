<?php
/**
 * Filter System Initialization
 *
 * Bootstraps the new filter system and handles backward compatibility.
 *
 * @package CP_Library\Filters
 * @since   1.6.0
 */

namespace CP_Library\Filters;

use CP_Library\Filters\Types\SermonFilterManager;
use CP_Library\Filters\Types\SeriesFilterManager;
use CP_Library\Filters\SEO;

/**
 * Init class - Bootstrap for the filter system.
 *
 * This class initializes the filter system, registering filter managers for
 * different post types and providing backward compatibility with the old
 * singleton Filters class.
 *
 * @since 1.6.0
 */
class Init {

	/**
	 * Instance of this class
	 *
	 * @var Init
	 */
	private static $instance = null;

	/**
	 * Error handler instance
	 *
	 * @var ErrorHandler
	 */
	private $error_handler = null;

	/**
	 * SEO handler instance
	 *
	 * @var SEO
	 */
	private $seo = null;

	/**
	 * Get the singleton instance
	 *
	 * @return Init
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Initialize error handler
		$this->error_handler = ErrorHandler::get_instance();

		// Initialize SEO handler
		$this->seo = SEO::get_instance();

		$this->actions();
	}

	/**
	 * Setup actions and filters
	 */
	private function actions() {
		// Initialize filter managers
		add_action( 'init', [ $this, 'register_filter_managers' ], 30 );

		// Register assets
		add_action( 'wp_enqueue_scripts', "CP_Library\Filters\FilterManager::enqueue_scripts" );

		// Add template hooks
		add_action( 'init', [ $this, 'add_template_hooks' ], 50 );
	}

	/**
	 * Add template hooks for filters
	 */
	public function add_template_hooks() {
		// Add filter to include our new template files
		add_filter( 'cpl_template_paths', [ $this, 'add_filter_templates' ] );

		// Add hook for rendering filters in archives
		add_action( 'cpl_before_archive_filter', [ $this, 'before_archive_filter' ], 10, 2 );
	}

	/**
	 * Add filter templates to the template paths
	 *
	 * @param array $paths Template paths
	 *
	 * @return array Modified paths
	 */
	public function add_filter_templates( $paths ) {
		// Our templates are already in the main templates directory
		return $paths;
	}

	/**
	 * Render filters before archive
	 *
	 * @param string $type Content type
	 * @param array  $args Template args
	 */
	public function before_archive_filter( $type, $args = [] ) {
		// Use template helpers to render filters
		$post_type = '';

		switch ( $type ) {
			case 'item':
				$post_type = 'cpl_item';
				break;
			case 'item-type':
				$post_type = 'cpl_item_type';
				break;
			default:
				return;
		}

		$filter_manager = FilterManager::get_filter_manager( $post_type );

		if ( $filter_manager ) {
			$template_args = wp_parse_args( $args, [
				'context'         => 'archive',
				'context_args'    => [],
				'show_search'     => true,
				'container_class' => 'cpl-archive-filter',
				'template'        => 'list',
			] );

			echo $filter_manager->render_filter_form( $template_args );
			echo $filter_manager->render_selected_filters( $template_args );
		}
	}

	/**
	 * Register filter managers for content types
	 */
	public function register_filter_managers() {
		// Register sermon filter manager
		FilterManager::register_filter_manager(
			'cpl_item',
			SermonFilterManager::class
		);

		// Register series filter manager
		FilterManager::register_filter_manager(
			'cpl_item_type',
			SeriesFilterManager::class
		);

		// Load template helpers
		require_once dirname( __FILE__ ) . '/TemplateHelpers.php';

		// Load global template functions
		require_once dirname( __FILE__ ) . '/functions.php';

		// Allow other plugins to register their own filter managers
		do_action( 'cpl_register_filter_managers' );
	}

	/**
	 * Helper to get a filter manager instance by post type
	 *
	 * @param string $post_type The post type
	 *
	 * @return AbstractFilterManager|null
	 */
	public function get_filter_manager( $post_type ) {
		return FilterManager::get_filter_manager( $post_type );
	}

	/**
	 * Helper to get the sermon filter manager
	 *
	 * @return SermonFilterManager|null
	 */
	public function get_sermon_filter_manager() {
		return FilterManager::get_filter_manager( 'cpl_item' );
	}

	/**
	 * Helper to get the series filter manager
	 *
	 * @return SeriesFilterManager|null
	 */
	public function get_series_filter_manager() {
		return FilterManager::get_filter_manager( 'cpl_item_type' );
	}
}
