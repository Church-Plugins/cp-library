<?php

namespace CP_Library\API;

use CP_Library\Controllers\Item;
use CP_Library\Exception;
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
		$this->post_type	=  CP_LIBRARY_UPREFIX . "_items";
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

		register_rest_route( $this->namespace, $this->rest_base . '/(?P<item_id>[\d]+)', array(
			'args' => array(
				'item_id' => array(
					'description' => __( 'The ID of the item.', 'cp-library' ),
					'type'        => 'integer',
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

		register_rest_route( $this->namespace, $this->rest_base . '/dictionary', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_topic_dictionary' ),
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

	public function get_search_results( $find ) {

		$find = rawurlencode( $find );
		$return_value = [];

		$address = get_site_url() . '/wp-json/wp/v2/search?search=' . $find . '&subtype=cpl_items&per_page=100&';
		$request = wp_remote_get( $address, ['sslverify' => false] );
		if( is_wp_error( $request ) ) {
			return [];
		}

		$result = wp_remote_retrieve_body( $request );
		$data = @json_decode( $result );
		if( !empty( $data ) ) {
			foreach( $data as $item ) {
				$return_value[] = $item->id;
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

				$format = str_replace( 'filter__', '', $formats[0] );
				$sql = '';
				global $wpdb;
				if( $format == 'audio' ) {
					$sql = $wpdb->prepare(
						"
						SELECT		origin_id
						FROM 		" . $wpdb->prefix . "cpl_item, " . $wpdb->prefix . "cpl_item_meta
						WHERE		wp_cpl_item_meta.`key` IN ( %s ) AND
									wp_cpl_item.`id` = wp_cpl_item_meta.item_id",
						'audio_url'
					);
				} else {
					$sql = $wpdb->prepare(
						"
						SELECT		origin_id
						FROM 		" . $wpdb->prefix . "cpl_item, " . $wpdb->prefix . "cpl_item_meta
						WHERE		wp_cpl_item_meta.`key` IN ( %s, %s ) AND
									wp_cpl_item.`id` = wp_cpl_item_meta.item_id",
						'video_id_vimeo', 'video_id_facebook'
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

				$data = [
					'id'        => $item->post->ID,
					'cplItemID' => $item->model->id,
					'permalink' => $item->get_permalink(),
					'thumb'    => $item->get_thumbnail(),
					'title'    => htmlspecialchars_decode( $item->get_title(), ENT_QUOTES | ENT_HTML401 ),
					'desc'     => $item->get_content(),
					'date'     => $item->get_publish_date(),
					'category' => $item->get_categories(),
					'video'    => $item->get_video(),
					'audio'    => $item->get_audio(),
				];

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
			$item = new Item( $item_id );

			$data = [
				'id'        => $item->post->ID,
				'cplItemID' => $item->model->id,
				'permalink' => $item->get_permalink(),
				'thumb'     => $item->get_thumbnail(),
				'title'     => htmlspecialchars_decode( $item->get_title(), ENT_QUOTES | ENT_HTML401 ),
				'desc'      => $item->get_content(),
				'date'      => $item->get_publish_date(),
				'category'  => $item->get_categories(),
				'video'     => $item->get_video(),
				'audio'     => $item->get_audio(),
			];

			$return_value['items'][] = $data;
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
}
