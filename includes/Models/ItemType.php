<?php

namespace CP_Library\Models;

/**
 * ItemType DB Class
 *
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ItemType Class
 *
 * @since 1.0.0
 */
class ItemType extends Table  {

	/**
	 * Get things started
	 *
	 * @since  1.0
	*/
	public function __construct() {
		$this->type        = 'item_type';

		parent::__construct();
	}

	/**
	 * Get columns and formats
	 *
	 * @since   1.0
	*/
	public function get_columns() {
		return array(
			'id'        => '%d',
			'title'     => '%s',
			'parent_id' => '%d',
			'published' => '%s',
			'updated'   => '%s',
		);
	}

	/**
	 * Get default column values
	 *
	 * @since   1.0
	*/
	public function get_column_defaults() {
		return array(
			'id'        => 0,
			'title'     => '',
			'parent_id' => 0,
			'published' => date( 'Y-m-d H:i:s' ),
			'updated'   => date( 'Y-m-d H:i:s' ),
		);
	}

}
