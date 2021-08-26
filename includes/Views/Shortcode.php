<?php
namespace SC_Library\Views;

/**
 * Shortcode view/render class
 *
 * @author costmo
 */
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
	 * Generate static JS to feed parameters between PHP and JS
	 *
	 * @param array $args
	 * @return String
	 * @author costmo
	 */
	protected static function staticScript( $args = array() ) {

		$output = '
		<script>
			var sclParams = sclParams || {};
		';
		// Push shortcode parameters to the frontend so that JS has access to the data
		foreach( $args as $key => $value ) {
			$output .= "sclParams." . $key . " = '" . $value . "';\n";
		}
		$output .= '
		</script>
		';

		return $output;
	}

	/**
	 * Renderer for the `scl_root` shortcode - a top-level view
	 *
	 * @param Array $args
	 * @return String
	 * @author costmo
	 */
	public function render_root( $args ) {

		$output  = self::staticScript( $args );
		$output .= '<div id="' . SCL_APP_PREFIX . '-root"></div>';

		return $output;
	}

	public function render_item_list( $args ) {

		$output  = self::staticScript( $args );
		$output .= '<div id="' . SCL_APP_PREFIX . '-item_list"></div>';

		return $output;

	}

	public function render_item( $args ) {

		$output  = self::staticScript( $args );
		$output .= '<div id="' . SCL_APP_PREFIX . '-item"></div>';

		return $output;

	}

	public function render_source_list( $args ) {

		$output  = self::staticScript( $args );
		$output .= '<div id="' . SCL_APP_PREFIX . '-source_list"></div>';

		return $output;

	}

	public function render_source( $args ) {

		$output  = self::staticScript( $args );
		$output .= '<div id="' . SCL_APP_PREFIX . '-source"></div>';

		return $output;

	}

	public function render_player( $args ) {

		$output  = self::staticScript( $args );
		$output .= '<div id="' . SCL_APP_PREFIX . '-player"></div>';

		return $output;
	}


}