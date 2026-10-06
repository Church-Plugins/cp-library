<?php

namespace CP_Library\API;

use ChurchPlugins\Models\Log;
use CP_Library\Controllers\Item;
use CP_Library\Models\Item as ItemModel;
use CP_Library\Exception;
use CP_Library\Models\ItemType;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST API Controller for Items objects
 *
 * @since 1.0.0
 *
 * @see WP_REST_Controller
 */
class Items extends WP_REST_Controller {

	public $post_type;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function __construct() {
		$this->namespace = cp_library()->get_api_namespace();
		$this->rest_base = 'items';
		$this->post_type = CP_LIBRARY_UPREFIX . '_item';
		add_action( 'rest_api_init', array( $this, 'register_custom_query_parameters' ) );
	}

	/**
	 * Registers the routes for the objects of the controller.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @see register_rest_route()
	 */
	public function register_routes() {

		register_rest_route( $this->namespace, $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_permissions_check' ),
			),
//			array(
//				'methods'             => WP_REST_Server::CREATABLE,
//				'callback'            => array( $this, 'create_item' ),
//				'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
//				'permission_callback' => array( $this, 'get_permissions_check' ),
//			),
//			array(
//				'methods'             => WP_REST_Server::DELETABLE,
//				'callback'            => array( $this, 'delete_all_items' ),
//				'permission_callback' => array( $this, 'get_permissions_check' ),
//			),
			// 'schema' => array( $this, 'get_public_item_schema' ),
		) );

		register_rest_route( $this->namespace, $this->rest_base . '/(?P<item_id>[^.\/]+)', array(
			'args' => array(
				'item_id' => array(
					'description' => __( 'The ID of the item.', 'cp-library' ),
					'type'        => 'string',
					'required'    => true,
				),
			),
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_permissions_check' ),
			),
//			array(
//				'methods'             => WP_REST_Server::DELETABLE,
//				'callback'            => array( $this, 'delete_item' ),
//				'permission_callback' => array( $this, 'get_permissions_check' ),
//			),
			// 'schema' => array( $this, 'get_public_item_schema' ),
		) );

		register_rest_route( $this->namespace, $this->rest_base . '-dictionary', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_topic_dictionary' ),
				'permission_callback' => array( $this, 'get_permissions_check' ),
			),
		) );

		register_rest_route( $this->namespace, $this->rest_base . '/(?P<item_id>\d+)/log/', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'log' ),
				'permission_callback' => array( $this, 'get_permissions_check' ),
			),
		) );

//        register_rest_route( $this->namespace, $this->rest_base . '/edit', array(
//            array(
//                'methods'             => WP_REST_Server::CREATABLE,
//                'callback'            => array( $this, 'edit_item' ),
//                'permission_callback' => array( $this, 'get_permissions_check' ),
//            ),
//            'schema' => array( $this, 'get_public_item_schema' ),
//        ) );

	}

	public function log( $request ) {

		try {

			$item_id = $this->log_item_id( $request->get_param( 'item_id' ) );

			if ( ! $item_id ) {
				throw new Exception( 'No item_id specified' );
			}

			if ( ! $action = $request->get_param( 'action' ) ) {
				throw new Exception( 'No action specified' );
			}

			if ( ! is_string( $action ) || ! in_array( $action, $this->get_log_actions(), true ) ) {
				throw new Exception( 'Invalid action specified' );
			}

			// Throws when this id is not an item row.
			ItemModel::get_instance( $item_id );

			if ( ! $this->get_log_ip() || $this->is_log_rate_limited( $item_id ) ) {
				return;
			}

			if ( 'view_duration' === $action ) {
				$this->handle_view_duration( $request );
				return;
			}

			$payload = $request->get_param( 'payload' );
			$payload = is_scalar( $payload ) && '' !== $payload ? substr( sanitize_text_field( (string) $payload ), 0, 255 ) : null;

			$data = Log::insert( [
				'object_type' => 'item',
				'object_id' => $item_id,
				'action' =>  $action,
				'data' => $payload
			] );

		} catch ( \ChurchPlugins\Exception $e ) {
			$data = [
				'id'    => $item_id,
				'error' => $e->getMessage(),
			];

			error_log( $e->getMessage() );
		}

		return $data;
	}

	/**
	 * Whole-number item id from the log route, or 0 when the value is not one.
	 *
	 * @param mixed $raw Route parameter.
	 * @return int
	 */
	public function log_item_id( $raw ) {
		if ( is_int( $raw ) ) {
			return absint( $raw );
		}

		if ( is_string( $raw ) && preg_match( '/^[0-9]+$/', $raw ) ) {
			return absint( $raw );
		}

		return 0;
	}

	/**
	 * Actions the player sends to the log route.
	 *
	 * Only `view_duration` includes a structured payload. The others are sent
	 * with no payload. `cpl_log_actions` can add an action a site already sends.
	 *
	 * @return array
	 */
	public function get_log_actions() {
		$actions = [
			'play',
			'persistent',
			'fullscreen',
			'download',
			'share_facebook',
			'share_twitter',
			'video_widget_play',
			'audio_widget_play',
			'video_view',
			'audio_view',
			'engaged_video_view',
			'engaged_audio_view',
			'view_duration',
		];

		return apply_filters( 'cpl_log_actions', $actions );
	}

	/**
	 * Address used for the rate limit.
	 *
	 * Always `REMOTE_ADDR`, checked with `FILTER_VALIDATE_IP`.
	 *
	 * @return string|false
	 */
	public function get_log_ip() {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
		$ip     = filter_var( $remote, FILTER_VALIDATE_IP );

		return $ip ? $ip : false;
	}

	/**
	 * Address stored on a view-duration row.
	 *
	 * The first comma-separated X-Forwarded-For entry is used when it is an
	 * IP. Otherwise `REMOTE_ADDR` is used, when that is an IP. The rate limit
	 * does not use this value.
	 *
	 * Filter `cpl_log_viewer_ip` to replace the address.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return string|false
	 */
	public function get_log_viewer_ip( $request ) {
		$header = $request->get_header( 'x-forwarded-for' );
		$ip     = false;

		if ( is_string( $header ) && '' !== $header ) {
			$first = trim( explode( ',', $header )[0] );
			$ip    = filter_var( $first, FILTER_VALIDATE_IP );
		}

		if ( ! $ip ) {
			$ip = $this->get_log_ip();
		}

		$ip = apply_filters( 'cpl_log_viewer_ip', $ip, $request );

		return ( is_string( $ip ) && filter_var( $ip, FILTER_VALIDATE_IP ) ) ? $ip : false;
	}

	/**
	 * Whether this address has already logged this item too many times.
	 *
	 * The player does not poll. A view total is sent when the sermon changes
	 * or the page unloads, and the other actions fire once per play. The
	 * default of 2000 writes per minute for one address and one item stays
	 * above that, including where many visitors share `REMOTE_ADDR`.
	 *
	 * Filter `cpl_log_rate_limit` to change the per-minute maximum.
	 *
	 * @param int $item_id Item id.
	 * @return bool True when the write should be skipped.
	 */
	public function is_log_rate_limited( $item_id ) {
		if ( ! $ip = $this->get_log_ip() ) {
			return true;
		}

		$limit = absint( apply_filters( 'cpl_log_rate_limit', 2000, $item_id ) );
		$key   = 'cpl_log_' . md5( $ip . '|' . $item_id );
		$count = absint( get_transient( $key ) );

		if ( $count >= $limit ) {
			return true;
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );

		return false;
	}

	/**
	 * Checks if a given request has access to read and manage the user's passwords.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool True if the request has read access for the item, otherwise false.
	 */
	public function get_permissions_check( $request ) {
		return true;
	}

	/**
	 * Checks if a given request has access to read and manage the user's passwords.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool True if the request has read access for the item, otherwise false.
	 */
	public function create_permissions_check( $request ) {
		return is_user_logged_in();
	}

	/**
	 * Get an associative array of item topics (terms) by first letter
	 *
	 * @return Array
	 * @author costmo
	 */
	public function get_topic_dictionary() {

		$return_value = [
			'count'		=> 0,
			'items'		=> []
		];

		$args = [
			'orderby'		=> 'name',
			'orderby'		=> 'asc',
			'hide_empty'	=> true
		];
		$terms = get_terms( [ 'talk_categories' ], $args );
		if( !empty( $terms ) && is_array( $terms ) ) {

			$return_value['count'] = count( $terms );

			foreach( $terms as $term ) {
				if( !empty( $term ) || is_object( $term ) && !empty( $term->term_id ) ) {

					$first_leter = substr( strtolower( trim( $term->name ) ), 0, 1 );

					if( !array_key_exists( $first_leter, $return_value['items'] ) ) {
						$return_value['items'][ $first_leter ] = [];
					}
					// Return as a normalized array
					$return_value['items'][ $first_leter ][] = [
						'id' 		=> $term->term_id,
						'name' 		=> $term->name,
						'slug' 		=> $term->slug,
						'count' 	=> $term->count
					];
				}
			}
		}

		return $return_value;
	}

	/**
	 * Get search results from WP's search REST endpoint
	 *
	 * TODO: Figure out how to overrid the 100 post maximum for this endpoint
	 *
	 * @param String $find				The string/terms to find
	 * @return array
	 * @author costmo
	 */
	public function get_search_results( $find ) {

//		$find = rawurlencode( rawurldecode( $find ) );
		$return_value = [];

		$request  = new WP_REST_Request( 'GET', "/wp/v2/search", [] );

		$request->set_param( 'search', $find );
		$request->set_param( 'subtype', $this->post_type );
		$request->set_param( 'per_page', 100 );

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		if( !empty( $data ) ) {
			foreach( $data as $item ) {
				$return_value[] = $item['id'];
			}
		}

		return $return_value;
	}

	/**
	 * Retrieves a list of items
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return array|WP_Error Array on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {

		$return_value = [];
		$taxonomies = [];
		$format_filter_ids = [];

		$args = [
			'post_type'      => $this->post_type,
			'post_status'    => 'publish',
			'posts_per_page' => 10,
		];

		$search_filter_ids = [];
		$filter_search = false;

		// If there are search terms, parse them first
		if( !empty( $request->get_param( 's' ) ) ) {
			$search_filter_ids = $this->get_search_results( $request->get_param( 's' ) );
			$filter_search = true;

			// If the user searched and nothing matches, return "empty" instead of "all"
			if( empty( $search_filter_ids ) ) {
				return $return_value;
			}
		}

		if( !empty( $request->get_param( 't' ) ) ) {
			$topic_string = preg_replace( "/\,$/", "", trim( $request->get_param( 't' ) ) );
			$taxonomies = explode( ",", $topic_string );
		}

		if( !empty( $request->get_param( 'f' ) ) ) {
			$format_string = preg_replace( "/\,$/", "", trim( $request->get_param( 'f' ) ) );
			$format_string = preg_replace( "/format\_\_/", "", $format_string );
			$formats = explode( ",", $format_string );

			if( !empty( $formats ) && !in_array( 'format__all', $formats ) && count( $formats ) == 1 ) {

				$format     = str_replace( 'filter__', '', $formats[0] );
				$sql        = '';
				$table      = ItemModel::get_prop( 'table_name' );
				$meta_table = ItemModel::get_prop( 'meta_table_name' );

				global $wpdb;
				if( $format == 'audio' ) {
					$sql = $wpdb->prepare(
						"
						SELECT		origin_id
						FROM 		$table, $meta_table
						WHERE		$meta_table.`key` IN ( %s ) AND
									$table.`id` = $meta_table.item_id",
						'audio_url'
					);
				} else {
					$sql = $wpdb->prepare(
						"
						SELECT		origin_id
						FROM 		$table, $meta_table
						WHERE		$meta_table.`key` IN ( %s, %s, %s ) AND
									$table.`id` = $meta_table.item_id",
						'video_id_vimeo', 'video_id_facebook', 'video_url'
					);
				}

				$result = $wpdb->get_results( $sql );
				foreach( $result as $row ) {
					$format_filter_ids[] = $row->origin_id;
				}
			}
		}

		if( !empty( $request->get_param( 'count' ) ) ) {
			$args['posts_per_page'] = absint( $request->get_param( 'count' ) );
		}

		if( !empty( $taxonomies ) ) {
			$args['tax_query'] =
			[
				array (
					'taxonomy' => 'talk_categories',
					'field' => 'slug',
					'terms' => $taxonomies,
				)
				];
		}

		// If the user has typed-in search parameters...
		if( $filter_search ) {
			// ...and there are filtrs applied
			if( !empty( $format_filter_ids ) ) {
				$args['post__in'] = array_intersect(  $format_filter_ids, $search_filter_ids  );
			} else { // ...no filters are applied
				$args['post__in'] = $search_filter_ids;
			}
		} else if( !empty( $format_filter_ids ) ) { // No user-supplied search
			$args['post__in'] = $format_filter_ids;
		}

		if ( $type_id = $request->get_param( 'type' ) ) {
			try {
				$type = ItemType::get_instance( $type_id );
				$items = $type->get_items();
				$post__in = [];

				foreach( $items as $item ) {
					$post__in[] = $item->origin_id;
				}

				if ( empty( $args['post__in'] ) ) {
					$args['post__in'] = $post__in;
				} else {
					$args['post__in'] = array_intersect( $args['post__in'], $post__in );
				}
			} catch( Exception $e ) {
				error_log( $e );
			}
		}

		if( $request->get_param( 'hideUpcoming' ) === 'true' ) {
			$args['cpl_hide_upcoming'] = true;
		}

		if ( !empty( $request->get_param( 'cpl_service_types' ) ) ) {
			$args['cpl_service_types'] = $request->get_param( 'cpl_service_types' );
		}

		// $posts = get_posts( $args );
		if( $page = $request->get_param( 'p' ) ) {
			$args['paged'] = absint( $page );
		}
		$args = apply_filters( 'cpl_api_get_items_args', $args, $request );
		$posts = new \WP_Query( $args );
		$return_value = [
			'count' => $posts->post_count,
			'total' => $posts->found_posts,
			'pages' => $posts->max_num_pages,
			'items' => [],
		];

		if( empty( $posts->post_count ) ) {
			return $return_value;
		}

		foreach( $posts->posts as $post ) {

			try {
				$item = new Item( $post->ID );

				// Check if include_variations parameter is set
				$include_variations = $request->get_param( 'include_variations' ) === 'true';
				$data = $item->get_api_data( $include_variations );

				$return_value['items'][] = $data;
			} catch ( Exception $e ) {
				$return_value['error'] = $e->getMessage();
				error_log( $e->getMessage() );
			}

		}

		return $return_value;
	}

	/**
	 * Retrieves the passwords.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return array|WP_Error Array on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$item_id = $request->get_param( 'item_id' );
		try {

			if ( ! is_numeric( $item_id ) ) {
				if ( ! $item = get_page_by_path( $item_id, OBJECT, $this->post_type ) ) {
					throw new Exception( 'Could not find the requested item' );
				}

				$item_id = $item->ID;
			}

			$item = new Item( $item_id );

			// Check if include_variations parameter is set
			$include_variations = $request->get_param( 'include_variations' ) === 'true';
			$data = $item->get_api_data( $include_variations );
		} catch ( Exception $e ) {
			$data = [
				'id' => $item_id,
				'error' => $e->getMessage(),
			];

			error_log( $e->getMessage() );
		}

		return $data;
	}

	/**
	 * Expose protected namesapce property
	 *
	 * @return string
	 * @author costmo
	 */
	public function get_namespace() {
		return $this->namespace;
	}

	/**
	 * Expose protected rest_base property
	 *
	 * @return string
	 * @author costmo
	 */
	public function get_rest_base() {
		return $this->rest_base;
	}

	/**
	 * Registers the custom query parameters for the items collection.
	 */
	public function register_custom_query_parameters() {
		add_filter( "rest_{$this->post_type}_collection_params", array( $this, 'custom_collection_params' ), 10, 2 );
		add_filter( "rest_{$this->post_type}_query", array( $this, 'rest_query_args' ), 10, 2 );
	}

	public function custom_collection_params( $params, $post_type ) {
		$params['cpl_hide_upcoming'] = array(
			'type'        => 'boolean',
			'description' => __( 'Whether to hide upcoming items', 'cp-library' ),
			'default'     => false,
		);

		$params['cpl_speakers'] = array(
			'type'        => 'array',
			'description' => __( 'Filter by speaker', 'cp-library' ),
			'default'     => array(),
		);

		$params['cpl_service_types'] = array(
			'type'        => 'array',
			'description' => __( 'Filter by service type', 'cp-library' ),
			'default'     => array(),
		);

		$params['cpl_item_type'] = array(
			'type'        => 'array',
			'description' => __( 'Filter by item type', 'cp-library' ),
			'default'     => array(),
		);

		return $params;
	}

	public function rest_query_args( $args, $request ) {
		if ( isset( $_GET['cpl_hide_upcoming'] ) && $_GET['cpl_hide_upcoming'] === 'true' ) {
			$args['cpl_hide_upcoming'] = true;
		}

		if ( isset( $_GET['cpl_speakers'] ) && is_array( $_GET['cpl_speakers'] ) ) {
			$args['cpl_speakers'] = (array) $_GET['cpl_speakers'];
		}

		if ( isset( $_GET['cpl_service_types'] ) && is_array( $_GET['cpl_service_types'] ) ) {
			$args['cpl_service_types'] = (array) $_GET['cpl_service_types'];
		}

		if ( isset( $_GET['cpl_item_type'] ) && is_array( $_GET['cpl_item_type'] ) ) {
			$args['cpl_item_type'] = (array) $_GET['cpl_item_type'];
		}

		return $args;
	}



	public function handle_view_duration( WP_REST_Request $request ) {

		$action  = $request->get_param( 'action' );
		$payload = $request->get_param( 'payload' );
		$item_id = $this->log_item_id( $request->get_param( 'item_id' ) );
		$user_ip = $this->get_log_viewer_ip( $request );

		if ( ! $item_id || ! $user_ip ) {
			return;
		}

		if( ! (
			is_array( $payload ) &&
			isset( $payload['watchedSeconds'] ) &&
			isset( $payload['maxDuration'] ) &&
			is_int( $payload['watchedSeconds'] ) &&
			is_int( $payload['maxDuration'] )
			) ) {
			throw new Exception( "Invalid payload", 400 );
		}

		$watched_seconds = absint( $payload['watchedSeconds'] );
		$max_duration    = absint( $payload['maxDuration'] );
		$watched_seconds = min( $watched_seconds, $max_duration );

		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}cp_log WHERE object_id = %d AND action = %s AND JSON_UNQUOTE(JSON_EXTRACT(data, '$.user_ip')) = %s LIMIT 1",
			$item_id,
			'view_duration',
			$user_ip
		);

		$data = $wpdb->get_row( $query );

		if( ! $data ) {
			Log::insert( [
				'object_type' => 'item',
				'object_id' => $item_id,
				'action' =>  $action,
				'data' => json_encode(array(
					'user_ip' => $user_ip,
					'watch_duration' => $watched_seconds
				))
			] );
			return;
		}

		$log = Log::get_instance( $data->id );

		$data     = json_decode( $data->data );
		$previous = ( is_object( $data ) && isset( $data->watch_duration ) ) ? $data->watch_duration : 0;

		$total_watch_duration = absint( $previous ) + $watched_seconds;
		$total_watch_duration = min( $total_watch_duration, $max_duration );

		$log->update([
			'data' => json_encode(array(
				'user_ip' => $user_ip,
				'watch_duration' => $total_watch_duration
			))
		]);

		return;
	}
}
