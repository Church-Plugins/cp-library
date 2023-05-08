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

		parent::__construct();
	}

	/**
	 * Override action-adder for CPT-descendants of this class
	 *
	 * @return void
	 * @author costmo
	 */
	public function add_actions() {

		_C::log( "Add Actions" );

		add_action( 'add_meta_boxes', [ $this, 'register_metaboxes' ] );
		// add_filter( 'cmb2_override_meta_save', [ $this, 'meta_save_override' ], 10, 4 );
		// add_filter( 'cmb2_override_meta_remove', [ $this, 'meta_save_override' ], 10, 4 );
		// add_filter( 'cmb2_override_meta_value', [ $this, 'meta_get_override' ], 10, 4 );

		add_action( 'cp_register_taxonomies', [ $this, 'register_taxonomy' ] );
		// add_filter( 'cp_app_vars', [ $this, 'app_vars' ] );
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

		// only register if we have object types
		if ( empty( $this->get_object_types() ) ) {
			return;
		}

		$terms = $this->get_terms_for_metabox();
		\add_meta_box(
			'cpl_scripture_metabox',
			$this->single_label,
			[ $this, 'metabox_callback' ],
			'cpl_item',
			'normal',
			'default'
		);
	}

	/**
	 * Admin Metabox for Scripture management
	 *
	 * @param WP_Post $post
	 * @return void
	 * @author costmo
	 */
	public function metabox_callback( $post ) {

		wp_nonce_field( 'cpl-admin', 'cpl_admin_nonce_field' );

		// Get our static list of possible Book/Chapter/Verse values
		$scriptures = _C::arrayify_json( $this->get_term_data() );
		$selected_scriptures = wp_get_object_terms( $post->ID, 'cpl_scripture' );
		$selected_scripture_names = []; // For easier lookup

		$selected_options_html = '';
		foreach ( $selected_scriptures as $selected_term ) {
			$selected_options_html .= '
				<span class="cpl-scripture-tag" data-id="' . esc_attr( $selected_term->term_id ) . '">' . esc_html( $selected_term->name ) . '</span>
			';
			$selected_scripture_names[] = $selected_term->name;
		}

		$book_list_html = '<ul id="cpl-book-list">';
		foreach( $scriptures as $book => $book_details ) {

			$selected_class = '';
			if( in_array( $book, $selected_scripture_names ) ) {
				$selected_class = 'cpl-selected';
			}
			$book_list_html .= '<li class="cpl-scripture-book ' . $selected_class . '" data-name="' . $book . '"> ' . $book . ' </li>';
		}
		$book_list_html .= '</ul>';

		_C::log( "Selected" );
		_C::log( $selected_scriptures );
		_C::log( $selected_scripture_names );

		$return_value = '
		<div id="cpl-scripture-input" class="widefat">
			' . $selected_options_html . '
		</div>
		<div id="cpl-scripture-list" class="cpl-list-closed">
			' . $book_list_html . '
		</div>
		<input type="hidden" name="cpl_scriptures" id="cpl-scriptures" value="" />
		<script>
			// Add available scriptures to JavaScript
			var availableScriptures = ' . json_encode( $scriptures ) . ';
		</script>
		';

		echo $return_value;
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
