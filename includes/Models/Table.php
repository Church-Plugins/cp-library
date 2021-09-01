<?php

namespace CP_Library\Models;

use CP_Library\Exception;

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
	 * The name of the primary column
	 *
	 * @since   1.0
	 */
	public $primary_key;

	/**
	 * Unique string to identify this data type
	 *
	 * @var string
	 */
	public $type;

	/**
	 * ID of the cache group to use
	 *
	 * @var string
	 */
	public $cache_group;

	/**
	 * Get things started
	 *
	 * @since   1.0
	 */
	public function __construct() {
		global $wpdb;

		$this->cache_group = $this->type;
		$this->table_name  = $wpdb->prefix . CPL_APP_PREFIX . '_' . $this->type;
		$this->primary_key = 'id';
	}

	/**
	 * Whitelist of columns
	 *
	 * @since   1.0
	 * @return  array
	 */
	public function get_columns() {
		return array();
	}

	/**
	 * Default column values
	 *
	 * @since   1.0
	 * @return  array
	 */
	public function get_column_defaults() {
		return array();
	}

	/**
	 * Retrieve a row by the primary key
	 *
	 * @param $row_id
	 *
	 * @since   1.0
	 * @return  object
	 */
	public function get( $row_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->table_name WHERE $this->primary_key = %s LIMIT 1;", $row_id ) );
	}

	/**
	 * Retrieve a row by a specific column / value
	 *
	 * @param $column
	 * @param $row_id
	 *
	 * @since   1.0
	 * @return  object
	 */
	public function get_by( $column, $row_id ) {
		global $wpdb;
		$column = esc_sql( $column );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->table_name WHERE $column = %s LIMIT 1;", $row_id ) );
	}

	/**
	 * Retrieve a specific column's value by the primary key
	 *
	 * @param $column
	 * @param $row_id
	 *
	 * @since   1.0
	 * @return  string
	 */
	public function get_column( $column, $row_id ) {
		global $wpdb;
		$column = esc_sql( $column );
		return $wpdb->get_var( $wpdb->prepare( "SELECT $column FROM $this->table_name WHERE $this->primary_key = %s LIMIT 1;", $row_id ) );
	}

	/**
	 * Retrieve a specific column's value by the the specified column / value
	 *
	 * @param $column
	 * @param $column_where
	 * @param $column_value
	 *
	 * @since   1.0
	 * @return  string
	 */
	public function get_column_by( $column, $column_where, $column_value ) {
		global $wpdb;
		$column_where = esc_sql( $column_where );
		$column       = esc_sql( $column );
		return $wpdb->get_var( $wpdb->prepare( "SELECT $column FROM $this->table_name WHERE $column_where = %s LIMIT 1;", $column_value ) );
	}

	/**
	 * Insert a new row
	 *
	 * @param        $data
	 *
	 *
	 * @return int
	 * @throws Exception
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function insert( $data ) {
		global $wpdb;

		$data = apply_filters( 'cpl_pre_insert', $data, $this );

		// Set default values
		$data = wp_parse_args( $data, $this->get_column_defaults() );

		// Initialise column format array
		$column_formats = $this->get_columns();

		// Force fields to lower case
		$data = array_change_key_case( $data );

		// White list columns
		$data = array_intersect_key( $data, $column_formats );

		// Reorder $column_formats to match the order of columns given in $data
		$data_keys = array_keys( $data );
		$column_formats = array_merge( array_flip( $data_keys ), $column_formats );

		$wpdb->insert( $this->table_name, $data, $column_formats );

		if ( ! $wpdb_insert_id = $wpdb->insert_id ) {
			throw new Exception( 'Could not insert data.' );
		}

		$this->set_last_changed();

		do_action( 'cpl_post_insert', $wpdb_insert_id, $data, $this );

		return $wpdb_insert_id;
	}

	/**
	 * @param        $row_id
	 * @param array  $data
	 * @param string $where
	 *
	 *
	 * @return bool
	 * @throws Exception
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function update( $row_id, $data = array(), $where = '' ) {

		global $wpdb;

		$data = apply_filters( 'cpl_pre_update', $data, $this );

		// Row ID must be positive integer
		$row_id = absint( $row_id );

		if ( empty( $row_id ) ) {
			throw new Exception( 'No row id provided.' );
		}

		if ( empty( $where ) ) {
			$where = $this->primary_key;
		}

		// Initialise column format array
		$column_formats = $this->get_columns();

		// Force fields to lower case
		$data = array_change_key_case( $data );

		// White list columns
		$data = array_intersect_key( $data, $column_formats );

		// Reorder $column_formats to match the order of columns given in $data
		$data_keys = array_keys( $data );
		$column_formats = array_merge( array_flip( $data_keys ), $column_formats );

		if ( false === $wpdb->update( $this->table_name, $data, array( $where => $row_id ), $column_formats ) ) {
			throw new Exception( sprintf( 'The row (%d) was not updated.', absint( $row_id ) ) );
		}

		$this->set_last_changed();

		do_action( 'cpl_post_update', $data, $this );

		return true;
	}

	/**
	 * @param int $row_id
	 *
	 * @return bool
	 * @throws Exception
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function delete( $row_id = 0 ) {

		global $wpdb;

		// Row ID must be positive integer
		$row_id = absint( $row_id );

		if( empty( $row_id ) ) {
			throw new Exception( 'No row id provided.' );
		}

		if ( false === $wpdb->query( $wpdb->prepare( "DELETE FROM $this->table_name WHERE $this->primary_key = %d", $row_id ) ) ) {
			throw new Exception( sprintf( 'The row (%d) was not deleted.', absint( $row_id ) ) );
		}

		return true;
	}

	/**
	 * Sets the last_changed cache key for customers.
	 *
	 * @since  1.0
	 */
	public function set_last_changed() {
		wp_cache_set( 'last_changed', microtime(), $this->cache_group );
	}

	/**
	 * Retrieves the value of the last_changed cache key for customers.
	 *
	 * @since  1.0.0
	 */
	public function get_last_changed() {
		if ( function_exists( 'wp_cache_get_last_changed' ) ) {
			return wp_cache_get_last_changed( $this->cache_group );
		}

		$last_changed = wp_cache_get( 'last_changed', $this->cache_group );
		if ( ! $last_changed ) {
			$last_changed = microtime();
			wp_cache_set( 'last_changed', $last_changed, $this->cache_group );
		}

		return $last_changed;
	}

}
