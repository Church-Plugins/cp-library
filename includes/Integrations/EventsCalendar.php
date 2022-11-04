<?php

namespace CP_Library\Integrations;

use CP_Library\Admin\Settings;
use CP_Library\Models\ItemType;
use CP_EventsCalendar\Models\Location;

class EventsCalendar {

	/**
	 * @var EventsCalendar
	 */
	protected static $_instance;

	/**
	 * Only make one instance of EventsCalendar
	 *
	 * @return EventsCalendar
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof EventsCalendar ) {
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
		// Update Event Series slug if it's the same as ours
		add_filter( 'tribe_events_register_series_type_args', function ( $args ) {
			$slug = Settings::get_item_type( 'slug', strtolower( sanitize_title( Settings::get_item_type( 'plural_label', 'Series' ) ) ) );
			if ( 'series' == $slug ) {
				$args['rewrite']['slug'] = 'event-series';
			}

			return $args;
		} );
	}

	/** Actions ***************************************************/

}
