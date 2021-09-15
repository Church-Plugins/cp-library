<?php

namespace CP_Library\Models;

/**
 * Item DB Class
 *
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Item Class
 *
 * @since 1.0.0
 */
class Item extends Table  {

	/**
	 * Get things started
	 *
	 * @since  1.0
	*/
	public function __construct() {
		$this->type        = 'item';

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
			'status'    => '%s',
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
			'status'    => '',
			'published' => date( 'Y-m-d H:i:s' ),
			'updated'   => date( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * Children must override if they want to have their own actions
	 *
	 * @return void
	 * @author costmo
	 */
	public function add_actions() {

		return;
	}

}
