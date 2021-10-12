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
	 * Retrieves a list of items
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return array|WP_Error Array on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {

		$return_value = [];
		$taxonomies = [];

		$args = [
			'post_type'      => $this->post_type,
			'post_status'    => 'publish',
			'posts_per_page' => 10,
		];

		if( !empty( $request->get_param( 'topic' ) ) ) {
			$topic_string = preg_replace( "/\,$/", "", trim( $request->get_param( 'topic' ) ) );
			$taxonomies = explode( ",", $request->get_param( 'topic' ) );
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

		// $posts = get_posts( $args );
		if ( $page = $request->get_param( 'page' ) ) {
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
					'id'       => $item->model->id,
					'postID'   => $item->post->ID,
					'permalink' => $item->get_permalink(),
					'thumb'    => $item->get_thumbnail(),
					'title'    => $item->get_title(),
					'desc'     => $item->get_content(),
					'date'     => $item->get_publish_date(),
					'category' => $item->get_categories(),
					'video'    => $item->get_video(),
					'audio'    => $item->get_audio(),
				];

				$return_value['items'][] = $data;
			} catch ( Exception $e ) {
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
		return [
			'thumb'    => 'https://i.vimeocdn.com/video/1239653387?mw=1100&mh=618&q=70',
			'title'    => 'Out of Love',
			'desc'     => 'A different description for this talk.',
			'date'     => date( 'r', time() - rand( 100, 23988 ) ),
			'category' => [ 'cat 1', 'cat 2' ],
			'video'    => 'https://vimeo.com/embed-redirect/603403673?embedded=true&source=vimeo_logo&owner=11698061',
			'audio'    => 'https://ret.sfo2.cdn.digitaloceanspaces.com/wp-content/uploads/2021/09/re20210915.mp3',
		];
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
