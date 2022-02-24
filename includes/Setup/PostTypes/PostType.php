<?php
namespace CP_Library\Setup\PostTypes;

// Exit if accessed directly
use CP_Library\Exception;

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
	 * Single label for CPT
	 * @var string
	 */
	public $single_label = '';

	/**
	 * Plural label for CPT
	 * @var string
	 */
	public $plural_label = '';

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
	 * Gets the modal for the called class
	 *
	 * @var null
	 */
	public $modal = null;

	/**
	 * Only make one instance of PostType
	 *
	 * @return self
	 */
	public static function get_instance() {
		$class = get_called_class();

		if ( ! self::$_instance instanceof $class ) {
			self::$_instance = new $class();
		}

		return self::$_instance;
	}

	/**
	 * Get things started
	 *
	 * @since   1.0
	 */
	protected function __construct() {
		$this->modal = '\CP_Library\Models\\' . get_class( $this );
	}

	/**
	 * Registers a custom post type using child-configured args
	 *
	 * @return void
	 * @throws Exception
	 * @author costmo
	 */
	public function register_post_type() {
		$cpt_args = $this->get_args();

		if( empty( $cpt_args ) || !is_array( $cpt_args ) ) {
			throw new Exception( "No configuration present for this CPT" );
			return;
		}

		register_post_type( $this->post_type, $cpt_args );
	}

	// This is currently not being fired
	public function rest_request_limit( $params ) {

		if( !empty( $params )  && is_array( $params ) && !empty( $params[ 'per_page' ] ) ) {
			$params[ 'per_page' ][ 'maximum' ] = 9999;
			$params[ 'per_page' ][ 'minimum' ] = -1;
		}
		return $params;

	}

	/**
	 * Register metaboxes for Item admin
	 *
	 * Children should provide their own metaboxes
	 *
	 * @return void
	 * @author costmo
	 */
	abstract public function register_metaboxes();

	abstract public function get_args();

	/**
	 * Default action-adder for this CPT-descendants of this class
	 *
	 * @return void
	 * @author costmo
	 */
	public function add_actions() {
		add_action( 'cmb2_admin_init', [ $this, 'register_metaboxes' ] );
		// add_action( 'rest_cpl_items_params', [ $this, 'rest_request_limit' ], 10, 1 );
		add_action( 'rest_cpl_items_query', [ $this, 'rest_request_limit' ], 10, 1 );
		add_action( "save_post_{$this->post_type}", [ $this, 'save_post' ] );
		add_action( 'cpl_register_post_types', [ $this, 'register_post_type' ] );
		add_filter( 'cpl_app_vars', [ $this, 'app_vars' ] );
		return;
	}

	/**
	 * Add vars for app localization
	 *
	 * @param $vars
	 *
	 * @return mixed
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function app_vars( $vars ) {
		$type = get_post_type_object( $this->post_type );
		$key  = str_replace( CP_LIBRARY_UPREFIX . '_', '', $this->post_type );
		$vars[ $key ] = [
			'labelSingular' => $type->labels->name,
			'labelPlural'   => $type->labels->singular_name,
			'slug'          => $type->rewrite['slug'],
		];

		return $vars;
	}

	/**
	 * Save post to our custom table
	 *
	 * @param $post_id
	 *
	 * @return bool|\CP_Library\Models\Item|\CP_Library\Models\ItemType|\CP_Library\Models\Source
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function save_post( $post_id ) {

		if ( 'auto-draft' == get_post_status( $post_id ) ) {
			return false;
		}

		try {
			// this will save the item to our custom table if it does not already exist
			$model = cp_library()->setup->post_types->get_type_model( $this->post_type, $post_id );
			$model->update( [ 'title' => get_the_title( $post_id ) ] );
		} catch( Exception $e ) {
			error_log( $e->getMessage() );
			return false;
		}

		return $model;
	}

}
