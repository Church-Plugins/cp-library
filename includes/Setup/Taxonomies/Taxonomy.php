<?php
namespace CP_Library\Setup\Taxonomies;

// Exit if accessed directly
use CP_Library\Exception;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Setup for custom post types
 *
 * @author costmo
 */
abstract class Taxonomy {

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
	public $taxonomy = null;

	/**
	 * Gets the modal for the called class
	 *
	 * @var null
	 */
	public $modal = null;

	/**
	 * Only make one instance of Taxonomy
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
	 * Registers a taxonomy using child-configured args
	 *
	 * @return void
	 * @throws Exception
	 */
	public function register_taxonomy() {
		$args = apply_filters( "{$this->taxonomy}_args", $this->get_args(), $this );

		if( empty( $args ) || !is_array( $args ) ) {
			throw new Exception( "No configuration present for this Taxonomy" );
			return;
		}

		register_taxonomy( $this->taxonomy, $this->get_object_types(), $args );
	}

	/**
	 * Get terms with those selected already at the top
	 *
	 * @return array
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_terms_for_metabox() {
		$terms = $this->get_terms();

		if ( ! empty( $_GET['post'] ) ) {
			$post_id = absint( $_GET['post'] );
			$set_terms = wp_get_post_terms( $post_id, $this->taxonomy, [ 'fields' => 'names' ] );

			foreach( $set_terms as $name ) {
				$index = array_search( $name, $terms );
				$terms = array_merge( [ $index => $name ], $terms );
			}
		}

		return $terms;
	}

	/**
	 * Register metaboxes for admin
	 *
	 * Children should provide their own metaboxes
	 *
	 * @return void
	 * @author costmo
	 */
	public function register_metaboxes() {
		$terms = $this->get_terms_for_metabox();

		$args = apply_filters( "{$this->taxonomy}_metabox_args", [
			'id'           => sprintf( '%s_data', $this->taxonomy ),
			'object_types' => $this->get_object_types(),
			'title'        => $this->plural_label,
			'context'      => 'side',
			'show_names'   => false,
			'priority'     => 'default',
			'closed'       => false,
		] );

		$cmb = new_cmb2_box( $args );

		$cmb->add_field( [
			'name'              => sprintf( __( 'Assign %s', 'cp-library' ), $this->plural_label ),
			'id'                => $this->taxonomy,
			'type'              => 'multicheck',
			'select_all_button' => false,
			'options'           => $terms
		] );
	}

	/**
	 * The object types that this taxonomy is associated with
	 *
	 * @return mixed
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	abstract public function get_object_types();

	/**
	 * Get terms for this taxonomy
	 *
	 * @return mixed
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	abstract public function get_terms();

	/**
	 * Get term data for this taxonomy
	 *
	 * @return mixed
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	abstract public function get_term_data();

	/**
	 * Get arguments for this Taxonomy
	 *
	 * @return array
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_args() {

		$labels = array(
			'name'                       => $this->plural_label,
			'singular_name'              => $this->single_label,
			'search_items'               => sprintf( __( 'Search %s', 'cp-library' ), $this->plural_label ),
			'popular_items'              => sprintf( __( 'Popular %s', 'cp-library' ), $this->plural_label ),
			'all_items'                  => sprintf( __( 'All %s', 'cp-library' ), $this->plural_label ),
			'edit_item'                  => sprintf( __( 'Edit %s', 'cp-library' ), $this->single_label ),
			'update_item'                => sprintf( __( 'Update %s', 'cp-library' ), $this->single_label ),
			'add_new_item'               => sprintf( __( 'Add New %s', 'cp-library' ), $this->single_label ),
			'new_item_name'              => sprintf( __( 'New %s Name', 'cp-library' ), $this->single_label ),
			'separate_items_with_commas' => sprintf( __( 'Separate %s with commas', 'cp-library' ), strtolower( $this->plural_label ) ),
			'add_or_remove_items'        => sprintf( __( 'Add or remove %s', 'cp-library' ), strtolower( $this->plural_label ) ),
			'choose_from_most_used'      => sprintf( __( 'Choose from the most used %s', 'cp-library' ), strtolower( $this->plural_label ) ),
			'not_found'                  => sprintf( __( 'No %s found.', 'cp-library' ), strtolower( $this->plural_label ) ),
			'menu_name'                  => $this->plural_label,
		);

		return array(
			'hierarchical'          => false,
			'labels'                => $labels,
			'show_ui'               => false,
			'show_admin_column'     => false,
			'query_var'             => true,
			'rewrite'               => array( 'slug' => strtolower( $this->plural_label ) ),
		);
	}

	/**
	 * Default action-adder for this CPT-descendants of this class
	 *
	 * @return void
	 * @author costmo
	 */
	public function add_actions() {
		add_action( 'cmb2_admin_init', [ $this, 'register_metaboxes' ] );

		add_filter( 'cmb2_override_meta_save', [ $this, 'meta_save_override' ], 10, 4 );
		add_filter( 'cmb2_override_meta_remove', [ $this, 'meta_save_override' ], 10, 4 );
		add_filter( 'cmb2_override_meta_value', [ $this, 'meta_get_override' ], 10, 4 );

		add_action( 'cpl_register_taxonomies', [ $this, 'register_taxonomy' ] );
		add_filter( 'cpl_app_vars', [ $this, 'app_vars' ] );
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
		$type = get_taxonomy( $this->taxonomy );
		$key  = str_replace( CP_LIBRARY_UPREFIX . '_', '', $this->taxonomy );

		$vars[ $key ] = [
			'labelSingular' => $type->labels->singular_name,
			'labelPlural'   => $type->labels->name,
			'slug'          => $type->rewrite['slug'],
		];

		return $vars;
	}


	/**
	 * Hijack the meta save filter to save to our tables
	 *
	 * Currently will also save to postmeta
	 *
	 * @param $return
	 * @param $data_args
	 * @param $field_args
	 * @param $field
	 *
	 * @return mixed
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function meta_save_override( $return, $data_args, $field_args, $field ) {

		$post_id = $data_args['id'];
		$type = get_post_type( $data_args['id'] );

		// break early if this is not our post type
		if ( ! in_array( $type, $this->get_object_types() ) ) {
			return $return;
		}

		if ( $data_args['field_id'] !== $this->taxonomy ) {
			return $return;
		}

		if ( isset( $data_args['value'] ) ) {
			$result = wp_set_post_terms( $post_id, $data_args['value'], $this->taxonomy );
		} else {
			$result = wp_set_post_terms( $post_id, [], $this->taxonomy );
		}

		// if the update failed, let CMB2 do it's thing
		if ( is_wp_error( $result ) || empty( $result ) ) {
			return $return;
		}

		return true;
	}

	/**
	 * return terms for metabox
	 *
	 * @param $data
	 * @param $object_id
	 * @param $data_args
	 * @param $field
	 *
	 * @return array
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function meta_get_override( $data, $object_id, $data_args, $field ) {

		$type = get_post_type( $object_id );

		// break early if this is not our post type
		if ( ! in_array( $type, $this->get_object_types() ) ) {
			return $data;
		}

		if ( $data_args['field_id'] != $this->taxonomy ) {
			return $data;
		}

		$terms = wp_get_post_terms( $object_id, $this->taxonomy, [ 'fields' => 'names' ] );

		// @todo handle this error better
		if ( is_wp_error( $terms ) ) {
			return $data;
		}

		return $terms;
	}

}
