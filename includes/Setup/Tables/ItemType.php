<?php

namespace CP_Library\Setup\Tables;

use ChurchPlugins\Setup\Tables\Table;

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
	 * @since  1.0.0
	*/
	public function __construct() {
		parent::__construct();

		$this->table_name = $this->prefix . 'cpl_item_type';
		$this->version    = 1;
	}

	/**
	 * Create the table
	 *
	 * @since   1.0.0
	*/
	public function get_sql() {

		return "CREATE TABLE " . $this->table_name . " (
			`id` bigint NOT NULL AUTO_INCREMENT,
			`origin_id` bigint,
			`title` varchar(255),
			`parent_id` bigint,
			`published` datetime NOT NULL,
			`updated` datetime NOT NULL,
			PRIMARY KEY  (`id`),
			KEY `idx_title` (`title`),
			KEY `idx_parent_id` (`parent_id`)
		) CHARACTER SET utf8 COLLATE utf8_general_ci;";

	}

	public function maybe_update() {
		global $wpdb;

		// don't update if title column already exists
		$column = $wpdb->get_results( "SHOW COLUMNS FROM " . $this->table_name . " LIKE 'title';" );
		if ( ! empty( $column ) ) {
			return;
		}

		$sql = "ALTER TABLE " . $this->table_name . " ADD COLUMN title varchar(255) AFTER origin_id;";

		$wpdb->query( $sql );
		$this->updated_table();
	}

}
