<?php
namespace CP_Library\Views;

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
			var cplParams = cplParams || {};
		';
		// Push shortcode parameters to the frontend so that JS has access to the data
		if( !empty( $args) && is_array( $args ) ) {
			foreach( $args as $key => $value ) {
				$output .= "cplParams." . $key . " = '" . $value . "';\n";
			}
		}
		$output .= '
		</script>
		';

		return $output;
	}

	/**
	 * Renderer for the `cpl_root` shortcode - a top-level view
	 *
	 * @param Array $args
	 * @return String
	 * @author costmo
	 */
	public function render_root( $args ) {

		$output  = self::staticScript( $args );
		$output .= '<div id="' . CP_LIBRARY_UPREFIX . '_root"></div>';

		return $output;
	}

	public function render_item_list( $args ) {

		$output  = self::staticScript( $args );
		$output .= '<div id="' . CP_LIBRARY_UPREFIX . '_item_list"></div>';

		return $output;
	}

	public function render_item( $args ) {

		$output  = self::staticScript( $args );
		$output .= '<div id="' . CP_LIBRARY_UPREFIX . '_item"></div>';

		return $output;

	}

	public function render_source_list( $args ) {

		$output  = self::staticScript( $args );
		$output .= '<div id="' . CP_LIBRARY_UPREFIX . '_source_list"></div>';

		return $output;

	}

	public function render_source( $args ) {

		$output  = self::staticScript( $args );
		$output .= '<div id="' . CP_LIBRARY_UPREFIX . '_source"></div>';

		return $output;

	}

	public function render_player( $args ) {

		$output  = self::staticScript( $args );
		$output .= '<div id="' . CP_LIBRARY_UPREFIX . '_player"></div>';

		return $output;
	}


}
