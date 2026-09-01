<?php

namespace CP_Library\Models;

use CP_Library\Exception;
use CP_Library\Setup\Tables\ItemMeta;
use CP_Library\Setup\Tables\Item;
use CP_Library\Models\Item as ItemModel;
use ChurchPlugins\Models\Table;
use CP_Library\Admin\Settings;

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
		global $wpdb;

		$this->type = 'item_type';
		$this->post_type = 'cpl_item_type';

		parent::init();

		$this->table_name  = $wpdb->prefix . 'cpl_' . $this->type;
		// A series has no meta table of its own: its rows (`item_type`, keyed by
		// `item_type_id`) live in the item meta table. This used to name a
		// cpl_item_type_meta table that never existed, so delete_all_meta() failed
		// silently and every sermon in a deleted series kept a row pointing at it.
		$this->meta_table_name  = $wpdb->prefix . 'cpl_item_meta';
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

			$sort_order = Settings::get_item_type( 'item_sort_order', 'DESC' );
			$sort_by    = Settings::get_item_type( 'item_sort_by', 'post_date' );

			// Return items for this Type in POST date order
			$prepared = $wpdb->prepare(
				"SELECT		{$item->table_name}.*
				 FROM		{$item->table_name}, {$meta->table_name}, {$wpdb->prefix}posts
				 WHERE 		{$meta->table_name}.key = 'item_type' AND
				 			{$meta->table_name}.item_type_id = %d AND
							{$item->table_name}.id = {$meta->table_name}.item_id AND
							{$wpdb->prefix}posts.ID = {$item->table_name}.origin_id
				 ORDER BY {$wpdb->prefix}posts.{$sort_by} {$sort_order}",
				$this->id
			);

			$this->items = $wpdb->get_results( $prepared );

			if ( ! $this->items ) {
				$this->items = [];
			}

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

		foreach( $items as $item ) {
			$date = get_the_date( 'U', $item->origin_id );

			if ( $date < current_time( 'timestamp' ) ) {
				$dates[] = $date;
			}
		}

		$status = get_post_status( $this->origin_id );
		if ( empty( $dates ) ) {
			// we need this meta value for sorting
			update_post_meta( $this->origin_id, 'last_item_date', 0 );

			if ( 'publish' === $status && apply_filters( 'cpl_item_type_require_items', false, $this ) ) {
				wp_update_post( [ 'ID' => $this->origin_id, 'post_status' => 'draft' ] );
				return 'draft';
			}

			return true;
		}

		asort( $dates );

		$first = $dates[0];
		$last  = $dates[ count( $dates ) - 1 ];

		update_post_meta( $this->origin_id, 'first_item_date', $first );
		update_post_meta( $this->origin_id, 'last_item_date', $last );

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
		global $wpdb;

		do_action( "cpl_{$this->type}_delete_meta_before" );

		// The items whose rows are about to go still hold them in their caches.
		$affected = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT `item_id` FROM {$this->meta_table_name} WHERE `item_type_id` = %d", $this->id ) );

		$this->delete_all_meta( $this->id, 'item_type_id' );

		if ( function_exists( 'cp_library_invalidate_item_caches' ) ) {
			cp_library_invalidate_item_caches( $affected );
		}

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
