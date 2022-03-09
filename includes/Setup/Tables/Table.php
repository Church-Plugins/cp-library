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
class Table {

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
	 * Current table version
	 *
	 * @return int
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_version() {
		return 1;
	}

	/**
	 * Get tables check option
	 *
	 * @return false|mixed|void
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_tables_check() {
		return get_option( 'cpl_tables_check', [] );
	}

	/**
	 * Update the version of this table
	 *
	 * @param $version int
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function updated_table( $version = 0 ) {
		if ( ! $version ) {
			$version = $this->version;
		}

		$tables = $this->get_tables_check();
		$tables[ $this->table_name ] = $version;

		update_option( 'cpl_tables_check', $tables );
	}

	/**
	 * Whether or not we need to run an update routine on this table
	 *
	 * @return false
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function needs_update() {
		$check = $this->get_tables_check();

		if ( empty( $check[ $this->table_name ] ) ) {
			return true;
		}

		return $check[ $this->table_name ] != $this->version;
	}

	/**
	 * update table
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function maybe_update() {}

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
		throw new Exception( "get_sql() can't come from the parent." );
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
