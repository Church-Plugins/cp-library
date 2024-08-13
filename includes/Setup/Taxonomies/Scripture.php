<?php
namespace CP_Library\Setup\Taxonomies;

use ChurchPlugins\Exception;
use CP_Library\Models\Item;
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
	 * @since 1.1.0
	 * @return void
	 * @author costmo
	 */
	public function add_actions() {

		add_action( 'add_meta_boxes', [ $this, 'register_metaboxes' ] );
		add_action( 'save_post_cpl_item', [ $this, 'save_metabox_input' ] );
		add_action( 'save_post_cpl_item_type', [ $this, 'save_metabox_input' ] );
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
			$this->get_object_types(),
			'normal',
			'default'
		);
	}

	/**
	 * Admin Metabox for Scripture management
	 *
	 * @since 1.1.0
	 * @param WP_Post $post
	 * @return void
	 * @author costmo
	 */
	public function render_metabox( $post ) {

		wp_nonce_field( 'cpl-admin', 'cpl_scripture_nonce_field' );

		// Get our static list of possible Book/Chapter/Verse values
		$scriptures = _C::arrayify_json( $this->get_term_data() );
		$selected_scriptures = $this->get_object_passages( $post->ID );

		$selected_options_html = '';
		foreach ( $selected_scriptures as $selected_term ) {
			$selected_options_html .= '
				<span class="cpl-scripture-tag" data-id="0">' .
					esc_html( $selected_term ) .
					'<input type="hidden" name="cpl-scripture-tag-selections[]" data-id="0" data-name="' . esc_html( $selected_term ) . '" value="' . esc_html( $selected_term ) . '">' .
				'</span>
			';
		}

		$book_list_html = '<ul id="cpl-book-list">';
		foreach( $scriptures as $book => $book_details ) {
			$book_list_html .= '<li class="cpl-scripture-book" data-name="' . $book . '"> ' . $book . ' </li>';
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
	 * @since  1.1.0
	 *
	 * @param int $post_id
	 * @author costmo
	 */
	public function save_metabox_input( $post_id ) {
		// Basic input and security checks
		if( ! isset( $_POST['cpl_scripture_nonce_field'] ) || ! wp_verify_nonce( $_POST['cpl_scripture_nonce_field'], 'cpl-admin' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Sanity check the input
		if( empty( $_POST ) || ! is_array( $_POST ) ) {
			return;
		}

		$scriptures = array();

		if ( isset( $_POST['cpl-scripture-tag-selections'] ) ) {
			$scriptures = $_POST['cpl-scripture-tag-selections'];
		}

		$this->update_object_scripture( $post_id, $scriptures );
	}

	/**
	 * Get the passage meta for the provided object
	 *
	 * @since  1.1.0
	 *
	 * @param $object_id
	 *
	 * @return mixed
	 * @author Tanner Moushey, 5/25/23
	 */
	public function get_object_passages( $object_id ) {
		if ( ! $passages = get_post_meta( $object_id, '_cp_scripture', true ) ) {
			$passages = [];

			// fall back to terms if no meta is found
			if ( $scriptures = $this->get_object_scripture( $object_id ) ) {
				foreach ( $scriptures as $scripture ) {
					$passages[] = $scripture->name;
				}
			}
		}

		return apply_filters( 'cp_library_get_object_passages', $passages, $object_id );
	}

	/**
	 * Get the scripture terms for the provided object
	 *
	 * @since  1.1.0
	 *
	 * @param $object_id
	 *
	 * @return array|\WP_Error
	 * @author Tanner Moushey, 5/25/23
	 */
	public function get_object_scripture( $object_id ) {
		return wp_get_post_terms( $object_id, $this->taxonomy );
	}

	/**
	 * Update the scripture passages associated with the provided object
	 *
	 * @since  1.1.0
	 *
	 * @param $object_id
	 * @param $passages
	 *
	 * @return array|bool|int|int[]|string|string[]|void|\WP_Error|null
	 * @author Tanner Moushey, 5/25/23
	 */
	public function update_object_scripture( $object_id, $passages ) {

		if ( empty( $passages ) ) {
			delete_post_meta( $object_id, '_cp_scripture' );
			wp_delete_object_term_relationships( $object_id, [ $this->taxonomy ] );
			return true;
		}

		if ( ! is_array( $passages ) ) {
			$passages = [ $passages ];
		}

		$save_term_ids = [];

		$passages = array_map( 'sanitize_text_field', $passages );

		foreach( $passages as $key => $passage ) {
			if ( ! $book = $this->get_book( $passage ) ) {
				unset( $passages[ $key ] );
				continue;
			}

			$existing_term = get_term_by( 'name', $book, $this->taxonomy );

			// If the term exists, use its ID.
			if ( $existing_term ) {
				$save_term_ids[] = $existing_term->term_id;
			} else {
				// If the term does not exist, insert it and use the new ID.
				$new_term = wp_insert_term( $book, $this->taxonomy );
				if ( ! is_wp_error( $new_term ) ) {
					$save_term_ids[] = $new_term['term_id'];
				}
			}
		}

		update_post_meta( $object_id, '_cp_scripture', $passages );
		return wp_set_object_terms( $object_id, $save_term_ids, $this->taxonomy, false );
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

	/**
	 * Retrieve the book associated with the passage
	 *
	 * @since  1.1.0
	 *
	 * @param $passage
	 *
	 * @return false|int|string
	 * @author Tanner Moushey, 5/25/23
	 */
	public function get_book( $passage ) {
		$passage = trim( $passage );
		$books = array_keys( get_object_vars( $this->get_term_data() ) );

		foreach( $books as $book ) {
			if ( 0 === strpos( strtolower( $passage ), strtolower( $book ) ) ) {
				return $book;
			}
		}

		return false;
	}

}
