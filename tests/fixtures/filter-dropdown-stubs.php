<?php
/**
 * Function stubs for rendering a filter dropdown without WordPress.
 *
 * @package CP_Library
 */

namespace {

	if ( ! function_exists( 'esc_html' ) ) {
		/**
		 * Matches WordPress esc_html(): encode once, leave an existing entity in place.
		 *
		 * @param string $text
		 * @return string
		 */
		function esc_html( $text ) {
			return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
		}
	}

	if ( ! function_exists( 'esc_attr' ) ) {
		/**
		 * @param string $text
		 * @return string
		 */
		function esc_attr( $text ) {
			return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
		}
	}

	if ( ! function_exists( 'checked' ) ) {
		/**
		 * @param mixed $checked
		 * @param mixed $current
		 * @param bool  $echo
		 * @return string
		 */
		function checked( $checked, $current = true, $echo = true ) {
			$result = ( (string) $checked === (string) $current ) ? " checked='checked'" : '';
			if ( $echo ) {
				echo $result;
			}

			return $result;
		}
	}
}
