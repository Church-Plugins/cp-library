<?php
/**
 * Abstract Filter Manager Class
 *
 * Base class for filter manager implementations that provides common functionality.
 *
 * @package CP_Library\Filters
 * @since   1.6.0
 */

namespace CP_Library\Filters;

use ChurchPlugins\Helpers;
use CP_Library\Admin\Settings;

/**
 * AbstractFilterManager class - Base implementation for filter managers.
 *
 * This abstract class defines the contract for filter managers and provides
 * common implementations for facet handling, caching, query modification,
 * and template rendering.
 *
 * @since 1.6.0
 */
abstract class AbstractFilterManager {

	/**
	 * Error handler instance
	 *
	 * @var ErrorHandler
	 */
	protected $error_handler;

	/**
	 * Post type this filter manager handles
	 *
	 * @var string
	 */
	protected $post_type;

	/**
	 * Registered facets
	 *
	 * @var array
	 */
	protected $facets = [];

	/**
	 * Registered facet instances
	 *
	 * @var array
	 */
	protected $facet_instances = [];

	/**
	 * Registered contexts
	 *
	 * @var array
	 */
	protected $contexts = [];

	/**
	 * Constructor
	 *
	 * @param array $args Configuration arguments
	 */
	public function __construct( $args = [] ) {
		$this->post_type = $args['post_type'] ?? '';

		// Initialize error handler
		$this->error_handler = ErrorHandler::get_instance();

		$this->actions();
		$this->init();
	}

	/**
	 * Setup actions and filters
	 */
	protected function actions() {
		add_action( 'wp_ajax_cpl_filter_options', [ $this, 'ajax_get_filter_options' ] );
		add_action( 'wp_ajax_nopriv_cpl_filter_options', [ $this, 'ajax_get_filter_options' ] );

		// Add pre_get_posts hook to modify queries based on facets
		add_action( 'pre_get_posts', [ $this, 'apply_facet_filters' ], 5 );

		// Capture query context for applicable post types
		add_action( 'pre_get_posts', [ $this, 'capture_query_context' ], 950 ); // Run early
		add_action( 'wp_footer', [ $this, 'print_query_context' ] );
	}

	/**
	 * Initialize the filter manager
	 * Register default contexts and facets
	 */
	protected function init() {
		$this->register_default_contexts();
		$this->register_default_facets();

		// Let integrations register additional facets
		do_action( "cpl_register_facets_{$this->post_type}", $this );
	}

	/**
	 * Register default contexts
	 * This method should be overridden by child classes
	 */
	abstract protected function register_default_contexts();

	/**
	 * Register default facets
	 * This method should be overridden by child classes
	 */
	abstract protected function register_default_facets();

	/**
	 * Enqueue scripts and styles for filters
	 */
	public function enqueue_scripts() {
		cp_library()->enqueue_asset( 'filters', [ 'jquery' ], CP_LIBRARY_PLUGIN_VERSION, false, true );
	}

	/**
	 * Register a context
	 *
	 * @param string $id   Context ID
	 * @param array  $args Context arguments
	 */
	public function register_context( $id, $args ) {
		$this->contexts[ $id ] = wp_parse_args( $args, [
			'label'          => '',
			'query_callback' => null,
		] );
	}

	/**
	 * Get a specific context
	 *
	 * @param string $id Context ID
	 *
	 * @return array|null
	 */
	public function get_context( $id ) {
		return isset( $this->contexts[ $id ] ) ? $this->contexts[ $id ] : null;
	}

	/**
	 * Get all registered contexts
	 *
	 * @return array
	 */
	public function get_contexts() {
		return $this->contexts;
	}

	/**
	 * Register a facet
	 *
	 * @param string $id   Facet ID
	 * @param array  $args Facet arguments
	 */
	public function register_facet( $id, $args ) {
		// Parse facet arguments with defaults
		$this->facets[ $id ] = wp_parse_args( $args, [
			'label'             => '',
			'param'             => 'facet-' . $id,  // URL parameter name
			'query_var'         => $id,            // WP_Query variable name
			'type'              => 'custom',       // custom, taxonomy, source, meta
			'public'            => true,           // Whether to show in public filter UI
			'query_callback'    => null,           // Callback to modify WP_Query
			'options_callback'  => null,           // Callback to get filter options
			'sanitize_callback' => 'sanitize_text_field',  // Sanitize filter value(s)
			'post_types'        => [ $this->post_type ],   // Compatible post types
		] );
	}

	/**
	 * Convenience method to register a taxonomy facet
	 *
	 * @param string $taxonomy Taxonomy name
	 * @param array  $args     Additional facet arguments
	 *
	 * @return bool Whether registration was successful
	 */
	public function register_taxonomy_facet( $taxonomy, $args = [] ) {
		$taxonomy_object = get_taxonomy( $taxonomy );

		// Skip if the taxonomy doesn't exist
		if ( ! $taxonomy_object ) {
			return false;
		}

		$label = isset( $taxonomy_object->labels->singular_name ) ? $taxonomy_object->labels->singular_name : $taxonomy;

		$args = wp_parse_args( $args, [
			'label'            => $label,
			'param'            => 'facet-' . $taxonomy,
			'query_var'        => $taxonomy,
			'type'             => 'taxonomy',
			'taxonomy'         => $taxonomy,
			'hierarchical'     => $taxonomy_object ? $taxonomy_object->hierarchical : false,
			'query_callback'   => [ $this, 'query_taxonomy_facet' ],
			'options_callback' => [ $this, 'get_taxonomy_options' ],
		] );

		// Create a facet instance using the TaxonomyFacet class
		try {
			$facet_instance = new Facets\TaxonomyFacet( $args );

			// Store the facet instance
			$this->facet_instances[ $taxonomy ] = $facet_instance;

			// Register the facet with the filter manager
			$this->register_facet( $taxonomy, $args );

			return true;
		} catch ( \Exception $e ) {
			error_log( 'Error registering taxonomy facet: ' . $e->getMessage() );

			return false;
		}
	}

	/**
	 * Convenience method to register a meta facet
	 *
	 * @param string $id       Facet ID
	 * @param string $meta_key Meta key
	 * @param array  $args     Additional facet arguments
	 *
	 * @return bool Whether registration was successful
	 */
	public function register_meta_facet( $id, $meta_key, $args = [] ) {
		$args = wp_parse_args( $args, [
			'param'            => 'facet-' . $id,
			'query_var'        => $id,
			'type'             => 'meta',
			'meta_key'         => $meta_key,
			'query_callback'   => [ $this, 'query_meta_facet' ],
			'options_callback' => [ $this, 'get_meta_options' ],
		] );

		// Create a facet instance using the MetaFacet class
		try {
			$facet_instance = new Facets\MetaFacet( $args );

			// Store the facet instance
			$this->facet_instances[ $id ] = $facet_instance;

			// Register the facet with the filter manager
			$this->register_facet( $id, $args );

			return true;
		} catch ( \Exception $e ) {
			error_log( 'Error registering meta facet: ' . $e->getMessage() );

			return false;
		}
	}

	/**
	 * Convenience method to register a source facet
	 *
	 * @param string $id          Facet ID
	 * @param string $source_type Source type (speaker, service_type)
	 * @param array  $args        Additional facet arguments
	 *
	 * @return bool Whether registration was successful
	 */
	public function register_source_facet( $id, $source_type, $args = [] ) {
		$args = wp_parse_args( $args, [
			'label'       => '',
			'param'       => 'facet-' . $id,
			'query_var'   => $id,
			'type'        => 'source',
			'source_type' => $source_type,
		] );

		// Create a facet instance using the SourceFacet class
		try {
			$facet_instance = new Facets\SourceFacet( $args );

			// Store the facet instance
			$this->facet_instances[ $id ] = $facet_instance;

			// Register the facet with the filter manager
			$this->register_facet( $id, $args );

			return true;
		} catch ( \Exception $e ) {
			error_log( 'Error registering source facet: ' . $e->getMessage() );

			return false;
		}
	}

	/**
	 * Get a specific facet configuration
	 *
	 * @param string $id Facet ID
	 *
	 * @return array|null
	 */
	public function get_facet( $id ) {
		return isset( $this->facets[ $id ] ) ? $this->facets[ $id ] : null;
	}

	/**
	 * Get all registered facets
	 *
	 * @param array $args Filter arguments
	 *
	 * @return array
	 */
	public function get_facets( $args = [] ) {
		$args = wp_parse_args( $args, [
			'public' => null,   // true, false, or null for all
			'type'   => null,   // 'taxonomy', 'source', 'meta', 'custom', or null for all
		] );

		$facets = $this->facets;

		// Filter by public status
		if ( $args['public'] !== null ) {
			$facets = array_filter( $facets, function ( $facet ) use ( $args ) {
				return $facet['public'] === $args['public'];
			} );
		}

		// Filter by type
		if ( $args['type'] !== null ) {
			$facets = array_filter( $facets, function ( $facet ) use ( $args ) {
				return $facet['type'] === $args['type'];
			} );
		}

		return $facets;
	}

	/**
	 * AJAX handler for getting filter options
	 */
	public function ajax_get_filter_options() {
		try {

			// Verify request parameters
			$facet_id   = Helpers::get_param( $_POST, 'filter_type', false );
			$selected   = Helpers::get_param( $_POST, 'selected', array() );
			$context    = Helpers::get_param( $_POST, 'context', 'archive' );
			$args       = Helpers::get_param( $_POST, 'args', array() );
			$query_vars = Helpers::get_param( $_POST, 'query_vars', array() );
			$post_type  = Helpers::get_param( $_POST, 'post_type', $this->post_type );

			if ( ! $facet_id ) {
				throw new FilterException(
					__( 'Missing filter_type parameter', 'cp-library' ),
					ErrorCodes::MISSING_PARAMETER,
					[ 'required_param' => 'filter_type' ]
				);
			}

			// Ensure we're handling the correct post type
			if ( $post_type !== $this->post_type ) {
				// The request is for a different post type than this filter manager handles
				// Let the appropriate filter manager handle it via the AJAX action in Init class
				return;
			}

			// Get facet configuration
			$facet = $this->get_facet( $facet_id );
			if ( ! $facet ) {
				throw new FilterException(
					sprintf( __( 'Invalid facet ID: %s', 'cp-library' ), esc_html( $facet_id ) ),
					ErrorCodes::FACET_NOT_FOUND,
					[ 'facet_id' => $facet_id ]
				);
			}

			// Check if this facet is compatible with the requested post type
			if ( ! empty( $facet['post_types'] ) && ! in_array( $post_type, $facet['post_types'] ) ) {
				throw new FilterException(
					sprintf( __( 'Facet not compatible with post type: %s', 'cp-library' ), esc_html( $post_type ) ),
					ErrorCodes::FACET_INCOMPATIBLE,
					[
						'facet'                 => $facet_id,
						'post_type'             => $post_type,
						'compatible_post_types' => $facet['post_types']
					]
				);
			}

			// Convert standard query vars to facet parameter names if needed
			if ( ! empty( $query_vars ) ) {
				$query_vars = $this->convert_query_vars_to_facet_params( $query_vars );
			}

			$query_vars['no_found_rows']          = true;
			$query_vars['posts_per_page']         = 9999;
			$query_vars['fields']                 = 'ids';
			$query_vars['update_post_meta_cache'] = false;
			$query_vars['update_post_term_cache'] = false;

			$terms = $query_vars[ $facet_id ] ?? array();

			if ( ( $query_vars['taxonomy'] ?? false ) === $facet['type'] ) {
				unset( $query_vars['taxonomy'] );
			}

			if ( in_array( ( $query_vars['term'] ?? false ), $terms, true ) ) {
				unset( $query_vars['term'] );
			}

			unset( $query_vars[ $facet_id ] );
			unset( $query_vars['paged'] );
			unset( $query_vars['post_parent'] );

			$query_vars = apply_filters( "cpl_filter_query_vars_{$this->post_type}", $query_vars, $facet_id, $context );

			// Get options for this facet
			$options = $this->get_filter_options( $facet_id, $context, array(
				'selected'     => $selected,
				'context_args' => $args,
				'query_vars'   => $query_vars
			) );

			// Return JSON response with options and param name
			wp_send_json_success( array(
				'options'    => $options,
				'param_name' => $facet['param'] ?? 'facet-' . $facet_id,
				'post_type'  => $this->post_type
			) );

		} catch ( FilterException $e ) {
			// Let the error handler process the exception
			$this->error_handler->handle_exception( $e );
		} catch ( \Exception $e ) {
			// Create a FilterException from a generic exception
			$filterException = new FilterException(
				$e->getMessage(),
				ErrorCodes::GENERAL_ERROR,
				[
					'facet'     => $facet_id ?? '',
					'post_type' => $this->post_type,
					'exception' => get_class( $e )
				]
			);

			$this->error_handler->handle_exception( $filterException );
		}
	}

	/**
	 * Get filter options for a specific facet and context
	 *
	 * @param string $facet_id Facet ID
	 * @param string $context  Context ID
	 * @param array  $args     Additional arguments
	 *
	 * @return array
	 */
	public function get_filter_options( $facet_id, $context = 'archive', $args = [] ) {
		// Parse arguments
		$args = wp_parse_args( $args, [
			'selected'     => [],
			'context_args' => [],
			'query_vars'   => [],
			'threshold'    => (int) Settings::get_advanced( 'filter_count_threshold', 3 ),
		] );

		// Check if facet exists
		$facet = $this->get_facet( $facet_id );
		if ( ! $facet ) {
			return [];
		}

		// Check if context exists
		$context_config = $this->get_context( $context );
		if ( ! $context_config ) {
			return [];
		}

		// Try to get cached options
		$cached_options = $this->get_cached_options( $facet_id, $context, $args );
		if ( false !== $cached_options ) {
			return $cached_options;
		}

		// Build query args based on context
		$query_args = [
			'post_type'              => $this->post_type,
			'post_status'            => 'publish',
			'posts_per_page'         => 9999,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		];

		// Merge in query vars from the request
		if ( 'service-type' == $context && isset( $args['context_args']['service_type_id'] ) ) {
			$query_args['cpl_service_types'] = $args['context_args']['service_type_id'];
		} else if ( ! empty( $args['query_vars'] ) && is_array( $args['query_vars'] ) ) {
			$query_args = array_merge( $args['query_vars'], $query_args );

			// Handle taxonomy terms - exclude the current facet from query vars
			if ( isset( $query_args[ $facet_id ] ) ) {
				unset( $query_args[ $facet_id ] );
			}

			// Also check param name
			if ( isset( $facet['param'] ) && isset( $query_args[ $facet['param'] ] ) ) {
				unset( $query_args[ $facet['param'] ] );
			}

			// Handle pagination
			if ( isset( $query_args['paged'] ) ) {
				unset( $query_args['paged'] );
			}
		}

		// Apply context-specific query modifications
		if ( isset( $context_config['query_callback'] ) && is_callable( $context_config['query_callback'] ) ) {
			$query_args = call_user_func( $context_config['query_callback'], $query_args, $args['context_args'] );
		}

		// Allow customization of query args per post type
		$query_args = apply_filters( "cpl_filter_query_args_{$this->post_type}", $query_args, $facet_id, $context, $args );

		add_filter( 'cpl_bypass_visibility_filtering', '__return_true' );

		// Get items based on context
		$item_ids = get_posts( $query_args );

		// Get options based on facet type
		$options = [];

		// First check if we have a facet instance
		if ( isset( $this->facet_instances[ $facet_id ] ) ) {
			// Use the facet instance's get_options method
			$options = $this->facet_instances[ $facet_id ]->get_options( [
				'post_type'    => $this->post_type,
				'threshold'    => $args['threshold'],
				'post__in'     => $item_ids,
				'context'      => $context,
				'context_args' => $args
			] );
		} // Fallback to using the options callback from the facet config
		else if ( ! empty( $facet['options_callback'] ) && is_callable( $facet['options_callback'] ) ) {
			$options = call_user_func( $facet['options_callback'], [
				'taxonomy'     => $facet['taxonomy'] ?? '',
				'facet_type'   => $facet_id,
				'threshold'    => $args['threshold'],
				'context'      => $context,
				'context_args' => $args,
				'post_type'    => $this->post_type,
				'post__in'     => $item_ids
			] );
		}

		// Cache options
		$this->cache_options( $facet_id, $context, $args, $options );

		return $options;
	}

	/**
	 * Get cached filter options
	 *
	 * @param string $filter_type Filter type ID
	 * @param string $context     Context ID
	 * @param array  $args        Additional arguments
	 *
	 * @return array|false
	 */
	protected function get_cached_options( $filter_type, $context, $args ) {
		// Get cache key
		$cache_key = "cpl_filter_options_{$this->post_type}_" . md5( $filter_type . '_' . $context . '_' . serialize( $args ) );

		// Check transient cache
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		return false;
	}

	/**
	 * Cache filter options
	 *
	 * @param string $filter_type Filter type ID
	 * @param string $context     Context ID
	 * @param array  $args        Additional arguments
	 * @param array  $options     The options to cache
	 */
	protected function cache_options( $filter_type, $context, $args, $options ) {
		// Get cache key
		$cache_key = "cpl_filter_options_{$this->post_type}_" . md5( $filter_type . '_' . $context . '_' . serialize( $args ) );

		// Cache for one hour
		set_transient( $cache_key, $options, HOUR_IN_SECONDS );
	}

	/**
	 * Render filter form
	 *
	 * @param array $args Filter form arguments
	 *
	 * @return string HTML output
	 */
	public function render_filter_form( $args = [] ) {
		// Parse args
		$args = wp_parse_args( $args, [
			'context'          => 'archive',
			'context_args'     => [],
			'disabled_filters' => Settings::get_advanced( 'disable_filters', [] ),
			'show_search'      => true,
			'container_class'  => '',
			'post_type'        => $this->post_type,
		] );

		// Get disabled filters
		$disabled_filters = $args['disabled_filters'];

		// Get available facets (excluding disabled ones and non-public ones)
		$facets = $this->get_facets( [ 'public' => true ] );
		$facets = array_filter( $facets, function ( $id ) use ( $disabled_filters ) {
			return ! in_array( $id, $disabled_filters );
		}, ARRAY_FILTER_USE_KEY );

		$facet_order = ['cpl_topic', 'cpl_scripture', 'cpl_speaker'];
		$ordered_facets = [];

		foreach( $facet_order as $key ) {
			if ( isset( $facets[ $key ] ) ) {
				$ordered_facets[ $key ] = $facets[ $key ];
				unset( $facets[ $key ] );
			}
		}

		// Merge ordered facets with remaining facets
		$facets = array_merge( $ordered_facets, $facets );

		$facets = apply_filters( "cpl_filter_facets_{$this->post_type}", $facets, $args );

		$context_args = '';
		if ( isset( $args['context_args'] ) && is_array( $args['context_args'] ) ) {
			foreach ( $args['context_args'] as $key => $value ) {
				$context_args .= sprintf( ' data-%s="%s"', sanitize_title( str_replace( '_', '-', $key ) ), esc_attr( $value ) );
			}
		}

		// Start output buffer
		ob_start();

		// Load template with variables in scope
		cp_library()->templates->get_template_part( 'parts/filters/form', [
			'facets'            => $facets,
			'context_args'      => $context_args,
			'context_args_data' => $args['context_args'],
			'context'           => $args['context'],
			'container_class'   => $args['container_class'],
			'show_search'       => $args['show_search'],
			'disabled_filters'  => $disabled_filters,
			'post_type'         => $args['post_type'],
		] );

		return ob_get_clean();
	}

	/**
	 * Render selected filters
	 *
	 * @param array $args Selected filters arguments
	 *
	 * @return string HTML output
	 */
	public function render_selected_filters( $args = [] ) {
		// Parse args
		$args = wp_parse_args( $args, [
			'context'      => 'archive',
			'context_args' => [],
			'post_type'    => $this->post_type,
		] );

		// Get request parameters
		$get = $_GET;
		$uri = explode( '?', $_SERVER['REQUEST_URI'] ?? '?' )[0];

		// Pass the facet instances to the template
		$template_args = [
			'args'            => $args,
			'get'             => $get,
			'uri'             => $uri,
			'post_type'       => $args['post_type'],
			'facet_instances' => $this->facet_instances,
			'manager'         => $this,
		];

		// Start output buffer
		ob_start();

		// Load template with variables in scope
		cp_library()->templates->get_template_part( 'parts/filters/selected', $template_args );

		return ob_get_clean();
	}

	/**
	 * Render a selected filter item
	 *
	 * @param string $facet_id Facet ID
	 * @param mixed  $value    The selected value
	 * @param string $uri      Base URI for removal link
	 * @param array  $get      GET parameters
	 *
	 * @return string HTML output
	 */
	public function render_selected_filter_item( $facet_id, $value, $uri, $get ) {
		// First check if we have a facet instance
		if ( isset( $this->facet_instances[ $facet_id ] ) ) {
			// Use the facet instance to render the item
			$facet_config = $this->get_facet( $facet_id );

			return $this->facet_instances[ $facet_id ]->render_selected_item( $value, $facet_config, $uri, $get );
		}

		// Fallback to default rendering based on facet type
		$facet = $this->get_facet( $facet_id );
		if ( ! $facet ) {
			return '';
		}

		$param         = $facet['param'];
		$label         = $facet['label'];
		$display_value = $value;

		// Try to get a better display value based on facet type
		if ( $facet['type'] === 'taxonomy' && ! empty( $facet['taxonomy'] ) ) {
			$term = get_term_by( 'slug', $value, $facet['taxonomy'] );
			if ( $term && ! is_wp_error( $term ) ) {
				$display_value = $term->name;
			}
		}

		// Create a copy of GET params and remove the current value
		$filter_get = $get;

		// Handle the removal of the filter parameter
		if ( isset( $filter_get[ $param ] ) && is_array( $filter_get[ $param ] ) ) {
			$index = array_search( $value, $filter_get[ $param ] );
			if ( $index !== false ) {
				unset( $filter_get[ $param ][ $index ] );
				// If there are no more values for this param, remove the empty array
				if ( empty( $filter_get[ $param ] ) ) {
					unset( $filter_get[ $param ] );
				}
			}
		} else {
			unset( $filter_get[ $param ] );
		}

		// Generate the HTML
		$html = sprintf(
			'<a href="%s" class="cpl-filter--filters--filter" role="button" aria-label="%s">
                <span class="cpl-filter-category" aria-hidden="true">%s:</span>
                %s
                <span class="cpl-filter-remove" aria-hidden="true">×</span>
            </a>',
			esc_url( add_query_arg( $filter_get, $uri ) ),
			esc_attr( sprintf( __( 'Remove %s filter: %s', 'cp-library' ), $label, $display_value ) ),
			esc_html( $label ),
			esc_html( $display_value )
		);

		return $html;
	}

	/**
	 * Apply facet filters to a WP_Query
	 *
	 * @param \WP_Query $query The query object
	 */
	public function apply_facet_filters( $query ) {
		// Skip if not the main query or admin
		if ( is_admin() ) {
			return;
		}

		// Skip if not a post type we're handling
		if ( ! $query_post_type = $query->get( 'post_type' ) ) {
			return;
		}

		if ( ! is_array( $query_post_type ) ) {
			$query_post_type = [ $query_post_type ];
		}

		if ( ! in_array( $this->post_type, $query_post_type ) ) {
			return;
		}

		// Collect active facets from request
		$active_facets = $this->get_active_facets_from_request();

		// Apply each active facet to the query
		foreach ( $active_facets as $facet_id => $values ) {
			$facet_config = $this->get_facet( $facet_id );

			if ( ! $facet_config ) {
				continue;
			}

			// Skip facets that don't apply to this post type
			if ( ! empty( $facet_config['post_types'] ) && ! in_array( $this->post_type, $facet_config['post_types'] ) ) {
				continue;
			}

			// First, check if we have a facet instance
			if ( isset( $this->facet_instances[ $facet_id ] ) ) {
				// Use the facet instance's apply_to_query method
				$this->facet_instances[ $facet_id ]->apply_to_query( $query, $values, $facet_config );
			} // Fallback to using the query callback from the facet config
			else if ( ! empty( $facet_config['query_callback'] ) && is_callable( $facet_config['query_callback'] ) ) {
				call_user_func( $facet_config['query_callback'], $query, $values, $facet_config );
			}
		}
	}

	/**
	 * Get active facets from the current request
	 *
	 * @return array Array of facet_id => values
	 */
	public function get_active_facets_from_request() {
		$active_facets = [];

		// Get all registered facets
		$facets = $this->get_facets();

		// Check each facet for a matching URL parameter
		foreach ( $facets as $facet_id => $facet_config ) {
			$param = $facet_config['param'];

			// Skip if parameter is not in the request
			if ( ! isset( $_GET[ $param ] ) ) {
				continue;
			}

			$values = $_GET[ $param ];

			// Normalize to array
			if ( ! is_array( $values ) ) {
				$values = [ $values ];
			}

			// Skip empty values
			if ( empty( $values ) ) {
				continue;
			}

			// Sanitize values using facet's sanitize callback
			$sanitize_callback = $facet_config['sanitize_callback'] ?? 'sanitize_text_field';
			$values            = array_map( $sanitize_callback, $values );

			// Remove empty values after sanitization
			$values = array_filter( $values );

			if ( ! empty( $values ) ) {
				$active_facets[ $facet_id ] = $values;
			}
		}

		return $active_facets;
	}

	/**
	 * Default query callback for taxonomy facets
	 *
	 * @param \WP_Query $query        The query object
	 * @param array     $values       The facet values
	 * @param array     $facet_config The facet configuration
	 */
	public function query_taxonomy_facet( $query, $values, $facet_config ) {
		$taxonomy = $facet_config['taxonomy'];

		if ( empty( $taxonomy ) || empty( $values ) ) {
			return;
		}

		// Get existing tax queries
		$tax_query = $query->get( 'tax_query' ) ?: [];

		// Add our taxonomy query
		$tax_query[] = [
			'taxonomy' => $taxonomy,
			'field'    => 'slug',
			'terms'    => $values,
			'operator' => 'IN',
		];

		// Set the tax query
		$query->set( 'tax_query', $tax_query );
	}

	/**
	 * Default query callback for meta facets
	 *
	 * @param \WP_Query $query        The query object
	 * @param array     $values       The facet values
	 * @param array     $facet_config The facet configuration
	 */
	public function query_meta_facet( $query, $values, $facet_config ) {
		$meta_key = $facet_config['meta_key'];

		if ( empty( $meta_key ) || empty( $values ) ) {
			return;
		}

		// Get existing meta queries
		$meta_query = $query->get( 'meta_query' ) ?: [];

		// Add our meta query
		$meta_query[] = [
			'key'     => $meta_key,
			'value'   => $values,
			'compare' => 'IN',
		];

		// Set the meta query
		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * Convert standard query vars to facet parameter names
	 * This helps with backward compatibility as JS might be using standard parameter names
	 *
	 * @param array $query_vars The query variables to convert
	 *
	 * @return array Updated query vars with facet parameters
	 */
	public function convert_query_vars_to_facet_params( $query_vars ) {
		// Get all registered facets
		$facets = $this->get_facets();

		// Create a mapping of standard parameter names to facet parameter names
		$param_mapping = [];
		foreach ( $facets as $id => $facet ) {
			// Skip if the facet doesn't have a param
			if ( empty( $facet['param'] ) ) {
				continue;
			}

			// Map both the facet ID and any query_var (for taxonomies) to the facet param
			$param_mapping[ $id ] = $facet['param'];
			if ( ! empty( $facet['query_var'] ) ) {
				$param_mapping[ $facet['query_var'] ] = $facet['param'];
			}
		}

		// Convert query vars
		$converted_vars = $query_vars;
		foreach ( $query_vars as $key => $value ) {
			// Skip if key doesn't need conversion or is already a facet param
			if ( ! isset( $param_mapping[ $key ] ) || strpos( $key, 'facet-' ) === 0 ) {
				continue;
			}

			// Add with the facet parameter name
			$converted_vars[ $param_mapping[ $key ] ] = $value;

			// Remove the original if it's not needed for WP_Query
			// Only remove if the key isn't a standard WP_Query parameter
			$wp_query_vars = [ 'post_type', 'post_status', 'posts_per_page', 's', 'orderby', 'order' ];
			if ( ! in_array( $key, $wp_query_vars ) ) {
				unset( $converted_vars[ $key ] );
			}
		}

		return $converted_vars;
	}

	/**
	 * Get meta facet options
	 *
	 * @param array $args Arguments
	 *
	 * @return array Options
	 */
	public function get_meta_options( $args = [] ) {
		global $wpdb;

		$args = wp_parse_args( $args, [
			'meta_key'  => '',
			'post_type' => $this->post_type,
			'threshold' => 1,
			'orderby'   => 'count',
			'order'     => 'DESC',
			'post__in'  => [],
		] );

		if ( empty( $args['meta_key'] ) ) {
			return [];
		}

		// Build where clause for post__in
		$where_in = '';
		if ( ! empty( $args['post__in'] ) ) {
			$where_in = ' AND p.ID IN (' . implode( ',', array_map( 'absint', $args['post__in'] ) ) . ')';
		}

		// Get unique meta values with counts
		$query = $wpdb->prepare(
			"SELECT meta_value AS value, COUNT(*) AS count, meta_value AS title, meta_value AS id
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = %s
            AND p.post_type = %s
            AND p.post_status = 'publish'
            {$where_in}
            GROUP BY meta_value
            HAVING count >= %d
            ORDER BY " . ( $args['orderby'] === 'count' ? "count {$args['order']}" : "meta_value {$args['order']}" ),
			$args['meta_key'],
			$args['post_type'],
			$args['threshold']
		);

		$results = $wpdb->get_results( $query );

		return $results ?: [];
	}

	/**
	 * Get taxonomy options
	 *
	 * @param array $args Arguments
	 *
	 * @return array Options
	 */
	public function get_taxonomy_options( $args = [] ) {
		global $wpdb;

		$args = wp_parse_args( $args, [
			'taxonomy'  => '',
			'post_type' => $this->post_type,
			'threshold' => 3,
			'orderby'   => 'count',
			'order'     => 'DESC',
			'post__in'  => [],
		] );

		if ( empty( $args['taxonomy'] ) ) {
			return [];
		}

		// Build where clause for post__in
		$where_in = '';
		if ( ! empty( $args['post__in'] ) ) {
			$where_in = ' AND p.ID IN (' . implode( ',', array_map( 'absint', $args['post__in'] ) ) . ')';
		}

		// Build the query with the where clause included manually
		$query = $wpdb->prepare(
			"SELECT
                t.name AS title,
                t.term_id AS id,
                t.slug AS value,
                COUNT(p.ID) AS count
            FROM
                {$wpdb->terms} AS t
            LEFT JOIN
                {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
            LEFT JOIN
                {$wpdb->term_relationships} AS tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            LEFT JOIN
                {$wpdb->posts} AS p ON tr.object_id = p.ID {$where_in}
            WHERE
                tt.taxonomy = %s
            AND
                p.post_type = %s
            AND
                p.post_status = 'publish'
            GROUP BY
                t.term_id
            HAVING
                count >= %d
            ORDER BY
                " . ( $args['orderby'] === 'count' ? "count {$args['order']}" : "title {$args['order']}" ),
			$args['taxonomy'],
			$args['post_type'],
			$args['threshold']
		);

		$output = $wpdb->get_results( $query );

		// Special handling for scripture ordering
		if ( $args['taxonomy'] === 'cpl_scripture' && $args['orderby'] !== 'count' ) {
			usort( $output, [ '\CP_Library\Filters', 'sort_scripture' ] );
		}

		return $output ?: [];
	}

	/**
	 * Storage for the captured query context
	 *
	 * @var array
	 */
	protected $query_context = [];

	/**
	 * Flag to track if we've captured a query for this post type yet
	 *
	 * @var boolean
	 */
	protected $query_context_captured = false;

	/**
	 * Capture query context from applicable queries
	 * This runs early in pre_get_posts to capture query vars before filters are applied
	 *
	 * @param \WP_Query $query The query object
	 */
	public function capture_query_context( $query ) {
		// Skip if we've already captured a query for this post type
		if ( $this->query_context_captured || is_admin() ) {
			return;
		}

		// Skip if not a post type we're handling
		if ( ! $query_post_type = $query->get( 'post_type' ) ) {
			return;
		}

		if ( ! is_array( $query_post_type ) ) {
			$query_post_type = [ $query_post_type ];
		}

		if ( ! in_array( $this->post_type, $query_post_type ) ) {
			return;
		}

		// Capture the query vars for this post type
		$this->query_context = $query->query_vars;

		// Remove pagination-related vars
		unset( $this->query_context['paged'] );
		unset( $this->query_context['page'] );
		unset( $this->query_context['posts_per_page'] );
		unset( $this->query_context['offset'] );
		unset( $this->query_context['taxonomy'] );
		unset( $this->query_context['term'] );

		// Remove internal WP_Query vars that shouldn't be passed to the filter
		unset( $this->query_context['preview'] );
		unset( $this->query_context['lazy_load_term_meta'] );
		unset( $this->query_context['ignore_sticky_posts'] );

		// Flag that we've captured a query for this post type
		$this->query_context_captured = true;
	}

	/**
	 * Print the captured query context to the footer
	 * This outputs JavaScript that merges the query context into the cplVars object
	 */
	public function print_query_context() {
		// Skip if no query context was captured or it's empty
		if ( empty( $this->query_context ) ) {
			return;
		}

		// Add post type to context for reference
		$this->query_context['post_type'] = $this->post_type;

		// Output JavaScript to update cplVars
		?>
		<script>
		(function() {

			if ( ! window.cplFilter ) {
				window.cplFilter = {};
			}

			if ( ! window.cplFilter.query_context ) {
				window.cplFilter.query_context = {};
			}

			// Add this post type's context
			window.cplFilter.query_context['<?php echo esc_js( $this->post_type ); ?>'] = <?php echo wp_json_encode( $this->query_context ); ?>;
		})();
		</script>
		<?php
	}

	/**
	 * Get a reference to the post type handled by this filter manager
	 *
	 * @return string Post type name
	 */
	public function get_post_type() {
		return $this->post_type;
	}

	/**
	 * Register all public taxonomies for a post type
	 *
	 * @param string|array $post_type          Post type(s) to register taxonomies for
	 * @param array        $exclude_taxonomies Optional array of taxonomy names to exclude
	 *
	 * @return int Number of taxonomies registered
	 */
	public function register_taxonomies_for_post_type( $post_type, $exclude_taxonomies = [] ) {
		$count      = 0;
		$post_types = is_array( $post_type ) ? $post_type : [ $post_type ];

		// Get all registered taxonomies
		$taxonomies = get_object_taxonomies( $post_types, 'objects' );

		$exclude_taxonomies = apply_filters( 'cpl_exclude_taxonomy_facets', $exclude_taxonomies, $post_types );

		foreach ( $taxonomies as $taxonomy ) {
			// Skip excluded taxonomies
			if ( in_array( $taxonomy->name, $exclude_taxonomies ) ) {
				continue;
			}

			if ( ! $taxonomy->public ) {
				continue;
			}

			// Register this taxonomy as a facet
			if ( $this->register_taxonomy_facet( $taxonomy->name ) ) {
				$count ++;
			}
		}

		return $count;
	}
}
