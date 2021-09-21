<?php

namespace CP_Library\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST API Controller for Sources objects
 *
 * @since 1.0.0
 *
 * @see WP_REST_Controller
 */
class Sources extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function __construct() {
		$this->namespace = cp_library()->get_api_namespace();
		$this->rest_base = 'sources';
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
				'callback'            => array( $this, 'get_sources' ),
				'permission_callback' => array( $this, 'get_permissions_check' ),
			),
			// 'schema' => array( $this, 'get_public_schema' ),
		) );

		register_rest_route( $this->namespace, $this->rest_base . '/(?P<source_id>[\d]+)', array(
			'args' => array(
				'source_id' => array(
					'description' => __( 'The ID of the source.', 'cp-library' ),
					'type'        => 'integer',
					'required'    => true,
				),
			),
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_source' ),
				'permission_callback' => array( $this, 'get_permissions_check' ),
			),

			// 'schema' => array( $this, 'get_public_schema' ),
		) );

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
	public function get_sources( $request ) {

		return [
			[
				'thumb'    => 'https://i.vimeocdn.com/video/1239653387?mw=1100&mh=618&q=70',
				'title'    => 'SOURCE For Love or Money',
				'desc'     => 'SOURCE A brief description for this talk.',
				'date'     => date( 'r', time() - rand( 100, 23988 ) ),
				'category' => [ 'cat 1', 'cat 2' ],
				'video'    => 'https://vimeo.com/embed-redirect/603403673?embedded=true&source=vimeo_logo&owner=11698061',
				'audio'    => 'https://ret.sfo2.cdn.digitaloceanspaces.com/wp-content/uploads/2021/09/re20210915.mp3',
			],
			[
				'thumb'    => 'https://i.vimeocdn.com/video/1239653387?mw=1100&mh=618&q=70',
				'title'    => 'SOURCE Out of Love',
				'desc'     => 'SOURCE A different description for this talk.',
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
	public function get_source( $request ) {
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
