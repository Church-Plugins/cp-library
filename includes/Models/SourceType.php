<?php

namespace CP_Library\Models;

use CP_Library\Exception;
use CP_Library\Setup\Tables\SourceMeta;
use CP_Library\Setup\Tables\Source;

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

	public function init() {
		global $wpdb;

		$this->type = 'source_type';

		parent::init();

		$this->table_name  = $wpdb->prefix . 'cp_' . $this->type;
	}

	/**
	 * @param $title
	 *
	 * @return mixed|void
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public static function get_by_title( $title ) {
		global $wpdb;

		$instance = new self();
		$type = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . $instance->table_name . " WHERE `title` = %s", $title )  );

		return apply_filters( 'cpl_source_type_get_by_title', $type, $title );
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

		$instance = new self();
		$types = $wpdb->get_results( "SELECT * FROM " . $instance->table_name );

		if ( ! $types ) {
			$types = [];
		}

		return apply_filters( 'cpl_get_all_source_types', $types );
	}

	public function get_items() {
		global $wpdb;

		$meta  = SourceMeta::get_instance();
		$item  = Source::get_instance();

		$sql = 'SELECT %1$s.* FROM %1$s
INNER JOIN %2$s
ON %1$s.id = %2$s.item_id
WHERE %2$s.key = "item_type" AND %2$s.item_type_id = %3$d
ORDER BY %2$s.order ASC';

		$items = $wpdb->get_results( $wpdb->prepare( $sql, $item->table_name, $meta->table_name, $this->id ) );

		return apply_filters( 'cpl_item_type_get_items', $items, $this );
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
		$this->delete_all_meta( $this->id, 'source_type_id' );
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
