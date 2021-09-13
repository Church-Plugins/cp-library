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

		$output .= "</p><strong>00:</strong> " . CP_LIBRARY_PLUGIN_DIR . "</p>";
		$output .= "</p><strong>01:</strong> " . CP_LIBRARY_PLUGIN_URL . "</p>";
		$output .= "</p><strong>02:</strong> " . CP_LIBRARY_PLUGIN_FILE . "</p>";
		$output .= "</p><strong>03:</strong> " . CP_LIBRARY_PLUGIN_VERSION . "</p>";
		$output .= "</p><strong>04:</strong> " . CP_LIBRARY_INCLUDES . "</p>";
		$output .= "</p><strong>05:</strong> " . CP_LIBRARY_STORE_URL . "</p>";
		$output .= "</p><strong>06:</strong> " . CP_LIBRARY_ITEM_NAME . "</p>";
		$output .= "</p><strong>07:</strong> " . CP_LIBRARY_APP_PATH . "</p>";
		$output .= "</p><strong>08:</strong> " . CP_LIBRARY_ASSET_MANIFEST . "</p>";
		$output .= "</p><strong>09:</strong> " . CP_LIBRARY_UPREFIX . "</p>";

		return $output;
	}

	public function render_item_list( $args ) {

		$output  = self::staticScript( $args );
		$output .= '<div id="' . CP_LIBRARY_UPREFIX . '-item_list"></div>';

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