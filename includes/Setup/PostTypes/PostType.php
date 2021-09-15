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
	 * An array of metabox definitions
	 *
	 * @var array
	 * @author costmo
	 */
	public $metaboxes = null;

	/**
	 * Convenience variable for children to set the post typer
	 *
	 * @var string
	 * @author costmo
	 */
	public $post_type = null;

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

	/**
	 * Registers a custom post type using child-configured args
	 *
	 * @return void
	 * @author costmo
	 */
	public function register_post_type() {

		if( empty( $this->cpt_args ) || !is_array( $this->cpt_args ) ) {
			throw new Exception( "No configuration present for this CPT" );
			return;
		}

		register_post_type( CP_LIBRARY_UPREFIX . "_" . $this->cpt_args['labels']['name'], $this->cpt_args );
	}

	/**
	 * Add metaboxes action from children with metaboxes to register
	 *
	 * @return void
	 * @author costmo
	 */
	public function add_metaboxes()
	{
		add_action( 'add_meta_boxes', [ $this, 'register_metaboxes' ] );
	}

	/**
	 * Register metaboxes for Item admin
	 *
	 * Children should provide their own metaboxes
	 *
	 * @return void
	 * @author costmo
	 */
	public function register_metaboxes() {

		return;
	}

	/**
	 * Default action-adder for this CPT-descendants of this class
	 *
	 * @return void
	 * @author costmo
	 */
	public function add_actions() {
		return;
	}

}