<?php

namespace CP_Library\Setup\Tables;

/**
 * Source DB Class
 *
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Source Class
 *
 * @since 1.0.0
 */
class Source extends Table  {

	/**
	 * Get things started
	 *
	 * @since  1.0.0
	*/
	public function __construct() {
		global $wpdb;

		$this->table_name = $wpdb->prefix . 'cpl_source';
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
			origin_id bigint(20),
			status ENUM( 'draft', 'publish', 'scheduled' ),
			published datetime NOT NULL,
			updated datetime NOT NULL,
			PRIMARY KEY  (id),
			Unique Key origin_id (origin_id),
			Key status (status),
		) CHARACTER SET utf8 COLLATE utf8_general_ci;";


	}

}
