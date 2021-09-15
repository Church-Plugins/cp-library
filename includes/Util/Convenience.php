<?php

namespace CP_Library\Util;


/**
 * Container for generic convenience functions, such as string mungers
 *
 * @author costmo
 */
class Convenience
{

	/**
	 * Abstract an obnoxiously common WP_Post sanity check
	 *
	 * @param WP_Post $post
	 * @return boolean
	 * @author costmo
	 */
	public static function is_post( $post = null) {

		if( empty( $post ) || !is_object( $post ) || empty( $post->ID ) ) {
			return false;
		} else {
			return true;
		}

	}

	/**
	 * Slightly less code than a full, proper nonce check.
	 *
	 * The submitted values is assumed to be in the `$_REQUEST` array, and
	 *   the request field must match the action title
	 *
	 * e.g. `Convenience::nonce_is_valid( 'my_nonce' );` to validate
	 * `wp_create_nonce( 'my_nonce );` via `$_REQUEST['my_nonce'];`
	 *
	 * @param string $action
	 * @return boolean
	 * @author costmo
	 */
	public static function nonce_is_valid( $action = null ) {

		if( empty( $_REQUEST ) || !is_array( $_REQUEST ) || empty( $_REQUEST[ $action ] ) || !wp_verify_nonce( $_REQUEST[ $action ], $action ) ) {
			return false;
		} else {
			return true;
		}

	}

}