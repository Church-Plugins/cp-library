<?php

namespace CP_Library\Models;

use CP_Library\Exception;
use CP_Library\Setup\Tables\ItemMeta;
use CP_Library\Setup\Tables\Item;
use CP_Library\Models\Item as ItemModel;
use ChurchPlugins\Models\Table;

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

	protected $items = false;

	protected static $_all_types = false;

	public function init() {
		$this->type = 'item_type';
		$this->post_type = 'cpl_item_type';

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

		if ( false === self::$_all_types ) {
			$instance = new self();

			$types = $wpdb->get_results( "SELECT * FROM " . $instance->table_name );

			if ( ! $types ) {
				$types = [];
			}

			self::$_all_types = $types;
		}

		return apply_filters( 'cpl_get_all_item_types', self::$_all_types );
	}

	public function get_items( $force = false ) {
		global $wpdb;

		if ( false === $this->items || $force ) {
			$meta = ItemMeta::get_instance();
			$item = Item::get_instance();

			$sql = 'SELECT %1$s.* FROM %1$s
INNER JOIN %2$s
ON %1$s.id = %2$s.item_id
WHERE %2$s.key = "item_type" AND %2$s.item_type_id = %3$d
ORDER BY %2$s.order ASC';

			$this->items = $wpdb->get_results( $wpdb->prepare( $sql, $item->table_name, $meta->table_name, $this->id ) );

			foreach( $this->items as $item ) {
				$item = new ItemModel( $item );
				$item->update_cache();
			}

			$this->update_cache();
		}

		return apply_filters( 'cpl_item_type_get_items', $this->items, $this );
	}

	/**
	 * Update the ItemType to match the most recent Item. Save the date from the first and last item.
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function update_dates() {
		$items = $this->get_items();
		$dates = [];

		$status = get_post_status( $this->origin_id );
		if ( empty( $items ) ) {
			if ( 'publish' === $status && apply_filters( 'cpl_item_type_require_items', true, $this ) ) {
				wp_update_post( [ 'ID' => $this->origin_id, 'post_status' => 'draft' ] );
				return 'draft';
			}

			return true;
		}

		foreach( $items as $item ) {
			$dates[] = get_the_date( 'U', $item->origin_id );
		}

		asort( $dates );

		$first = $dates[0];
		$last  = $dates[ count( $dates ) - 1 ];

		$this->update_meta_value( 'first_item_date', $first );
		$this->update_meta_value( 'last_item_date', $last );

		wp_update_post( [ 'ID' => $this->origin_id, 'post_date' => date( 'Y-m-d H:i:s', $last ), 'post_status' => 'publish' ] );

		return 'publish' == $status ? true : 'publish';
	}

	/**
	 * Also delete all item associated meta
	 *
	 * @return bool|void
	 * @throws Exception
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function delete() {
		do_action( "cpl_{$this->type}_delete_meta_before" );
		$this->delete_all_meta( $this->id, 'item_type_id' );
		do_action( "cpl_{$this->type}_delete_meta_after" );

		parent::delete();
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
