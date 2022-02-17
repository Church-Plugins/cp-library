<?php

namespace CP_Library\Models;

use CP_Library\Exception;

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
 * @since 1.0.0
 */
class Item extends Table  {

	public static function init() {
		self::$type = 'item';
		self::$post_type = 'cpl_items';

		parent::init();
	}

	/**
	 * Get types associated with this item
	 *
	 * @return array|null
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_types() {
		global $wpdb;

		$types = $wpdb->get_col( $wpdb->prepare( "SELECT `item_type_id` FROM " . static::$meta_table_name . " WHERE `key` = 'item_type';" ) );

		return apply_filters( 'cpl_item_get_types', $types, $this );
	}

	/**
	 * Update the types associated with this item
	 *
	 * @param $types array
	 *
	 * @return bool
	 * @throws Exception
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function update_types( $types ) {
		$existing_types = $this->get_types();

		foreach( $types as $type ) {
			if ( $key = array_search( $type, $existing_types ) ) {
				unset( $existing_types[ $key ] );
				continue;
			}

			$data = [
				'key' => 'item_type',
				'item_type_id' => absint( $type ),
			];

			$this->update_meta( $data, false );
		}

		// remove any types which should no longer be attached
		foreach( $existing_types as $type ) {
			$this->delete_meta( absint( $type ), 'item_type_id' );
		}

		return true;
	}

	/**
	 * Get columns and formats
	 *
	 * @since   1.0
	*/
	public static function get_columns() {
		return array(
			'id'        => '%d',
			'origin_id' => '%d',
			'status'    => '%s',
			'published' => '%s',
			'updated'   => '%s',
		);
	}

	/**
	 * Get default column values
	 *
	 * @since   1.0
	*/
	public static function get_column_defaults() {
		return array(
			'origin_id' => 0,
			'status'    => '',
			'published' => date( 'Y-m-d H:i:s' ),
			'updated'   => date( 'Y-m-d H:i:s' ),
		);
	}


	/**
	 * Get meta columns and formats
	 *
	 * @since   1.0
	*/
	public static function get_meta_columns() {
		return array(
			'id'           => '%d',
			'key'          => '%s',
			'value'        => '%s',
			'item_id'      => '%d',
			'item_type_id' => '%d',
			'order'        => '%d',
			'published'    => '%s',
			'updated'      => '%s',
		);
	}

	/**
	 * Get default meta column values
	 *
	 * @since   1.0
	*/
	public function get_meta_column_defaults() {
		return array(
			'key'          => '',
			'value'        => '',
			'item_id'      => $this->id,
			'item_type_id' => 0,
			'order'        => 0,
			'published'    => date( 'Y-m-d H:i:s' ),
			'updated'      => date( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * Get columns and formats
	 *
	 * @since   1.0
	*/
	public static function get_type_columns() {
		return array(
			'id'        => '%d',
			'title'     => '%s',
			'parent_id' => '%d',
			'published' => '%s',
			'updated'   => '%s',
		);
	}

	/**
	 * Get default column values
	 *
	 * @since   1.0
	*/
	public static function get_type_column_defaults() {
		return array(
			'id'        => 0,
			'title'     => '',
			'parent_id' => 0,
			'published' => date( 'Y-m-d H:i:s' ),
			'updated'   => date( 'Y-m-d H:i:s' ),
		);
	}



}
