<?php

namespace SC_Library\Setup;


/**
 * Setup plugin initialization
 */
class Init {

	/**
	 * @var Init
	 */
	protected static $_instance;

	/**
	 * @var Source
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public $source;

	/**
	 * Only make one instance of Init
	 *
	 * @return Init
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof Init ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor
	 *
	 */
	protected function __construct() {
		$this->includes();
		$this->actions();
	}

	/**
	 * Admin init includes
	 *
	 * @return void
	 */
	protected function includes() {
		$this->source = Source::get_instance();
	}

	protected function actions() {}

	/** Actions ***************************************************/

}
