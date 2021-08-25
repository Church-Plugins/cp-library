<?php

namespace SC_Library;

class Init {

	/**
	 * @var
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
	 * Add Hooks and Actions
	 */
	protected function __construct() {
		add_action( 'plugins_loaded', array( $this, 'maybe_setup' ), - 9999 );
	}

	/**
	 * Setup the plugin
	 */
	public function maybe_setup() {
		if ( ! $this->check_required_plugins() ) {
			return;
		}

		$this->includes();
		$this->actions();
	}

	/**
	 * Includes
	 */
	protected function includes() {
		Admin\Init::get_instance();
	}

	/**
	 * Actions and Filters
	 */
	protected function actions() {}

	/** Actions **************************************/

	/**
	 * Required Plugins notice
	 */
	public function required_plugins() {
		printf( '<div class="error"><p>%s</p></div>', __( 'Your system does not meet the requirements for Church Plugins - Library', 'cp-library' ) );
	}

	/** Helper Methods **************************************/

	/**
	 * Make sure RCP is active
	 * @return bool
	 */
	protected function check_required_plugins() {

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		// @todo check for requirements before loading
		if ( 1 ) {
			return true;
		}

		add_action( 'admin_notices', array( $this, 'required_plugins' ) );

		return false;
	}

	/**
	 * Gets the plugin support URL
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_support_url() {
		return 'https://churchplugins.com/support';
	}

	/**
	 * Returns the plugin name, localized
	 *
	 * @since 1.0.0
	 * @return string the plugin name
	 */
	public function get_plugin_name() {
		return __( 'Church Plugins - Library', 'cp-library' );
	}

	public function get_id() {
		return 'cp-library';
	}

}
