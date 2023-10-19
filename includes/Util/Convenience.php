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

		if ( ! is_int( $timestamp ) && ! ctype_digit( $timestamp ) ) {
			$timestamp = strtotime( $timestamp );
		}

		$diff = time() - $timestamp;
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

	/**
	 * Normalize free-form timestamp input into HH:MM:SS or MM:SS format
	 *
	 * Returns an empty string if sense can't be made of the input
	 *
	 * @param string $input
	 * @return string
	 * @author costmo
	 */
	public static function normalize_timestamp( $input = '' ) {

		$return_value = '';

		// Sanity check the input
		if( empty( $input ) ) {
			return $return_value;
		}

		// Tokenize the input and sanity check the result
		$tokens = explode( ':', $input );
		$token_count = count( $tokens );
		if( empty( $tokens ) || $token_count > 3 ) {
			return $return_value;
		}

		if( 1 === $token_count ) {
			$return_value = "00";
		}
		foreach( $tokens as $token ) {
			$token = (int)$token;
			if( $token < 0 || $token > 60 ) {
				$token = 0;
			}
			if( $token < 10 ) {
				$return_value .= ":0" . $token;
			} else {
				$return_value .= ":" . $token;
			}
		}

		$return_value = preg_replace( "/^\:/", "", $return_value );
		return $return_value;
	}

	/**
	 * Force writing a message to a log file. Useful for when we've /dev/null'd stdout
	 *
	 * @param String $message
	 * @return void
	 * @author costmo
	 */
	public static function log( $message )
	{
		return; // disable logging for production

		// Enable to also watch from the browser through the Query Monitor plugin
		$also_qm_debug = true;

		// Generate a file name and output path
		$parsed = parse_url( get_site_url() );
		$file_name = self::slugify( $parsed['host'], '_' ) . '-' . date( 'Y-m-d' ) . ".log";
		$output_path = WP_CONTENT_DIR . '/uploads/' . $file_name;

		// Add a timestamp to the message...
		// ... and normalize to string and format improvment(s)
		if( empty( $message ) || (!is_string( $message ) && !is_numeric( $message )) ) { //
			$message = "[" . date( 'Y-m-d H:i:s' ) . "]\n" . var_export( $message, true );
		} else {
			$message = "[" . date( 'Y-m-d H:i:s' ) . "] " . $message;
		}

		// Write the message to file
		$fp = @fopen( $output_path, 'a' ); // costmo
		if( false !== $fp ) {
			fwrite( $fp, $message . "\n" );
			fclose( $fp );
		}

		// If we're watching from the browser (doesn't work for AJAX requests)
		if( $also_qm_debug ) {
			self::qmd( $message, 'debug' );
		}
	}

	/**
	 * Send a debug message to Query Monitor's Log panel
	 *
	 * Query Monitor log actions don't trigger on AJAX requests
	 *
	 * @param mixed $input			Typically, the message to log (string, array, etc.)
	 * @param string $level			The alert level. Default is `debug`
	 * @return void
	 * @author costmo
	 * @see https://querymonitor.com/docs/logging-variables/
	 */
	public static function qmd( $message, $level = 'debug' ) {
		// To match a Query Monitor action
		$valid_alert_levels = [ 'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug' ];

		// Sanity check and normalize possible input
		if( empty( $level ) || !is_string( $level ) || !in_array( $level, $valid_alert_levels ) ) {
			$level = 'debug';
		}

		do_action( "qm/" . $level, $message );
	}

	/**
	 * Turn a normal string into a slug
	 *
	 * @param String $input					// Input to sanitize
	 * @param String $replacement_char		// Character to use in place of invalid chars
	 * @return String
	 * @author costmo
	 */
	public static function slugify( $input, $replacement_char = "-" ) {
		$slug = strtolower( stripslashes( strip_tags( $input ) ) );

		// Sanity check and normalize
		if( !empty( $replacement_char ) && is_string( $replacement_char ) && strlen( $replacement_char ) === 1 ) {
			$replacement_char = $replacement_char;
		} else {
			$replacement_char = "-";
		}

		return preg_replace( "/[^a-z0-9\-]/", $replacement_char, $slug );
	}

	/**
	 * Convert a slug (or slug-like string) to a human-friendly string
	 *
	 * @param String $slug					// Input to munge
	 * @return String $replacement_char		// Character to use in place of commonly used slug chars
	 * @author costmo
	 */
	public static function de_slugify( $slug, $replacement_char = " " ) {
		$slug = stripslashes( strip_tags( $slug ) );

		// Sanity check and normalize
		if( !empty( $replacement_char ) && is_string( $replacement_char ) && strlen( $replacement_char ) === 1 ) {
			$replacement_char = $replacement_char;
		} else {
			$replacement_char = " ";
		}

		return preg_replace( "/[\-\_]/", $replacement_char, $slug );
	}

	/**
	 * Guarantee that a decoded JSON string returns an array
	 *
	 * If the input is not valid JSON, will return the inpt as-is
	 *
	 * @param String $input
	 * @return array
	 * @author costmo
	 */
	public static function arrayify_json( $input ) {

		try {
			$input = json_decode( json_encode( $input ), true, 512, JSON_THROW_ON_ERROR );
		} catch( \Exception $e ) {
			return $input;
		}

		return $input;

	}

}
