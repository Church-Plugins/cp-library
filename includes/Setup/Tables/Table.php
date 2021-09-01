<?php

namespace CP_Library\Setup\Tables;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Table base class
 *
 * @author Tanner Moushey
 * @since  1.0
*/
abstract class Table {

	/**
	 * The name of our database table
	 *
	 * @since   1.0
	 */
	public $table_name;

	/**
	 * The version of our database table
	 *
	 * @since   1.0
	 */
	public $version;

	/**
	 * The name of the primary column
	 *
	 * @since   1.0
	 */
	public $primary_key;

	/**
	 * @var self
	 */
	protected static $_instance;

	/**
	 * Only make one instance of Table
	 *
	 * @return self
	 */
	public static function get_instance() {
		$class = get_called_class();

		if ( ! self::$_instance instanceof $class ) {
			self::$_instance = new $class();
		}

		return self::$_instance;
	}

	/**
	 * Get things started
	 *
	 * @since   1.0
	 */
	public function __construct() {}

	/**
	 * Check if the given table exists
	 *
	 * @since  1.0
	 * @param  string $table The table name
	 * @return bool          If the table name exists
	 */
	public function table_exists( $table ) {
		global $wpdb;
		$table = sanitize_text_field( $table );

		return $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE '%s'", $table ) ) === $table;
	}

	/**
	 * Check if the table was ever installed
	 *
	 * @since  1.0
	 * @return bool Returns if the customers table was installed and upgrade routine run
	 */
	public function installed() {
		return $this->table_exists( $this->table_name );
	}

	/**
	 * SQL string to create the Table
	 *
	 * @return string
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_sql() {
		return '';
	}

	/**
	 * Create the table
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function create_table() {
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$sql = apply_filters( 'cpl_create_table_sql', $this->get_sql(), $this );

		dbDelta( $sql );

		update_option( $this->table_name . '_db_version', $this->version );
	}

}
