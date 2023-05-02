<?php
namespace CP_Library\Setup\Taxonomies;

use CP_Library\Templates;
use ChurchPlugins\Setup\Taxonomies\Taxonomy;

use CP_Library\Util\Convenience as _C;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Setup for custom taxonomy: Scripture
 *
 * @author tanner moushey
 * @since 1.0
 */
class Scripture extends Taxonomy  {

	/**
	 * Child class constructor. Punts to the parent.
	 *
	 * @author costmo
	 */
	public function __construct() {
		$this->taxonomy = CP_LIBRARY_UPREFIX . "_scripture";

		$this->single_label = apply_filters( "{$this->taxonomy}_single_label", 'Scripture' );
		$this->plural_label = apply_filters( "{$this->taxonomy}_plural_label", 'Scripture' );

		add_action( 'cmb2_after_init', [$this, 'bind_metabox_script'] );

		parent::__construct();
	}

	function bind_metabox_script( $cmb ) {

		_C::log( "CMB" );
		_C::log($cmb );

		// Check if we're on the right metabox
		if ( $cmb->cmb_id !== 'your_metabox_id' ) {
			return;
		}

		// // Enqueue your JavaScript file
		// wp_enqueue_script( 'my-script', 'path/to/my/script.js', array( 'jquery', 'select2' ) );

		// // Localize the script with the current terms
		// wp_localize_script( 'my-script', 'myScriptData', array(
		// 	'terms' => $cmb->prop( 'options' ),
		// ) );
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

		$terms = [];

		foreach ( $data as $book => $details ) {
			$terms[ esc_attr( $book ) ] = $book;
		}

		return $terms;
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
		$file = Templates::get_template_hierarchy( '__data/scripture_detailed_20230427_124224.json' );

		if ( ! $file ) {
			return [];
		}

		return apply_filters( "{$this->taxonomy}_get_term_data", json_decode( file_get_contents( $file ) ) );
	}

}
