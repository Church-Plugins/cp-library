<?php
namespace CP_Library\Setup\PostTypes;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Setup for custom post types
 *
 * @author costmo
 */
abstract class PostType {

	/**
	 * @var self
	 */
	protected static $_instance;

	/**
	 * Input for custom post type registration provided by children
	 *
	 * @var array
	 * @author costmo
	 */
	public $cpt_args = null;

	/**
	 * Only make one instance of PostType
	 *
	 * @return self
	 */
	abstract public static function get_instance();


	/**
	 * Get things started
	 *
	 * @since   1.0
	 */
	public function __construct() {

	}

	public function register() {

		if( empty( $this->cpt_args ) || !is_array( $this->cpt_args ) ) {
			throw new Exception( "No configuration present for this CPT" );
			return;
		}

		register_post_type( $this->cpt_args['labels']['name'], $this->cpt_args );
	}

}