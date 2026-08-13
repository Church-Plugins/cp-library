<?php
/**
 * Real definitions for the handful of WordPress functions that are pure.
 *
 * The rule for this file: a function belongs here only if it is deterministic,
 * side-effect free, and has one fixed definition that cannot reasonably differ
 * between sites — `absint()` is `abs( intval( $x ) )` and nothing else. Copying
 * those in keeps tests readable, because restating them as Brain Monkey stubs in
 * every test case says nothing about what the test is actually asserting.
 *
 * Everything else — get_option(), apply_filters(), get_post_meta(), anything
 * touching $wpdb — must be stubbed per test with Brain Monkey. Those carry the
 * assumptions the test is making, and hiding them in a shared bootstrap would
 * make tests pass for reasons their author never stated.
 *
 * @package CP_Library
 */

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( intval( $maybeint ) );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $string ) {
		return untrailingslashit( $string ) . '/';
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $string ) {
		return rtrim( $string, '/\\' );
	}
}

if ( ! function_exists( 'maybe_serialize' ) ) {
	function maybe_serialize( $data ) {
		if ( is_array( $data ) || is_object( $data ) ) {
			return serialize( $data );
		}

		return $data;
	}
}
