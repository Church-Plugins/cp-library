<?php
namespace SC_Library\Controllers;

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
			'cpl_root'			=> 'render_cpl_root',

			'cpl_item_list'		=> 'render_item_list',
			'cpl_item'			=> 'render_item',

			'cpl_source_list'	=> 'render_source_list',
			'cpl_source'		=> 'render_source',

			'cpl_player'		=> 'render_player'
		];

		foreach( $codes as $shortcode => $handler ) {

			add_shortcode( $shortcode, [$view, $handler] );
		}
	}


}