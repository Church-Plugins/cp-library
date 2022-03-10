<?php
namespace CP_Library\Setup\Taxonomies;

use CP_Library\Admin\Settings;
use CP_Library\Exception;
use CP_Library\Models\ItemType as Model;
use CP_Library\Models\Item as ItemModel;
use CP_Library\Models\Speaker as Speaker_Model;
use \CP_Library\Controllers\Item as ItemController;
use CP_Library\Templates;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Setup for custom taxonomy: Topic
 *
 * @author tanner moushey
 * @since 1.0
 */
class Topic extends Taxonomy  {

	/**
	 * Child class constructor. Punts to the parent.
	 *
	 * @author costmo
	 */
	protected function __construct() {
		$this->taxonomy = CP_LIBRARY_UPREFIX . "_topic";

		$this->single_label = apply_filters( "{$this->taxonomy}_single_label", 'Topic' );
		$this->plural_label = apply_filters( "{$this->taxonomy}_plural_label", 'Topics' );

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
		return [ cp_library()->setup->post_types->item->post_type ];
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

		$topic_terms = wp_list_pluck( $data, 'term' );
		return array_combine( array_map( 'esc_attr', $topic_terms ), $topic_terms );
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
		$topics_file = Templates::get_template_hierarchy( '__data/topics.json' );

		if ( ! $topics_file ) {
			return [];
		}

		return apply_filters( "{$this->taxonomy}_get_term_data", json_decode( file_get_contents( $topics_file ) ) );
	}

}
