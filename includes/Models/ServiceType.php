<?php

namespace CP_Library\Models;

use CP_Library\Admin\Settings;
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
class ServiceType extends Source {

	public static $type_key = 'service_type';

	public $title = null;

	public function init() {
		$this->post_type = 'cpl_service_type';

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
	public static function get_all_service_types() {
		global $wpdb;


		$type_id  = self::get_type_id();
		$meta     = SourceMetaTable::get_instance();
		$instance = new self();

		$sql = sprintf( 'SELECT %1$s.* FROM %1$s
INNER JOIN %2$s
ON %1$s.id = %2$s.source_id
WHERE %2$s.key = "source_type" AND %2$s.source_type_id = %3$d
ORDER BY %2$s.order ASC', $instance->table_name, $meta->table_name, $type_id );

		$service_types = $wpdb->get_results( $sql );

		if ( ! $service_types ) {
			$service_types = [];
		}

		return apply_filters( 'cpl_get_all_service_types', $service_types );
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

		$where = 'WHERE ( %2$s.key = "source_item" AND %2$s.source_type_id = %3$d AND %2$s.source_id = %4$d ) ';

		// if this is the default type, get everything that is not assigned to a service type
		if ( Settings::get_service_type( 'default_service_type' ) == $this->id ) {
			$where .= ' OR NOT EXISTS (SELECT %2$s.item_id FROM %2$s WHERE %1$s.id = %2$s.item_id AND %2$s.key = "source_item" AND %2$s.source_type_id = %3$d) ';
		}

		$sql = sprintf( 'SELECT %1$s.origin_id, %1$s.id FROM %1$s
INNER JOIN %2$s
ON %1$s.id = %2$s.item_id ' . $where .
'ORDER BY %2$s.order ASC', $instance->table_name, $meta->table_name, $type_id, $this->id );

		$items = $wpdb->get_results( $sql );

		if ( ! $items ) {
			$items = [];
		} else {
			$items = wp_list_pluck( $items, $field );
		}

		return apply_filters( 'cpl_get_service_type_items', $items );
	}

	public static function insert( $data ) {
		$service_type = parent::insert( $data ); // TODO: Change the autogenerated stub
		$service_type->add_type();
		return $service_type;
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
