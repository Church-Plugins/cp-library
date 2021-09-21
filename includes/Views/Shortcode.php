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
		$output .= '<div id="' . CP_LIBRARY_UPREFIX . '-root"></div>';

		return $output;
	}

	public function render_item_list( $args ) {

		error_log( "RENDER_ITEM_LIST" );

		$output  = self::staticScript( $args );
		$output .= '<div id="' . CP_LIBRARY_UPREFIX . '-item_list"></div>';

		// TODO: Never echo from a function being called by a shortcode. This messes up admin (among other things)
		// https://developer.wordpress.org/reference/functions/add_shortcode/
		// "Note that the function called by the shortcode should never produce an output of any kind. Shortcode
		//    functions should return the text that is to be used to replace the shortcode. Producing the output
		//    directly will lead to unexpected results."
		$output .= include( CP_LIBRARY_PLUGIN_DIR . 'templates/item-list.php' );

		return $output;
	}

	public function render_item( $args ) {

		$output  = self::staticScript( $args );
		$output .= '<div id="' . CP_LIBRARY_UPREFIX . '-item"></div>';

		return $output;

	}

	public function render_source_list( $args ) {

		$output  = self::staticScript( $args );
		$output .= '<div id="' . CP_LIBRARY_UPREFIX . '-source_list"></div>';
		// $output .= include( CP_LIBRARY_PLUGIN_DIR . 'templates/source-list.php' );

		return $output;

	}

	public function render_source( $args ) {

		$output  = self::staticScript( $args );
		$output .= '<div id="' . CP_LIBRARY_UPREFIX . '-source"></div>';

		return $output;

	}

	public function render_player( $args ) {

		$output  = self::staticScript( $args );
		$output .= '<div id="' . CP_LIBRARY_UPREFIX . '-player"></div>';

		return $output;
	}


}
