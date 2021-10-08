<?php

namespace CP_Library\Models;

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

	public static function init() {
		self::$type = 'source';

		parent::init();
	}

	/**
	 * Get columns and formats
	 *
	 * @since   1.0
	*/
	public static function get_columns() {
		return array(
			'id'        => '%d',
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
			'title'     => '',
			'status'    => '',
			'published' => date( 'Y-m-d H:i:s' ),
			'updated'   => date( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * `save_post` action handler for the CPT
	 *
	 * @param int $post_id
	 * @param WP_Post $post
	 * @return void
	 * @author costmo
	 */
	public function save_post( $post_id, $post ) {

//		// Surface sanity check
//		if( !Convenience::is_post( $post ) || $post->post_type !== $this->post_type ) {
//			return;
//		}
//
//		$parent_source_nonce_field = CP_LIBRARY_UPREFIX . "_source_parent_nonce";
//
//		/**
//		 * Parent Source metabox
//		 * TODO: There are bound to be more of these... refactor so each `if` contains one line of code
//		 */
//		if( Convenience::nonce_is_valid( $parent_source_nonce_field ) ) {
//
//			$parent_key = CP_LIBRARY_UPREFIX . '_source_parent_id';
//
//			// Don't `empty()`-check this because '0' is a valid value
//			if( is_array( $_REQUEST ) && array_key_exists( $parent_key, $_REQUEST ) && strlen( $_REQUEST[ $parent_key ] ) > 0 ) {
//
//				global $wpdb;
//				// and finally, we can't use wp_update_post because that will trigger an infinite loop here
//				$prepared = $wpdb->prepare(
//					"UPDATE " . $wpdb->prefix . "posts SET post_parent = %d WHERE ID = %d",
//					$_REQUEST[ $parent_key ], $post_id
//				);
//				$wpdb->query( $prepared );
//
//				// TODO: Maintain the wp_cpl_source table
//
//				// TODO: Find a nicer/more targeted way of doing this.
//				wp_cache_flush();
//			}
//		}
//		/**
//		 * Parent Source metabox
//		 */


	}

}
