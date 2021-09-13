<?php

namespace CP_Library\Admin;

use CP_Library\Setup\Source;

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
		// return;

		if ( ! is_admin() ) {
			return;
		}

		$table_check = get_option( 'cpl_table_check', false );
		$installed   = false;
		$tables      = [ 'item', 'item_meta', 'item_type', 'source', 'source_meta', 'source_type' ];

		if ( false === $table_check || current_time( 'timestamp' ) > $table_check ) {

			foreach( $tables as $table ) {
				if ( ! @cp_library()->setup->tables->$table->installed() ) {
					// Create the source database (this ensures it creates it on multisite instances where it is network activated)
					@cp_library()->setup->tables->$table->create_table();
					$installed = true;
				}
			}

			if ( $installed ) {
				do_action( 'cpl_after_install' );
			}

			update_option( 'cpl_table_check', ( current_time( 'timestamp' ) + WEEK_IN_SECONDS ) );

		}

	}


}
