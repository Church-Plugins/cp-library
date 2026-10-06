<?php
/**
 * Checks for admin requests sent through cp_action.
 *
 * @package CP_Library
 */

namespace CP_Library\Admin;

/**
 * Capability and nonce check for one admin request.
 *
 * @since 1.7.1
 */
class Request {

	/**
	 * Whether the current user may run this request.
	 *
	 * The capability is the one used by the related admin screen. The nonce
	 * action is the request name.
	 *
	 * @since 1.7.1
	 *
	 * @param string $capability Capability name.
	 * @param string $action     Nonce action.
	 * @param mixed  ...$args    Optional object ids for the capability check.
	 * @return bool
	 */
	public static function allowed( $capability, $action, ...$args ) {
		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';

		if ( empty( $args ) ) {
			$can = current_user_can( $capability );
		} else {
			$can = current_user_can( $capability, ...$args );
		}

		return $can && (bool) wp_verify_nonce( $nonce, $action );
	}

	/**
	 * Add the nonce for a cp_action query argument.
	 *
	 * @since 1.7.1
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public static function with_nonce( $args ) {
		if ( ! empty( $args['cp_action'] ) && is_string( $args['cp_action'] ) ) {
			$args['_wpnonce'] = wp_create_nonce( $args['cp_action'] );
		}

		return $args;
	}
}
