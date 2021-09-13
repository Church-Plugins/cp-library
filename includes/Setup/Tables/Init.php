<?php

namespace CP_Library\Setup\Tables;


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
	 * @var SourceMeta
	 */
	public $source_meta;

	public $source_type;

	/**
	 * @var Item
	 */
	public $item;

	/**
	 * @var ItemMeta
	 */
	public $item_meta;

	/**
	 * @var string
	 */
	public $item_type;

	/**
	 * @var array
	 */
	public $options;

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
		$this->source_meta = SourceMeta::get_instance();
		$this->source_type = SourceType::get_instance();

		$this->item = Item::get_instance();
		$this->item_meta = ItemMeta::get_instance();
		$this->item_type = ItemType::get_instance();
	}

	protected function actions() {}

	/** Actions ***************************************************/

}
