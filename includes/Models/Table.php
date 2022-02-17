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
	public static $table_name;

	/**
	 * The name of the meta database table
	 *
	 * @var string
	 */
	public static $meta_table_name;

	/**
	 * The name of the primary column
	 *
	 * @since   1.0
	 */
	public static $primary_key;

	/**
	 * Unique string to identify this data type
	 *
	 * @var string
	 */
	public static $type;

	/**
	 * The post type associated with this object
	 *
	 * @var
	 */
	public static $post_type;

	/**
	 * ID of the cache group to use
	 *
	 * @var string
	 */
	public static $cache_group;

	/**
	 * ID of the current post
	 *
	 * @var
	 */
	public $id = null;
	public $origin_id = null;

	public static function init() {
		global $wpdb;

		static::$cache_group = static::$type;
		static::$table_name  = $wpdb->prefix . CP_LIBRARY_UPREFIX . '_' . static::$type;
		static::$meta_table_name  = $wpdb->prefix . CP_LIBRARY_UPREFIX . '_' . static::$type . "_meta";
		static::$primary_key = 'id';
	}

	/**
	 * Setup instance using an origin id
	 * @param $origin_id
	 *
	 * @return bool | static
	 * @throws Exception
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public static function get_instance_from_origin( $origin_id ) {
		global $wpdb;

		static::init();

		$origin_id = absint( $origin_id );
		if ( ! $origin_id ) {
			return false;
		}

		if ( ! get_post( $origin_id ) ) {
			throw new Exception( 'That post does not exist.' );
		}

		if ( static::$post_type !== get_post_type( $origin_id ) ) {
			throw new Exception( 'The post type for the provided ID is not correct.' );
		}

		$object = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . static::$table_name . " WHERE origin_id = %s LIMIT 1;", $origin_id ) );

		if ( ! $object ) {
			$data = [ 'origin_id' => $origin_id, 'status' => get_post_status( $origin_id ) ];
			return static::insert( $data );
		}

		$class = get_called_class();
		return new $class( $object );
	}

	/**
	 * Setup instance using the primary id
	 * @param $id
	 *
	 * @return bool | static
	 * @throws Exception
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public static function get_instance( $id ) {
		global $wpdb;

		static::init();

		$id = absint( $id );
		if ( ! $id ) {
			return false;
		}

		$object = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . static::$table_name . " WHERE id = %s LIMIT 1;", $id ) );

		if ( ! $object ) {
			throw new Exception( 'Could not find object.' );
		}

		$class = get_called_class();
		return new $class( $object );
	}


	/**
	 * Get things started
	 *
	 * @since   1.0
	 */
	public function __construct( $object ) {
		static::init();

		foreach( get_object_vars( $object ) as $key => $value ) {
			$this->$key = $value;
		}

	}

	/**
	 * Whitelist of columns
	 *
	 * @since   1.0
	 * @return  array
	 */
	public static function get_columns() {
		return array();
	}

	/**
	 * Default column values
	 *
	 * @since   1.0
	 * @return  array
	 */
	public static function get_column_defaults() {
		return array();
	}

	/**
	 * Whitelist of meta columns
	 *
	 * @since   1.0
	 * @return  array
	 */
	public static function get_meta_columns() {
		return array();
	}

	/**
	 * Default meta column values
	 *
	 * @since   1.0
	 * @return  array
	 */
	public function get_meta_column_defaults() {
		return array(
			'item_id' => $this->id,
			'updated' => date( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * Insert a new row
	 *
	 * @param        $data
	 *
	 * @return bool | object
	 * @throws Exception
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public static function insert( $data ) {
		static::init();

		global $wpdb;

		/**
		 * @var static
		 */
		$class = get_called_class();
		$data = apply_filters( 'cpl_pre_insert', $data );

		// Set default values
		$data = wp_parse_args( $data, static::get_column_defaults() );

		// Initialise column format array
		$column_formats = static::get_columns();

		// Force fields to lower case
		$data = array_change_key_case( $data );

		// White list columns
		$data = array_intersect_key( $data, $column_formats );

		// Reorder $column_formats to match the order of columns given in $data
		$data_keys = array_keys( $data );
		$column_formats = array_merge( array_flip( $data_keys ), $column_formats );

		$wpdb->insert( static::$table_name, $data, $column_formats );

		if ( ! $wpdb_insert_id = $wpdb->insert_id ) {
			throw new Exception( 'Could not insert data.' );
		}

		static::set_last_changed();

		do_action( 'cpl_post_insert', $wpdb_insert_id, $data );

		return static::get_instance( $wpdb_insert_id );
	}

	/**
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
	public function update( $data = array() ) {

		global $wpdb;

		if ( empty( $data['updated'] ) ) {
			$data['updated'] = date( 'Y-m-d H:i:s' );
		}

		$data = apply_filters( 'cpl_pre_update', $data, $this );

		// Row ID must be positive integer
		$row_id = absint( $this->id );

		if ( empty( $row_id ) ) {
			throw new Exception( 'No row id provided.' );
		}

		if ( empty( $where ) ) {
			$where = static::$primary_key;
		}

		// Initialise column format array
		$column_formats = static::get_columns();

		// Force fields to lower case
		$data = array_change_key_case( $data );

		// White list columns
		$data = array_intersect_key( $data, $column_formats );

		// Reorder $column_formats to match the order of columns given in $data
		$data_keys = array_keys( $data );
		$column_formats = array_merge( array_flip( $data_keys ), $column_formats );

		if ( false === $wpdb->update( static::$table_name, $data, array( $where => $row_id ), $column_formats ) ) {
			throw new Exception( sprintf( 'The row (%d) was not updated.', absint( $row_id ) ) );
		}

		static::set_last_changed();

		do_action( 'cpl_post_update', $data, $this );

		return true;
	}

	/**
	 * Insert or update new meta
	 *
	 * @param $key
	 * @param $value
	 *
	 * @return false|int
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function update_meta_value( $key, $value ) {
		$data = [ 'key' => $key, 'value' => $value, 'item_id' => $this->id ];
		return $this->update_meta( $data );
	}

	public function update_meta( $data, $unique = true ) {
		global $wpdb;

		$data = apply_filters( 'cpl_pre_update_meta', $data, $this );

		// Initialise column format array
		$column_formats = static::get_meta_columns();

		// Force fields to lower case
		$data = array_change_key_case( $data );

		// White list columns
		$data = array_intersect_key( $data, $column_formats );

		// Reorder $column_formats to match the order of columns given in $data
		$data_keys = array_keys( $data );
		$column_formats = array_merge( array_flip( $data_keys ), $column_formats );

		if ( $this->get_meta_value( $data['key'] ) && $unique ) {
			$result = $wpdb->update( static::$meta_table_name, $data, array(
				'item_id' => $this->id,
				'key'     => $data['key']
			), $column_formats );
		} else {
			// set default values
			$data = wp_parse_args( $data, $this->get_meta_column_defaults() );
			$wpdb->insert( static::$meta_table_name, $data, $column_formats );
			$result = $wpdb->insert_id;
		}

		static::set_last_changed();

		return $result;
	}

	public function get_meta_value( $key ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT `value` FROM " . static::$meta_table_name . " WHERE `key` = %s AND item_id = %d LIMIT 1;", $key, $this->id ) );
	}

	/**
	 * @param $value
	 * @param $field
	 *
	 * @return bool
	 * @throws Exception
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function delete_meta( $value, $field = 'key' ) {
		global $wpdb;

		if ( false === $wpdb->query( $wpdb->prepare( "DELETE FROM " . static::$meta_table_name . " WHERE `item_id` = %d AND `{$field}` = %s", $this->id, $value ) ) ) {
			throw new Exception( sprintf( 'The row (%d) was not deleted.', absint( $this->id ) ) );
		}

		return true;
	}

	/**
	 * @return bool
	 * @throws Exception
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function delete() {

		global $wpdb;

		if ( false === $wpdb->query( $wpdb->prepare( "DELETE FROM " . static::$table_name . " WHERE " . static::$primary_key . " = %d", $this->id ) ) ) {
			throw new Exception( sprintf( 'The row (%d) was not deleted.', absint( $this->id ) ) );
		}

		return true;
	}

	/**
	 * Sets the last_changed cache key for customers.
	 *
	 * @since  1.0
	 */
	public static function set_last_changed() {
		wp_cache_set( 'last_changed', microtime(), static::$cache_group );
	}

	/**
	 * Retrieves the value of the last_changed cache key for customers.
	 *
	 * @since  1.0.0
	 */
	public static function get_last_changed() {
		if ( function_exists( 'wp_cache_get_last_changed' ) ) {
			return wp_cache_get_last_changed( static::$cache_group );
		}

		$last_changed = wp_cache_get( 'last_changed', static::$cache_group );
		if ( ! $last_changed ) {
			$last_changed = microtime();
			wp_cache_set( 'last_changed', $last_changed, static::$cache_group );
		}

		return $last_changed;
	}

}
