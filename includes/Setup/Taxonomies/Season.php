<?php
namespace CP_Library\Setup\Taxonomies;

use CP_Library\Admin\Settings;
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

		add_action( $this->taxonomy . '_pre_add_form', [ $this, 'builtin_terms' ] );
	}

	/**
	 * Show notice for built-in terms
	 *
	 * @since  1.3.0
	 *
	 * @author Tanner Moushey, 12/6/23
	 */
	public function builtin_terms() {
		if ( ! Settings::get_advanced( 'season_terms_enabled', 1 ) ) {
			return;
		}
		add_thickbox();
		?>
		<h3><?php _e( 'Built-in Seasons', 'cp-library' ); ?></h3>
		<p><?php _e( 'Before adding a new Season, please make sure that one does not already exist in the <a href="#TB_inline?width=600&height=550&inlineId=modal-seasons" class="thickbox">built-in list of Seasons</a>. When a built-in Season is used, it will show in the Term table.'); ?></p>
		<div id="modal-seasons" style="display:none;">
			<h3><?php _e( 'Built-in Seasons', 'cp-library' ); ?></h3>
			<p><?php echo implode( ', ', wp_list_pluck( cp_library()->setup->taxonomies->season->get_term_data(), 'term' ) ); ?></p>
		</div>
		<?php
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

		return apply_filters( 'cpl_season_object_types', $types, $this );
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

		if ( ! $file || ! Settings::get_advanced( 'season_terms_enabled', 1 ) ) {
			return [];
		}

		return apply_filters( "{$this->taxonomy}_get_term_data", json_decode( file_get_contents( $file ) ) );
	}

}
