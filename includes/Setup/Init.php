<?php

namespace CP_Library\Setup;

/**
 * Setup plugin initialization
 */
class Init {

	/**
	 * @var Init
	 */
	protected static $_instance;

	/**
	 * @var Tables\Init
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public $tables;

	/**
	 * @var Podcast
	 * @since 1.0.4
	 */
	public $podcast;

	/**
	 * @var PostTypes\Init;
	 */
	public $post_types;

	/**
	 * @var Taxonomies\Init;
	 */
	public $taxonomies;

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
		Shortcode::get_instance();
		$this->podcast = Podcast::get_instance();
		$this->tables = Tables\Init::get_instance();
		$this->post_types = PostTypes\Init::get_instance();
		$this->taxonomies = Taxonomies\Init::get_instance();
	}

	protected function actions() {}

	/** Actions ***************************************************/

}
