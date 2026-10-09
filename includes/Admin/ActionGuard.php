<?php

namespace CP_Library\Admin;

/**
 * Shared check for admin actions started from a cp_action request.
 */
class ActionGuard {

	/**
	 * Whether the current request may run an admin action.
	 *
	 * @param string $capability  Capability required for the action.
	 * @param string $nonce_action Nonce action that matches the link or form.
	 * @param int    $object_id   Optional object ID for a meta capability.
	 * @return bool
	 */
	public static function allows( $capability, $nonce_action, $object_id = 0 ) {
		$object_id = absint( $object_id );

		if ( $object_id ) {
			$allowed = current_user_can( $capability, $object_id );
		} else {
			$allowed = current_user_can( $capability );
		}

		if ( ! $allowed ) {
			return false;
		}

		$nonce = isset( $_REQUEST['_wpnonce'] ) ? (string) $_REQUEST['_wpnonce'] : '';

		return (bool) wp_verify_nonce( $nonce, $nonce_action );
	}
}
