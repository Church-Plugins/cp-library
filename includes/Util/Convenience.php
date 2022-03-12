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

	public static function relative_time( $timestamp, $format = '' ) {

		$format = ! empty( $format ) ? $format : get_option( 'date_format' );

		if ( ! ctype_digit( $timestamp ) ) {
			$timestamp = strtotime( $timestamp );
		}

		$diff = current_time( 'timestamp' ) - $timestamp;
		if ( $diff == 0 ) {
			return 'now';
		} elseif ( $diff > 0 ) {
			$day_diff = floor( $diff / 86400 );
			if ( $day_diff == 0 ) {
				if ( $diff < 60 ) {
					return 'just now';
				}
				if ( $diff < 120 ) {
					return '1 minute ago';
				}
				if ( $diff < 3600 ) {
					return floor( $diff / 60 ) . ' minutes ago';
				}
				if ( $diff < 7200 ) {
					return '1 hour ago';
				}
				if ( $diff < 86400 ) {
					return floor( $diff / 3600 ) . ' hours ago';
				}
			}
			if ( $day_diff == 1 ) {
				return 'Yesterday';
			}
			if ( $day_diff < 7 ) {
				return $day_diff . ' days ago';
			}

			return date( $format, $timestamp );
		} else {
			$diff     = abs( $diff );
			$day_diff = floor( $diff / 86400 );
			if ( $day_diff == 0 ) {
				if ( $diff < 120 ) {
					return 'in a minute';
				}
				if ( $diff < 3600 ) {
					return 'in ' . floor( $diff / 60 ) . ' minutes';
				}
				if ( $diff < 7200 ) {
					return 'in an hour';
				}
				if ( $diff < 86400 ) {
					return 'in ' . floor( $diff / 3600 ) . ' hours';
				}
			}
			if ( $day_diff == 1 ) {
				return 'Tomorrow';
			}
			if ( $day_diff < 4 ) {
				return date( 'l', $timestamp );
			}

			return date( $format, $timestamp );
		}
	}

}
