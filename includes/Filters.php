<?php

namespace CP_Library;

use ChurchPlugins\Helpers;
use CP_Library\Admin\Settings;

/**
 * Filters class for managing sermon filters across different contexts
 *
 * @since 1.6.0
 */
class Filters {

	/**
	 * Singleton instance
	 *
	 * @var Filters
	 */
	private static $instance = null;

	/**
	 * Registered contexts
	 *
	 * @var array
	 */
	private $contexts = [];

	/**
	 * Registered facets
	 *
	 * @var array
	 */
	private $facets = [];

	/**
	 * Get the singleton instance
	 *
	 * @return Filters
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor
	 */
	private function __construct() {
		$this->actions();
	}

	/**
	 * Setup actions and filters
	 */
	private function actions() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_cpl_filter_options', [ $this, 'ajax_get_filter_options' ] );
		add_action( 'wp_ajax_nopriv_cpl_filter_options', [ $this, 'ajax_get_filter_options' ] );
		add_action( 'init', [ $this, 'init' ], 20 );

		// Add pre_get_posts hook to modify queries based on facets
		add_action( 'pre_get_posts', [ $this, 'apply_facet_filters' ] );
	}

	public function init() {
		$this->register_default_contexts();
		$this->register_default_facets();

		// Let post types and taxonomies register their facets
		do_action( 'cpl_register_facets', $this );
	}

	/**
	 * Register default facets for taxonomies
	 */
	private function register_default_facets() {
		// Register taxonomy facets
		$taxonomies = cp_library()->setup->taxonomies->get_objects();
		foreach ( $taxonomies as $tax ) {
			// Skip if taxonomy object is null
			if ( ! $tax || ! isset( $tax->taxonomy ) ) {
				continue;
			}

			$this->register_taxonomy_facet( $tax->taxonomy, [
				'label'        => $tax->single_label,
				'param'        => 'facet-' . $tax->taxonomy,
				'query_var'    => $tax->taxonomy,
				'public'       => true,
				'hierarchical' => isset( $tax->hierarchical ) ? $tax->hierarchical : false,
			]);
		}
	}

	/**
	 * Enqueue scripts and styles for filters
	 */
	public function enqueue_scripts() {
		cp_library()->enqueue_asset( 'filters', ['jquery'], CP_LIBRARY_PLUGIN_VERSION, false, true );
	}


	/**
	 * Register default contexts
	 */
	private function register_default_contexts() {
		// Archive context
		$this->register_context( 'archive', [
			'label' => __( 'Archive', 'cp-library' ),
		] );

		// Service Type context
		$this->register_context( 'service-type', [
			'label' => __( 'Service Type', 'cp-library' ),
		] );
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
		]);
	}

	/**
	 * Convenience method to register a taxonomy facet
	 *
	 * @param string $taxonomy Taxonomy name
	 * @param array  $args     Additional facet arguments
	 */
	public function register_taxonomy_facet( $taxonomy, $args = [] ) {
		$taxonomy_object = get_taxonomy( $taxonomy );
		$label = isset( $taxonomy_object->labels->singular_name ) ? $taxonomy_object->labels->singular_name : $taxonomy;

		$args = wp_parse_args( $args, [
			'label'        => $label,
			'param'        => 'facet-' . $taxonomy,
			'query_var'    => $taxonomy,
			'type'         => 'taxonomy',
			'taxonomy'     => $taxonomy,
			'hierarchical' => $taxonomy_object ? $taxonomy_object->hierarchical : false,
			'query_callback' => [ $this, 'query_taxonomy_facet' ],
			'options_callback' => [ $this, 'get_taxonomy_options' ],
		]);

		$this->register_facet( $taxonomy, $args );
	}

	/**
	 * Convenience method to register a meta facet
	 *
	 * @param string $id       Facet ID
	 * @param string $meta_key Meta key
	 * @param array  $args     Additional facet arguments
	 */
	public function register_meta_facet( $id, $meta_key, $args = [] ) {
		$args = wp_parse_args( $args, [
			'param'           => 'facet-' . $id,
			'query_var'       => $id,
			'type'            => 'meta',
			'meta_key'        => $meta_key,
			'query_callback'  => [ $this, 'query_meta_facet' ],
			'options_callback' => [ $this, 'get_meta_options' ],
		]);

		$this->register_facet( $id, $args );
	}

	/**
	 * Get a specific facet configuration
	 *
	 * @param string $id Facet ID
	 * @return array|null
	 */
	public function get_facet( $id ) {
		return isset( $this->facets[ $id ] ) ? $this->facets[ $id ] : null;
	}

	/**
	 * Get all registered facets
	 *
	 * @param array $args Filter arguments
	 * @return array
	 */
	public function get_facets( $args = [] ) {
		$args = wp_parse_args( $args, [
			'public' => null,   // true, false, or null for all
			'type'   => null,   // 'taxonomy', 'source', 'meta', 'custom', or null for all
		]);

		$facets = $this->facets;

		// Filter by public status
		if ( $args['public'] !== null ) {
			$facets = array_filter( $facets, function( $facet ) use ( $args ) {
				return $facet['public'] === $args['public'];
			});
		}

		// Filter by type
		if ( $args['type'] !== null ) {
			$facets = array_filter( $facets, function( $facet ) use ( $args ) {
				return $facet['type'] === $args['type'];
			});
		}

		return $facets;
	}

	/**
	 * AJAX handler for getting filter options
	 */
	public function ajax_get_filter_options() {
		// Verify request parameters
		$facet_id    = Helpers::get_param( $_POST, 'filter_type', false );
		$selected    = Helpers::get_param( $_POST, 'selected', array() );
		$context     = Helpers::get_param( $_POST, 'context', 'archive' );
		$args        = Helpers::get_param( $_POST, 'args', array() );
		$query_vars  = Helpers::get_param( $_POST, 'query_vars', array() );

		if ( ! $facet_id ) {
			wp_send_json_error( array( 'message' => 'Missing filter_type parameter' ) );
		}

		// Get facet configuration
		$facet = $this->get_facet( $facet_id );
		if ( ! $facet ) {
			wp_send_json_error( array( 'message' => 'Invalid facet ID' ) );
		}

		// Convert standard query vars to facet parameter names if needed
		if ( ! empty( $query_vars ) ) {
			$query_vars = $this->convert_query_vars_to_facet_params( $query_vars );
		}

		// Fallback to old method for backward compatibility
		$options = $this->get_filter_options( $facet_id, $context, array(
			'selected'   => $selected,
			'context_args' => $args,
			'query_vars' => $query_vars
		) );

		// Return JSON response with options and param name
		wp_send_json_success( array(
			'options' => $options,
			'param_name' => $facet['param'] ?? 'facet-' . $facet_id
		) );
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
		$cached_options = false; // $this->get_cached_options( $facet_id, $context, $args );
		if ( false !== $cached_options ) {
			return $cached_options;
		}

		// Build query args based on context
		$query_args = [
			'post_type'      => 'cpl_item',
			'post_status'    => 'publish',
			'posts_per_page' => 9999,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		];

		// Merge in query vars from the request
		if ( 'service-type' == $context && isset( $args['context_args']['service_type_id'] ) ) {
			$query_args['cpl_service_types'] = $args['context_args']['service_type_id'];
		} else if ( !empty( $args['query_vars'] ) && is_array( $args['query_vars'] ) ) {
			$query_args = array_merge( $args['query_vars'], $query_args );

			// Handle taxonomy terms - exclude the current facet from query vars
			if ( isset( $query_args[$facet_id] ) ) {
				unset( $query_args[$facet_id] );
			}

			// Also check param name
			if ( isset( $facet['param'] ) && isset( $query_args[$facet['param']] ) ) {
				unset( $query_args[$facet['param']] );
			}

			// Handle pagination
			if ( isset( $query_args['paged'] ) ) {
				unset( $query_args['paged'] );
			}

			if ( isset( $query_args['cpl_service_type'] ) ) {
				unset( $query_args['cpl_service_type'] );
			}
		}

		// Apply context-specific query modifications
		if ( isset( $context_config['query_callback'] ) && is_callable( $context_config['query_callback'] ) ) {
			$query_args = call_user_func( $context_config['query_callback'], $query_args, $args['context_args'] );
		}

		// Get items based on context
		$query = new \WP_Query( $query_args );
		$item_ids = $query->posts;

		// Get options based on facet type
		$options = [];

		if ( ! empty( $facet['options_callback'] ) && is_callable( $facet['options_callback'] ) ) {
			$options = call_user_func( $facet['options_callback'], [
				'taxonomy'   => $facet['taxonomy'] ?? '',
				'threshold'  => (int) Settings::get_advanced( 'filter_count_threshold', 3 ),
				'context'    => $context,
				'context_args' => $args,
				'item_ids' => $item_ids
			]);
		}

		switch ( $facet['type'] ) {
			case 'taxonomy':
				// Get taxonomy filter options
				$taxonomy = $facet['taxonomy'];

				// Different default for scripture
				$default_order_by = 'cpl_scripture' === $taxonomy ? 'name' : 'sermon_count';
				$order_by = Settings::get_advanced( 'sort_' . $taxonomy, $default_order_by );

				$term_args = [
					'taxonomy'   => $taxonomy,
					'hide_empty' => true,
					'orderby'    => 'count' === $order_by ? 'count' : 'name',
					'order'      => 'count' === $order_by ? 'DESC' : 'ASC',
				];

				// Only include terms associated with the filtered items
				if ( !empty( $item_ids ) ) {
					$term_args['object_ids'] = $item_ids;
				}

				$terms = get_terms( $term_args );

				foreach ( $terms as $term ) {
					// Skip terms with fewer items than threshold
					if ( $term->count < $args['threshold'] ) {
						continue;
					}

					$option = (object) [
						'title' => $term->name,
						'value' => $term->slug,
						'count' => $term->count,
						'id'    => $term->term_id,
					];

					$options[] = $option;
				}

				// Special handling for scripture ordering
				if ( 'cpl_scripture' === $taxonomy && 'name' === $order_by ) {
					usort( $options, '\CP_Library\Filters::sort_scripture' );
				}
				break;

			case 'source':
				// Get source filter options (speakers, service types)
				$order_by = Settings::get_advanced( 'sort_' . $facet_id, 'sermon_count' );

				$source_args = [
					'facet_type' => $facet_id,
					'order_by'   => 'count' === $order_by ? 'count' : 'title',
					'threshold'  => $args['threshold'],
					'post_type'  => 'cpl_item',
				];

				// Only include sources associated with the filtered items
				if ( !empty( $item_ids ) ) {
					$source_args['post__in'] = $item_ids;
				}

				try {
					$options = $this->get_sources($source_args);
				} catch ( \Exception $e ) {
					$options = [];
				}
				break;

			default:
				// For any other type, try to use custom options_callback if available
				if ( ! empty( $facet['options_callback'] ) && is_callable( $facet['options_callback'] ) ) {
					$options = call_user_func( $facet['options_callback'], $query_args, $args );
				}
				break;
		}

		// Cache options
		$this->cache_options( $facet_id, $context, $args, $options );

		return $options;
	}

	/**
	 * Get sources (speakers or service types)
	 *
	 * @param array $args The arguments for the query.
	 * @return array
	 * @throws \ChurchPlugins\Exception If the arguments are invalid.
	 */
	public function get_sources( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'order_by'   => 'count',
				'threshold'  => 3,
				'facet_type' => '',
				'post__in'   => array(),
				'post_type' => cp_library()->setup->post_types->item->post_type,
			)
		);

		if ( ! in_array( $args['facet_type'], array( 'speaker', 'service-type' ) ) ) {
			throw new \ChurchPlugins\Exception( 'Invalid facet type' );
		}

		$order_by = ( 'count' === $args['order_by'] ) ? 'count DESC' : 'title ASC';

		// Build where clause for post__in
		$where_clause = '1 = 1';
		if ( ! empty( $args['post__in'] ) ) {
			$where_clause = 'sermon.origin_id IN (' . implode( ',', array_map( 'absint', $args['post__in'] ) ) . ')';
		}

		global $wpdb;

		// Format the SQL manually with the where clause
		$sql = $wpdb->prepare(
			'SELECT
				source.id,
				source.id AS value,
				source.title AS title,
				COUNT(sermon.id) AS count
			FROM
				%1$s AS source
			LEFT JOIN
				%2$s AS meta ON meta.source_id = source.id
			INNER JOIN
				%3$s AS type ON meta.source_type_id = type.id AND type.title = \'%4$s\'
			LEFT JOIN
				%5$s AS sermon ON meta.item_id = sermon.id AND ' . $where_clause . '
			GROUP BY
				source.id
			HAVING
				count >= %6$d
			ORDER BY
				%7$s;',
			$wpdb->prefix . 'cp_source',
			$wpdb->prefix . 'cp_source_meta',
			$wpdb->prefix . 'cp_source_type',
			'service-type' === $args['facet_type'] ? 'service_type' : $args['facet_type'],
			$wpdb->prefix . 'cpl_item',
			$args['threshold'],
			$order_by
		);

		$speakers = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! $speakers ) {
			$speakers = array();
		}

		return $speakers;
	}


	/**
	 * Get terms
	 *
	 * @param array $args The arguments for getting terms.
	 * @return array
	 */
	public function get_terms( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'order_by'   => 'sermon_count',
				'threshold'  => 3,
				'facet_type' => '',
				'post__in'   => array(),
				'post_type' => cp_library()->setup->post_types->item->post_type,
			)
		);

		if ( ! in_array( $args['facet_type'], cp_library()->setup->taxonomies->get_taxonomies(), true ) ) {
			throw new \ChurchPlugins\Exception( 'Invalid facet type' );
		}

		$order_by = ( 'count' === $args['order_by'] ) ? 'count DESC' : 'title ASC';

		// Build where clause for post__in
		$where_clause = '1 = 1';
		if ( ! empty( $args['post__in'] ) ) {
			$where_clause = 'p.ID IN (' . implode( ',', array_map( 'absint', $args['post__in'] ) ) . ')';
		}

		global $wpdb;

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
				{$wpdb->posts} AS p ON tr.object_id = p.ID AND {$where_clause}
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
				{$order_by}
			",
			$args['facet_type'],
			$args['post_type'],
			$args['threshold']
		);

		$output = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( $args['facet_type'] === 'cpl_scripture' && 'name' === $args['order_by'] ) {
			usort( $output, 'CP_Library\Filters::sort_scripture' );
		}

		return $output ? $output : array();
	}

	/**
	 * Sort terms by scripture order
	 *
	 * @since  1.3.0
	 *
	 * @param $a
	 * @param $b
	 *
	 * @return int|string
	 * @author Tanner Moushey, 10/20/23
	 */
	public static function sort_scripture( $a, $b ) {
		$book_order = array_values( cp_library()->setup->taxonomies->scripture->get_terms() );
		$index_a = array_search( $a->title, $book_order );
		$index_b = array_search( $b->title, $book_order );

		if ( $index_a === false || $index_b === false ) {
			return 0;
		}

		return $index_a - $index_b;
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
	private function get_cached_options( $filter_type, $context, $args ) {
		// Get cache key
		$cache_key = 'cpl_filter_options_' . md5( $filter_type . '_' . $context . '_' . serialize( $args ) );

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
	private function cache_options( $filter_type, $context, $args, $options ) {
		// Get cache key
		$cache_key = 'cpl_filter_options_' . md5( $filter_type . '_' . $context . '_' . serialize( $args ) );

		// Cache for one hour
		set_transient( $cache_key, $options, HOUR_IN_SECONDS );
	}

	/**
	 * Render filter form
	 *
	 * @param array $args Filter form arguments
	 */
	public function render_filter_form( $args = [] ) {
		// Parse args
		$args = wp_parse_args( $args, [
			'context'          => 'archive',
			'context_args'     => [],
			'disabled_filters' => Settings::get_advanced( 'disable_filters', [] ),
			'show_search'      => true,
			'container_class'  => '',
		] );

		// Get disabled filters
		$disabled_filters = $args['disabled_filters'];

		// Get available facets (excluding disabled ones and non-public ones)
		$facets = $this->get_facets(['public' => true]);
		$facets = array_filter( $facets, function ( $id ) use ( $disabled_filters ) {
			return ! in_array( $id, $disabled_filters );
		}, ARRAY_FILTER_USE_KEY );

		$context_args = '';
		if ( isset( $args['context_args'] ) && is_array( $args['context_args'] ) ) {
			foreach ( $args['context_args'] as $key => $value ) {
				$context_args .= sprintf( ' data-%s="%s"', sanitize_title( str_replace( '_', '-', $key ) ), esc_attr( $value ) );
			}
		}

		// Start output buffer
		ob_start();

		// Load template with variables in scope
		cp_library()->templates->get_template_part('parts/filters/form', [
			'facets' => $facets,
			'context_args' => $context_args,
			'context_args_data' => $args['context_args'],
			'context' => $args['context'],
			'container_class' => $args['container_class'],
			'show_search' => $args['show_search'],
			'disabled_filters' => $disabled_filters
		]);

		return ob_get_clean();
	}

	/**
	 * Render selected filters
	 *
	 * @param array $args Selected filters arguments
	 */
	public function render_selected_filters( $args = [] ) {
		// Parse args
		$args = wp_parse_args( $args, [
			'context'      => 'archive',
			'context_args' => [],
		] );

		// Get request parameters
		$get = $_GET;
		$uri = explode( '?', $_SERVER['REQUEST_URI'] ?? '?' )[0];

		// Start output buffer
		ob_start();

		// Load template with variables in scope
		cp_library()->templates->get_template_part('parts/filters/selected', [
			'args' => $args,
			'get' => $get,
			'uri' => $uri
		]);

		return ob_get_clean();
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
		if ( ! $post_type = $query->get( 'post_type' ) ) {
			return;
		}

		if ( ! is_array( $post_type ) ) {
			$post_type = [ $post_type ];
		}

		if (  is_array( $post_type ) && ! in_array( 'cpl_item', $post_type ) ) {
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

			// Use the facet's query_callback if it has one
			if ( ! empty( $facet_config['query_callback'] ) && is_callable( $facet_config['query_callback'] ) ) {
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
			$values = array_map( $sanitize_callback, $values );

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
	 * Get taxonomy facet options
	 *
	 * @param array $args Arguments
	 * @return array Options
	 */
	public function get_taxonomy_options( $args = [] ) {
		$args = wp_parse_args( $args, [
			'taxonomy'   => '',
			'threshold'  => 1,
			'orderby'    => 'count',
			'order'      => 'DESC',
			'hide_empty' => true,
		]);

		if ( empty( $args['taxonomy'] ) ) {
			return [];
		}

		// Get terms
		$terms = get_terms([
			'taxonomy'   => $args['taxonomy'],
			'hide_empty' => $args['hide_empty'],
			'orderby'    => $args['orderby'],
			'order'      => $args['order'],
		]);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return [];
		}

		// Format terms as options
		$options = [];
		foreach ( $terms as $term ) {
			// Skip terms with fewer items than threshold
			if ( $term->count < $args['threshold'] ) {
				continue;
			}

			$options[] = [
				'id'    => $term->term_id,
				'value' => $term->slug,
				'title' => $term->name,
				'count' => $term->count,
			];
		}

		return $options;
	}

	/**
	 * Convert standard query vars to facet parameter names
	 * This helps with backward compatibility as JS might be using standard parameter names
	 *
	 * @param array $query_vars The query variables to convert
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
			$param_mapping[$id] = $facet['param'];
			if ( !empty( $facet['query_var'] ) ) {
				$param_mapping[$facet['query_var']] = $facet['param'];
			}
		}

		// Convert query vars
		$converted_vars = $query_vars;
		foreach ( $query_vars as $key => $value ) {
			// Skip if key doesn't need conversion or is already a facet param
			if ( !isset( $param_mapping[$key] ) || strpos( $key, 'facet-' ) === 0 ) {
				continue;
			}

			// Add with the facet parameter name
			$converted_vars[$param_mapping[$key]] = $value;

			// Remove the original if it's not needed for WP_Query
			// Only remove if the key isn't a standard WP_Query parameter
			$wp_query_vars = ['post_type', 'post_status', 'posts_per_page', 's', 'orderby', 'order'];
			if ( !in_array( $key, $wp_query_vars ) ) {
				unset( $converted_vars[$key] );
			}
		}

		return $converted_vars;
	}

	/**
	 * Get meta facet options
	 *
	 * @param array $args Arguments
	 * @return array Options
	 */
	public function get_meta_options( $args = [] ) {
		global $wpdb;

		$args = wp_parse_args( $args, [
			'meta_key'   => '',
			'post_type'  => 'cpl_item',
			'threshold'  => 1,
			'orderby'    => 'count',
			'order'      => 'DESC',
		]);

		if ( empty( $args['meta_key'] ) ) {
			return [];
		}

		// Get unique meta values with counts
		$query = $wpdb->prepare(
			"SELECT meta_value AS value, COUNT(*) AS count
			FROM {$wpdb->postmeta} pm
			JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = %s
			AND p.post_type = %s
			AND p.post_status = 'publish'
			GROUP BY meta_value
			HAVING count >= %d
			ORDER BY " . ( $args['orderby'] === 'count' ? "count {$args['order']}" : "meta_value {$args['order']}" ),
			$args['meta_key'],
			$args['post_type'],
			$args['threshold']
		);

		$results = $wpdb->get_results( $query );

		if ( empty( $results ) ) {
			return [];
		}

		// Format results as options
		$options = [];
		foreach ( $results as $result ) {
			$options[] = [
				'id'    => $result->value,
				'value' => $result->value,
				'title' => $result->value,
				'count' => $result->count,
			];
		}

		return $options;
	}
}
