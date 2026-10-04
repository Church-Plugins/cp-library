<?php
/**
 * Stand-in for ChurchPlugins\Helpers used only by unit tests that render a
 * template. The real class lives in the ChurchPlugins submodule, which the
 * unit suite does not boot.
 *
 * @package CP_Library
 */

namespace ChurchPlugins;

class Helpers {

	public static function get_icon( $icon ) {
		return '';
	}

	public static function get_param( $arr, $key, $default = '' ) {
		if ( is_array( $arr ) && isset( $arr[ $key ] ) ) {
			return $arr[ $key ];
		}

		return $default;
	}
}
