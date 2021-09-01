<?php

namespace CP_Library\Setup\Tables;

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
	 * @since  1.0.0
	*/
	public function __construct() {
		global $wpdb;

		$this->table_name = $wpdb->prefix . 'cpl_item_meta';
		$this->version    = '1.0';

		parent::__construct();
	}

	/**
	 * Keys for key column
	 *
	 * @return mixed|void
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	protected function get_keys() {
		return apply_filters( 'cpl_item_meta_keys_enum', [ 'avatar', 'name', 'url' ] );
	}

	/**
	 * Create the table
	 *
	 * @since   1.0.0
	*/
	public function get_sql() {

		$keys = "'" . implode( "', '", $this->get_keys() ) . "'";

		return "CREATE TABLE " . $this->table_name . " (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			key ENUM( $keys ),
			value longtext,
			item_id bigint(20),
			item_type_id bigint(20),
			order bigint(20),
			published datetime NOT NULL,
			updated datetime NOT NULL,
			PRIMARY KEY  (id),
			Key item_id (item_id),
			Key item_type_id (item_type_id),
		) CHARACTER SET utf8 COLLATE utf8_general_ci;";


	}

}
