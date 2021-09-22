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
	protected static $_instance = [];

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
	public static function get_instance() {
		$class = get_called_class();

		if ( ! isset( self::$_instance[ $class ] ) || ! self::$_instance[ $class ] instanceof $class ) {
			self::$_instance[ $class ] = new $class();
		}

		return self::$_instance[ $class ];
	}

	/**
	 * Get things started
	 *
	 * @since   1.0
	 */
	protected function __construct() {
		$this->add_actions();
	}

	/**
	 * Registers a custom post type using child-configured args
	 *
	 * @return void
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

	public function get_args() {
		return [];
	}

	/**
	 * Default action-adder for this CPT-descendants of this class
	 *
	 * @return void
	 * @author costmo
	 */
	public function add_actions() {
		add_action( 'cmb2_admin_init', [ $this, 'register_metaboxes' ] );
		add_action( 'init', [ $this, 'register_post_type' ] );

		return;
	}

}
