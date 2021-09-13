<?php
namespace CP_Library\Controllers;

use CP_Library\Init as Init;
use CP_Library\Views\Shortcode as Shortcode_View;

/**
 * Shortcode controller class
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
	 * @return Shortcode
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
	 * Add the app's custom shortcodes to WP
	 *
	 * @param array $params
	 * @return void
	 * @author costmo
	 */
	public function add_shortcodes() {

		$view = Shortcode_View::get_instance();
		// An array of mappings from `shortcode` => `handler method`
		$codes = [
			CP_LIBRARY_UPREFIX . '_root'			=> 'render_root',

			CP_LIBRARY_UPREFIX . '_item_list'		=> 'render_item_list',
			CP_LIBRARY_UPREFIX . '_item'			=> 'render_item',

			CP_LIBRARY_UPREFIX . '_source_list'		=> 'render_source_list',
			CP_LIBRARY_UPREFIX . '_source'			=> 'render_source',

			CP_LIBRARY_UPREFIX . '_player'			=> 'render_player'
		];

		foreach( $codes as $shortcode => $handler ) {

			add_shortcode( $shortcode, [$view, $handler] );
		}
	}


}
