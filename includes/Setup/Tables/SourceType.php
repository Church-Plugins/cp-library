<?php

namespace CP_Library\Setup\Tables;

/**
 * SourceType DB Class
 *
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SourceType Class
 *
 * @since 1.0.0
 */
class SourceType extends Table  {

	/**
	 * Get things started
	 *
	 * @since  1.0.0
	*/
	public function __construct() {
		global $wpdb;

		$this->table_name = $wpdb->prefix . 'cpl_source_type';
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
			id bigint(20) NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			parent_id bigint(20),
			published datetime NOT NULL,
			updated datetime NOT NULL,
			PRIMARY KEY  (id),
			Key title (title),
		) CHARACTER SET utf8 COLLATE utf8_general_ci;";

	}

}
