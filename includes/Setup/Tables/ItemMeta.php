<?php

namespace CP_Library\Setup\Tables;

/**
 * ItemMeta DB Class
 *
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ItemMeta Class
 *
 * @since 1.0.0
 */
class ItemMeta extends Table  {

	/**
	 * Get things started
	 *
	 * @since  1.0.0
	*/
	public function __construct() {
		global $wpdb;

		$this->table_name = $wpdb->prefix . 'cpl_item_meta';
		$this->version    = '1.0';

		parent::__construct();

		$enum = get_option( 'cp_library_item_meta_enum' );
		if ( is_admin() && self::get_keys() != $enum ) {
			global $wpdb;
			$keys = "'" . implode( "', '", self::get_keys() ) . "'";
			$sql  = "ALTER TABLE $this->table_name CHANGE `key` `key` ENUM( $keys ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;";
			$wpdb->query($sql);
			update_option( 'cp_library_item_meta_enum', self::get_keys() );
		}
	}

	/**
	 * Keys for key column
	 * @author Tanner Moushey
	 *
	 * @return mixed|void
	 * @since  1.0.0
	 *
	 */
	public static function get_keys() {
		return apply_filters( 'cpl_item_meta_keys_enum', [ '', 'avatar', 'name', 'video_url', 'audio_url', 'video_id_vimeo', 'video_id_facebook', 'item_type' ] );
	}

	/**
	 * Create the table
	 *
	 * @since   1.0.0
	*/
	public function get_sql() {

		$keys = "'" . implode( "', '", self::get_keys() ) . "'";

		return "CREATE TABLE " . $this->table_name . " (
			`id` bigint NOT NULL AUTO_INCREMENT,
			`key` ENUM( $keys ),
			`value` longtext,
			`item_id` bigint,
			`item_type_id` bigint,
			`order` bigint,
			`published` datetime NOT NULL,
			`updated` datetime NOT NULL,
			PRIMARY KEY  (`id`),
			KEY `idx_key` (`key`),
			KEY `idx_item_id` (`item_id`),
			KEY `idx_item_type_id` (`item_type_id`)
		) CHARACTER SET utf8 COLLATE utf8_general_ci;";


	}

}
