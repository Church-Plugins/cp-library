<?php

namespace CP_Library\Models;

/**
 * ItemMeta DB Class
 *
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ItemMeta Class
 *
 * @since 1.0.0
 */
class ItemMeta extends Table  {

	/**
	 * Get things started
	 *
	 * @since  1.0
	*/
	public function __construct() {
		$this->type        = 'item_meta';

		parent::__construct();
	}

	/**
	 * Get columns and formats
	 *
	 * @since   1.0
	*/
	public function get_columns() {
		return array(
			'id'           => '%d',
			'key'          => '%s',
			'value'        => '%s',
			'item_id'      => '%d',
			'item_type_id' => '%d',
			'order'        => '%d',
			'published'    => '%s',
			'updated'      => '%s',
		);
	}

	/**
	 * Get default column values
	 *
	 * @since   1.0
	*/
	public function get_column_defaults() {
		return array(
			'id'           => 0,
			'key'          => '',
			'value'        => '',
			'item_id'      => 0,
			'item_type_id' => 0,
			'order'        => 0,
			'published'    => date( 'Y-m-d H:i:s' ),
			'updated'      => date( 'Y-m-d H:i:s' ),
		);
	}

}
