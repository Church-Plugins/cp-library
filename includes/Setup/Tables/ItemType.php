<?php

namespace CP_Library\Setup\Tables;

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
		global $wpdb;

		$this->table_name = $wpdb->prefix . 'cpl_item_type';
		$this->version    = '1.0';

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
			`title` varchar(255) NOT NULL,
			`parent_id` bigint,
			`published` datetime NOT NULL,
			`updated` datetime NOT NULL,
			PRIMARY KEY  (`id`),
			KEY `idx_title` (`title`),
			KEY `idx_parent_id` (`parent_id`)
		) CHARACTER SET utf8 COLLATE utf8_general_ci;";

	}

}
