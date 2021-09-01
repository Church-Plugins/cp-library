<?php

namespace CP_Library\Models;

/**
 * SourceMeta DB Class
 *
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SourceMeta Class
 *
 * @since 1.0.0
 */
class SourceMeta extends Table  {

	/**
	 * Get things started
	 *
	 * @since  1.0
	*/
	public function __construct() {
		$this->type        = 'source_meta';

		parent::__construct();
	}

	/**
	 * Get columns and formats
	 *
	 * @since   1.0
	 */
	public function get_columns() {
		return array(
			'id'             => '%d',
			'key'            => '%s',
			'value'          => '%s',
			'source_id'      => '%d',
			'source_type_id' => '%d',
			'item_id'        => '%d',
			'order'          => '%d',
			'published'      => '%s',
			'updated'        => '%s',
		);
	}

	/**
	 * Get default column values
	 *
	 * @since   1.0
	 */
	public function get_column_defaults() {
		return array(
			'id'             => 0,
			'key'            => '',
			'value'          => '',
			'source_id'      => 0,
			'source_type_id' => 0,
			'item_id'        => 0,
			'order'          => 0,
			'published'      => date( 'Y-m-d H:i:s' ),
			'updated'        => date( 'Y-m-d H:i:s' ),
		);
	}

}
