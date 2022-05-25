<?php

namespace CP_Library\Integrations;

class Locations {

	/**
	 * @var Locations
	 */
	protected static $_instance;

	/**
	 * Only make one instance of Locations
	 *
	 * @return Locations
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof Locations ) {
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
	 * @return void
	 */
	protected function includes() {}

	protected function actions() {
		add_filter( 'cp_location_taxonomy_types', [ $this, 'tax_types' ] );
	}

	/** Actions ***************************************************/

	public function tax_types( $types ) {
		return array_merge( $types, cp_library()->setup->post_types->get_post_types() );
	}
}
