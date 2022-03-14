<?php

namespace CP_Library\Setup\Tables;

use ChurchPlugins\Setup\Tables\Table;

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
 * @since 1.0
 */
class Item extends Table  {
	/**
	 * Get things started
	 *
	 * @since  1.0.0
	*/
	public function __construct() {
		global $wpdb;

		$this->table_name = $wpdb->prefix . 'cpl_item';
		$this->version    = 1;

		parent::__construct();
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
			`status` ENUM( 'draft', 'publish', 'scheduled' ),
			`published` datetime NOT NULL,
			`updated` datetime NOT NULL,
			PRIMARY KEY  (`id`),
			KEY `idx_origin_id` (`origin_id`),
			KEY `idx_status` (`status`)
		) CHARACTER SET utf8 COLLATE utf8_general_ci;";

	}

	public function maybe_update() {
		global $wpdb;

		$sql = "ALTER TABLE " . $this->table_name . " ADD COLUMN title varchar(255) AFTER origin_id;";

		$wpdb->query( $sql );
		$this->updated_table();
	}

}

