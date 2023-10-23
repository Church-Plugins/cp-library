<?php
namespace CP_Library\Setup\Taxonomies;

use CP_Library\Templates;
use ChurchPlugins\Setup\Taxonomies\Taxonomy;

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
	 * Return the args for this taxonomy
	 *
	 * @since  1.3.0
	 *
	 * @return array
	 * @author Tanner Moushey, 10/21/23
	 */
	public function get_args() {
		$args =  parent::get_args();

		$args['show_ui'] = true;
		$args['meta_box_cb'] = false;

		return $args;
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
		$terms = get_terms( [ 'taxonomy' => $this->taxonomy, 'hide_empty' => false ] );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			$terms = [];
		}

		$terms = wp_list_pluck( $terms, 'name' );

		$data  = $this->get_term_data();

		if ( ! empty( $data ) ) {
			$topic_terms = wp_list_pluck( $data, 'term' );

			foreach ( $topic_terms as $term ) {
				if ( ! array_search( $term, $terms ) ) {
					$terms[] = $term;
				}
			}
		}

		asort( $terms );
		$terms = array_combine( array_map( 'esc_attr', $terms ), $terms );

		return apply_filters( 'cpl_season_get_terms', $terms, $data );
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
