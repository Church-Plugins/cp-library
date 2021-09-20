<?php

namespace CP_Library\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class used to manage a user's API Passwords via the REST API.
 *
 * @since 4.7.0
 *
 * @see WP_REST_Controller
 */
class Items extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 4.7.0
	 * @access public
	 */
	public function __construct() {
		$this->namespace = cp_library()->get_api_namespace();
		$this->rest_base = 'items';
	}

	/**
	 * Registers the routes for the objects of the controller.
	 *
	 * @since 4.7.0
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
			'schema' => array( $this, 'get_public_item_schema' ),
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
			'schema' => array( $this, 'get_public_item_schema' ),
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
	 * Retrieves the passwords.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return array|WP_Error Array on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {

		return [
			[
				'thumb'    => 'https://i.vimeocdn.com/video/1239653387?mw=1100&mh=618&q=70',
				'title'    => 'For Love or Money',
				'desc'     => 'A brief description for this talk.',
				'date'     => date( 'r', time() - rand( 100, 23988 ) ),
				'category' => [ 'cat 1', 'cat 2' ],
				'video'    => 'https://vimeo.com/embed-redirect/603403673?embedded=true&source=vimeo_logo&owner=11698061',
				'audio'    => 'https://ret.sfo2.cdn.digitaloceanspaces.com/wp-content/uploads/2021/09/re20210915.mp3',
			],
			[
				'thumb'    => 'https://i.vimeocdn.com/video/1239653387?mw=1100&mh=618&q=70',
				'title'    => 'Out of Love',
				'desc'     => 'A different description for this talk.',
				'date'     => date( 'r', time() - rand( 100, 23988 ) ),
				'category' => [ 'cat 1', 'cat 2' ],
				'video'    => 'https://vimeo.com/embed-redirect/603403673?embedded=true&source=vimeo_logo&owner=11698061',
				'audio'    => 'https://ret.sfo2.cdn.digitaloceanspaces.com/wp-content/uploads/2021/09/re20210915.mp3',
			],
		];

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
}
