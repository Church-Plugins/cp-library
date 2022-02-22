<?php

namespace CP_Library\Models;

use CP_Library\Setup\Tables\ItemMeta;
use CP_Library\Setup\Tables\Item;

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
class ItemType extends Table  {

	public static function init() {
		self::$type = 'item_type';
		self::$post_type = 'cpl_item_type';

		parent::init();
	}

	/**
	 * Get all types
	 *
	 * @return array
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public static function get_all_types() {
		global $wpdb;
		self::init();
		$types = $wpdb->get_results( "SELECT * FROM " . self::$table_name );

		if ( ! $types ) {
			$types = [];
		}

		return apply_filters( 'cpl_get_all_types', $types );
	}

	public function get_items() {
		global $wpdb;

		$meta  = ItemMeta::get_instance();
		$item  = Item::get_instance();

		$sql = 'SELECT %1$s.* FROM %1$s
INNER JOIN %2$s
ON %1$s.id = %2$s.item_id
WHERE %2$s.key = "item_type" AND %2$s.item_type_id = %3$d
ORDER BY %2$s.order ASC';

		$items = $wpdb->get_results( $wpdb->prepare( $sql, $item->table_name, $meta->table_name, $this->id ) );

		return apply_filters( 'cpl_item_type_get_items', $items, $this );
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
	public static function get_column_defaults() {
		return array(
			'origin_id' => 0,
			'title'     => '',
			'parent_id' => null,
			'published' => date( 'Y-m-d H:i:s' ),
			'updated'   => date( 'Y-m-d H:i:s' ),
		);
	}

}
