<?php
namespace CP_Library\Setup\Taxonomies;

use CP_Library\Templates;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Setup for custom taxonomy: Season
 *
 * @author tanner moushey
 * @since 1.0
 */
class Season extends Taxonomy  {

	/**
	 * Child class constructor. Punts to the parent.
	 *
	 * @author costmo
	 */
	protected function __construct() {
		$this->taxonomy = CP_LIBRARY_UPREFIX . "_season";

		$this->single_label = apply_filters( "{$this->taxonomy}_single_label", 'Season' );
		$this->plural_label = apply_filters( "{$this->taxonomy}_plural_label", 'Seasons' );

		parent::__construct();
	}

	/**
	 * Return the object types for this taxonomy
	 *
	 * @return array
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_object_types() {
		$types = [ cp_library()->setup->post_types->item->post_type ];

		if ( cp_library()->setup->post_types->item_type_enabled() ) {
			$types[] = cp_library()->setup->post_types->item_type->post_type;
		}

		return $types;
	}

	/**
	 * A key value array of term data "esc_attr( Name )" : "Name"
	 * @return array
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_terms() {
		$data = $this->get_term_data();

		if ( empty( $data ) ) {
			return [];
		}

		$terms = wp_list_pluck( $data, 'term' );
		return array_combine( array_map( 'esc_attr', $terms ), $terms );
	}

	/**
	 * Get term data from json file
	 *
	 * @return array
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_term_data() {
		$file = Templates::get_template_hierarchy( '__data/seasons.json' );

		if ( ! $file ) {
			return [];
		}

		return apply_filters( "{$this->taxonomy}_get_term_data", json_decode( file_get_contents( $file ) ) );
	}

}
