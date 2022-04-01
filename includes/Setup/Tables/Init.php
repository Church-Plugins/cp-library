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
	protected function includes() {}

	protected function actions() {
		add_filter( 'cp_registered_tables', [ $this, 'register_tables' ] );
	}

	/** Actions ***************************************************/

	/**
	 * Add our tables to the Church Plugins registration function
	 *
	 * @param $tables
	 *
	 * @return mixed
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function register_tables( $tables ) {
		$tables[] = Item::get_instance();
		$tables[] = ItemMeta::get_instance();
		$tables[] = ItemType::get_instance();

		return $tables;
	}

}
