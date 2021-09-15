<?php

namespace CP_Library\Setup\PostTypes;


/**
 * Setup plugin initialization for CPTs
 */
class Init {

	/**
	 * @var Init
	 */
	protected static $_instance;

	/**
	 * Setup Item CPT
	 *
	 * @var CP_Library\Setup\PostTypes\Item
	 */
	public $item;

	/**
	 * Setup Source CPT
	 *
	 * @var CP_Library\Setup\PostTypes\Source
	 * @author costmo
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
	 * Run includes and actions on instantiation
	 *
	 */
	protected function __construct() {
		$this->includes();
		$this->actions();
	}

	/**
	 * Plugin init includes
	 *
	 * @return void
	 */
	protected function includes() {
		$this->item = Item::get_instance();
		$this->source = Source::get_instance();
	}

	/**
	 * Plugin init actions
	 *
	 * @return void
	 * @author costmo
	 */
	protected function actions() {

		$this->item->register_post_type();
		$this->source->register_post_type();

		$this->source->add_actions();
		$this->item->add_actions();
	}


}
