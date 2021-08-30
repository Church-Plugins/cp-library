<?php
namespace SC_Library\Controllers;

use SC_Library\Init as Init;
use SC_Library\Views\Shortcode as Shortcode_View;

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
			SCL_APP_PREFIX . '_root'			=> 'render_root',

			SCL_APP_PREFIX . '_item_list'		=> 'render_item_list',
			SCL_APP_PREFIX . '_item'			=> 'render_item',

			SCL_APP_PREFIX . '_source_list'		=> 'render_source_list',
			SCL_APP_PREFIX . '_source'			=> 'render_source',

			SCL_APP_PREFIX . '_player'			=> 'render_player'
		];

		foreach( $codes as $shortcode => $handler ) {

			add_shortcode( $shortcode, [$view, $handler] );
		}
	}


}
