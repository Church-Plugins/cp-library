<?php

namespace CP_Library\Models;

use CP_Library\Exception;
use ChurchPlugins\Setup\Tables\SourceMeta as SourceMetaTable;
use ChurchPlugins\Models\Source;
use ChurchPlugins\Models\SourceType;

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
class Speaker extends Source {

	public static $type_key = 'speaker';

	public $title = null;

	public function init() {
		$this->post_type = 'cpl_speaker';

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
	public static function get_all_speakers() {
		global $wpdb;


		$type_id  = self::get_type_id();
		$meta     = SourceMetaTable::get_instance();
		$instance = new self();

		$sql = sprintf( 'SELECT %1$s.* FROM %1$s
INNER JOIN %2$s
ON %1$s.id = %2$s.source_id
WHERE %2$s.key = "source_type" AND %2$s.source_type_id = %3$d
ORDER BY %2$s.order ASC, %1$s.title ASC', $instance->table_name, $meta->table_name, $type_id );

		$speakers = $wpdb->get_results( $sql );

		if ( ! $speakers ) {
			$speakers = [];
		}

		return apply_filters( 'cpl_get_all_speakers', $speakers );
	}

	public static function get_type_id() {
		if ( ! $type = SourceType::get_by_title( self::$type_key ) ) {
			try {
				$type = SourceType::insert( [ 'title' => self::$type_key ] );
			} catch ( Exception $e ) {
				error_log( $e );
			}
		}

		return $type->id;
	}

	public function get_all_items( $field = 'origin_id' ) {
		global $wpdb;


		$type_id  = self::get_type_id();
		$meta     = SourceMetaTable::get_instance();
		$instance = new Item();

		$sql = sprintf( 'SELECT %1$s.origin_id, %1$s.id FROM %1$s 
INNER JOIN %2$s 
ON %1$s.id = %2$s.item_id 
WHERE ( %2$s.key = "source_item" AND %2$s.source_type_id = %3$d AND %2$s.source_id = %4$d ) 
ORDER BY %2$s.order ASC', $instance->table_name, $meta->table_name, $type_id, $this->id );

		$items = $wpdb->get_results( $sql );

		if ( ! $items ) {
			$items = [];
		} else {
			$items = wp_list_pluck( $items, $field );
		}

		return apply_filters( 'cpl_get_speaker_items', $items );
	}

	public static function insert( $data ) {
		$speaker = parent::insert( $data ); // TODO: Change the autogenerated stub
		$speaker->add_type();
		return $speaker;
	}

	public function update( $data = array() ) {
		$this->add_type();
		return parent::update( $data ); // TODO: Change the autogenerated stub
	}

	public function add_type() {
		$this->update_meta( [
			'key' => 'source_type',
			'value' => 1, // just set some positive value
			'source_type_id' => self::get_type_id()
		] );
	}
}
