<?php

namespace CP_Library\API;

use CP_Library\Controllers\ServiceType;
use CP_Library\Exception;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST API Controller for ServiceType objects
 *
 * @since 1.6.0
 *
 * @see WP_REST_Controller
 */
class ServiceTypes extends WP_REST_Controller {

	public $post_type;

	/**
	 * Constructor.
	 *
	 * @since 1.6.0
	 * @access public
	 */
	public function __construct() {
		$this->namespace = cp_library()->get_api_namespace();
		$this->rest_base = 'service-types';
		$this->post_type	=  CP_LIBRARY_UPREFIX . "_service_type";
	}

	/**
	 * Registers the routes for the objects of the controller.
	 *
	 * @since 1.6.0
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
			// 'schema' => array( $this, 'get_public_item_schema' ),
		) );

		register_rest_route( $this->namespace, $this->rest_base . '/(?P<type_id>[\d]+)', array(
			'args' => array(
				'type_id' => array(
					'description' => __( 'The ID of the service type.', 'cp-library' ),
					'type'        => 'integer',
					'required'    => true,
				),
			),
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_permissions_check' ),
			),
			// 'schema' => array( $this, 'get_public_item_schema' ),
		) );

	}

	/**
	 * Checks if a given request has access to read service types
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool True if the request has read access for the item, otherwise false.
	 */
	public function get_permissions_check( $request ) {
		return true;
	}

	/**
	 * Retrieves a list of service types
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return array|WP_Error Array on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {

		$return_value = [];

		$args = [
			'post_type'      => $this->post_type,
			'post_status'    => 'publish',
			'posts_per_page' => 10,
		];

		if( !empty( $request->get_param( 'count' ) ) ) {
			$args['posts_per_page'] = absint( $request->get_param( 'count' ) );
		}

		// Get page parameter if present
		if( $page = $request->get_param( 'p' ) ) {
			$args['paged'] = absint( $page );
		}

		$args = apply_filters( 'cpl_api_get_service_types_args', $args, $request );
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
				$service_type = new ServiceType( $post->ID );
				$data = $service_type->get_api_data();
				$return_value['items'][] = $data;
			} catch ( Exception $e ) {
				$return_value['error'] = $e->getMessage();
				error_log( $e->getMessage() );
			}
		}

		return $return_value;
	}

	/**
	 * Retrieves a single service type.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return array|WP_Error Array on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$type_id = $request->get_param( 'type_id' );
		try {
			$service_type = new ServiceType( $type_id );
			$data = $service_type->get_api_data();
		} catch ( Exception $e ) {
			$data = [
				'id' => $type_id,
				'error' => $e->getMessage(),
			];
			error_log( $e->getMessage() );
		}

		return $data;
	}

	/**
	 * Expose protected namespace property
	 *
	 * @return string
	 */
	public function get_namespace() {
		return $this->namespace;
	}

	/**
	 * Expose protected rest_base property
	 *
	 * @return string
	 */
	public function get_rest_base() {
		return $this->rest_base;
	}
}