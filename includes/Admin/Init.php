<?php

namespace CP_Library\Admin;

use SC_Library\Setup\Source;

/**
 * Admin-only plugin initialization
 */
class Init {

	/**
	 * @var Init
	 */
	protected static $_instance;

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
		License::get_instance();
		Settings::get_instance();
	}

	protected function actions() {
		add_action( 'admin_init', [ $this, 'after_install' ] );
	}

	/** Actions ***************************************************/

	public function after_install() {
		return;

		new \WP_Post()
		if ( ! is_admin() ) {
			return;
		}

		$table_check = get_option( SCL_APP_PREFIX . '_table_check', false );
		$installed   = false;

		if ( false === $table_check || current_time( 'timestamp' ) > $table_check ) {

			if ( ! @cp_library()->setup->source->installed() ) {
				// Create the source database (this ensures it creates it on multisite instances where it is network activated)
				@cp_library()->setup->source->create_table();
				$installed = true;
			}

			if ( $installed ) {
				do_action( SCL_APP_PREFIX . '_after_install' );
			}

			update_option( SCL_APP_PREFIX . '_table_check', ( current_time( 'timestamp' ) + WEEK_IN_SECONDS ) );

		}

	}


}
