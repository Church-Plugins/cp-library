<?php
namespace SC_Library\Views;

class Shortcode
{

	/**
	 * Singleton instance
	 *
	 * @var Shortcode
	 */
	protected static $_instance;

	/**
	 * Enforce singleton instantiation
	 *
	 * @return Init
	 */
	public static function get_instance() {
		if( !self::$_instance instanceof Shortcode ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor
	 *
	 * @author costmo
	 * @return void
	 */
	protected function __construct() {

	}

	/**
	 * Renderer for the `cpl_root` shortcode - a top-level view
	 *
	 * @param Array $args
	 * @return String
	 * @author costmo
	 */
	public function render_cpl_root( $args ) {

		$output = '
		<script>
			var cplParams = cplParams || {};
		';

		// Push shortcode parameters to the frontend so that JS has access to the data
		foreach( $args as $key => $value ) {
			$output .= "cplParams." . $key . " = '" . $value . "';\n";
		}

		$output .= '
		</script>
		<div id="' . CPL_APP_PREFIX . '-root"></div>';

		return $output;
	}


}