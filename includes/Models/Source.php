<?php

namespace CP_Library\Models;

use CP_Library\Exception;
use CP_Library\Util\Convenience as Convenience;

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

	public function init() {
		global $wpdb;

		$this->type = 'source';
		$this->post_type = 'cpl_speaker';

		parent::init();

		$this->table_name  = $wpdb->prefix . 'cp_' . $this->type;
		$this->meta_table_name  = $wpdb->prefix . 'cp_' . $this->type . "_meta";

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

		if ( false === $wpdb->query( $wpdb->prepare( "DELETE FROM " . static::get_prop('meta_table_name' ) . " WHERE `source_id` = %d AND `{$field}` = %s", $this->id, $value ) ) ) {
			throw new Exception( sprintf( 'The row (%d) was not deleted.', absint( $this->id ) ) );
		}

		return true;
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
		$this->delete_all_meta( $this->id, 'source_id' );
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
			'id'        => 0,
			'origin_id' => 0,
			'title'     => '',
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
			'id'             => '%d',
			'key'            => '%s',
			'value'          => '%s',
			'source_id'      => '%d',
			'source_type_id' => '%d',
			'item_id'        => '%d',
			'order'          => '%d',
			'published'      => '%s',
			'updated'        => '%s',
		);
	}

	/**
	 * Get default meta column values
	 *
	 * @since   1.0
	*/
	public function get_meta_column_defaults() {
		return array(
			'key'            => '',
			'value'          => '',
			'source_id'      => $this->id,
			'source_type_id' => 0,
			'item_id'        => 0,
			'order'          => 0,
			'published'      => date( 'Y-m-d H:i:s' ),
			'updated'        => date( 'Y-m-d H:i:s' ),
		);
	}

}
