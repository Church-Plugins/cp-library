<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace CP_Library\API;

/**
 * CP Library AJAX methods.
 */
class AJAX {

	/**
	 * The class instance
	 *
	 * @var AJAX
	 */
	protected static $instance;

	/**
	 * The class constructor
	 */
	protected function __construct() {
		$this->includes();
		$this->actions();
	}

	/**
	 * Get the class instance
	 *
	 * @return AJAX
	 */
	public static function get_instance() {
		if ( ! self::$instance instanceof AJAX ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register actions
	 */
	protected function actions() {}

	/**
	 * Include files
	 */
	protected function includes() {}
}
