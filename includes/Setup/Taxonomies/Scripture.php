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
	 * @since 1.0.0
	 * @author costmo
	 */
	public function __construct() {
		$this->taxonomy = CP_LIBRARY_UPREFIX . "_scripture";

		$this->single_label = apply_filters( "{$this->taxonomy}_single_label", 'Scripture' );
		$this->plural_label = apply_filters( "{$this->taxonomy}_plural_label", 'Scripture' );

		parent::__construct();
	}

	/**
	 * Override action-adder for CPT-descendants
	 *
	 * @since 1.0.5
	 * @return void
	 * @author costmo
	 */
	public function add_actions() {

		add_action( 'add_meta_boxes', [ $this, 'register_metaboxes' ] );
		add_action( 'save_post_cpl_item', [ $this, 'save_metabox_input' ] );
		add_action( 'cp_register_taxonomies', [ $this, 'register_taxonomy' ] );
	}

	/**
	 * Register metaboxes for admin
	 *
	 * Children should provide their own metaboxes
	 *
	 * @since 1.0.0
	 * @return void
	 * @author costmo
	 */
	public function register_metaboxes() {

		// only register if we have object types
		if ( empty( $this->get_object_types() ) ) {
			return;
		}

		// Custom input UX instead of cmb2
		\add_meta_box(
			'cpl_scripture_metabox',
			$this->single_label,
			[ $this, 'render_metabox' ],
			'cpl_item',
			'normal',
			'default'
		);
	}

	/**
	 * Admin Metabox for Scripture management
	 *
	 * @since 1.0.5
	 * @param WP_Post $post
	 * @return void
	 * @author costmo
	 */
	public function render_metabox( $post ) {

		wp_nonce_field( 'cpl-admin', 'cpl_admin_nonce_field' );

		// Get our static list of possible Book/Chapter/Verse values
		$scriptures = _C::arrayify_json( $this->get_term_data() );
		$selected_scriptures = wp_get_object_terms( $post->ID, 'cpl_scripture' );
		$selected_scripture_names = []; // For easier lookup

		$selected_options_html = '';
		foreach ( $selected_scriptures as $selected_term ) {
			$selected_options_html .= '
				<span class="cpl-scripture-tag" data-id="' . esc_attr( $selected_term->term_id ) . '">' .
					esc_html( $selected_term->name ) .
					'<input type="hidden" name="cpl-scripture-tag-selections[]" data-id="' . esc_attr( $selected_term->term_id ) . '" data-name="' . esc_html( $selected_term->name ) . '" value="' . esc_html( $selected_term->name ) . '">' .
				'</span>
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

		$return_value = '
		<div id="cpl-scripture-input" class="widefat">
			' . $selected_options_html . '
		</div>
		<div id="cpl-scripture-list" class="cpl-list-closed">
			' . $book_list_html . '
		</div>
		<div id="cpl-scripture-list-chapter" class="cpl-list-closed">
			<div class="cpl-scripture-progress-display"></div>
			<div class="cpl-scripture-progress-content"></div>
			<div class="cpl-scripture-finish-progress"></div>
		</div>
		<div id="cpl-scripture-list-verse" class="cpl-list-closed">
			<div class="cpl-scripture-progress-display"></div>
			<div class="cpl-scripture-progress-content"></div>
			<div class="cpl-scripture-finish-progress"></div>
		</div>
		<input type="hidden" name="cpl_scripture_current_selection" id="cpl-scripture-current-selection" data-value="" />
		<input type="hidden" name="cpl_scripture_selection_level" id="cpl-scripture-selection-level" data-value="" />
		<input type="hidden" name="cpl_scripture_current_selection_book" id="cpl-scripture-current-selection-book" data-value="" />
		<script>
			// Add available scriptures to JavaScript
			var availableScriptures = ' . json_encode( $scriptures ) . ';
		</script>
		';

		echo $return_value;
	}

	/**
	 * Saves input from the wp-admin input for Scripture taxonomy
	 *
	 * @return void
	 * @since  1.0.5
	 *
	 * @param int $post_id
	 * @author costmo
	 */
	public function save_metabox_input( $post_id ) {

		// Basic input and security checks
		if( ! isset( $_POST['cpl_admin_nonce_field'] ) || ! wp_verify_nonce( $_POST['cpl_admin_nonce_field'], 'cpl-admin' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Sanity check the input
		if( empty( $_POST ) || ! is_array( $_POST ) ) {
			return;
		}

		// Term input was empty, so make sure the terms are cleared for this post and return
		if( empty( $_POST['cpl-scripture-tag-selections'] ) || ! is_array( $_POST['cpl-scripture-tag-selections'] ) ) {
			wp_delete_object_term_relationships( $post_id, [ 'cpl_scripture' ] );
			return;
		}

		// There are terms to save...
		$save_term_ids = [];
		$incoming_terms = $_POST['cpl-scripture-tag-selections'];
		foreach( $incoming_terms as $term ) {
			// Check if the term exists in the 'cpl_scripture' taxonomy.
			$existing_term = get_term_by( 'name', $term, 'cpl_scripture' );

			// If the term exists, use its ID.
			if ( $existing_term ) {
				$save_term_ids[] = $existing_term->term_id;
			} else {
				// If the term does not exist, insert it and use the new ID.
				$new_term = wp_insert_term( $term, 'cpl_scripture' );
				if ( ! is_wp_error( $new_term ) ) {
					$save_term_ids[] = $new_term['term_id'];
				}
			}
		}
		wp_set_object_terms( $post_id, $save_term_ids, 'cpl_scripture', false );
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
