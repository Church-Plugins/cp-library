<?php

namespace CP_Library\Models;

use CP_Library\Exception;
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
class Item extends Table  {

	protected $speakers = false;
	protected $types = false;

	public function init() {
		$this->type = 'item';
		$this->post_type = 'cpl_item';

		parent::init();
	}

	/**
	 *
	 * @return mixed|void
	 * @throws Exception
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_speakers() {
		global $wpdb;

		if ( false === $this->speakers ) {
			$speaker        = Speaker::get_instance();
			$speaker_type   = Speaker::get_type_id();
			$this->speakers = $wpdb->get_col( $wpdb->prepare( "SELECT `source_id` FROM " . $speaker->get_prop( 'meta_table_name' ) . " WHERE `key` = 'source_item' AND `item_id` = %d AND `source_type_id` = %d;", $this->id, $speaker_type ) );
			$this->update_cache();
		}

		return apply_filters( 'cpl_item_get_speakers', $this->speakers, $this );
	}

	/**
	 * Update the speakers associated with this item
	 *
	 * @param $speakers array Array of speaker IDs (source_type_id) to add
	 *
	 * @return bool
	 * @throws Exception
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function update_speakers( $speakers ) {
		$existing_speakers = $this->get_speakers();

		foreach( (array) $speakers as $speaker ) {
			if ( false !== $key = array_search( $speaker, $existing_speakers ) ) {
				unset( $existing_speakers[ $key ] );
				continue;
			}

			$data = [
				'key' => 'source_item',
				'item_id' => absint( $this->id ),
				'source_type_id' => Speaker::get_type_id(),
			];

			$speaker_model = Speaker::get_instance( $speaker );
			$speaker_model->update_meta( $data, false );
		}

		// remove any speakers which should no longer be attached
		foreach( $existing_speakers as $speaker ) {
			$speaker_model = Speaker::get_instance( $speaker );
			$speaker_model->delete_meta( absint( $this->id ), 'item_id' );
		}

		$this->speakers = $speakers;
		$this->update_cache();

		return true;
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

		if ( false === $this->types ) {
			$this->types = $wpdb->get_col( $wpdb->prepare( "SELECT `item_type_id` FROM " . $this->meta_table_name . " WHERE `key` = 'item_type' AND `item_id` = %d;", $this->id ) );
			$this->update_cache();
		}

		return apply_filters( 'cpl_item_get_types', $this->types, $this );
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
			if ( false !== $key = array_search( $type, $existing_types ) ) {
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

		$this->types = $types;
		$this->update_cache();

		return true;
	}

	/**
	 * Update the Item Type -> Item order
	 *
	 * @param $type
	 * @param $order
	 *
	 * @return false|int
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function update_type_order( $type, $order ) {
		global $wpdb;

		return $wpdb->update( $this->meta_table_name, [
			'order' => absint( $order ),
		], array(
			'item_id'      => $this->id,
			'item_type_id' => $type
		) );
	}

	/**
	 *
	 * @param $type
	 *
	 * @return bool
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */

	/**
	 * Add new type to item
	 *
	 * @param $type Integer The id of the type to add
	 *
	 * @return bool
	 * @throws Exception
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function add_type( $type ) {
		$existing_types = $this->get_types();

		// check if type is already set
		if ( false !== array_search( $type, $existing_types ) ) {
			return true;
		}

		$existing_types[] = absint( $type );
		return $this->update_types( $existing_types );
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
		$source = new Speaker();
		$source->delete_all_meta( $this->id, 'item_id' );
		$this->delete_all_meta( $this->id, 'item_id' );
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
