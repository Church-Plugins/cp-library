<?php
/**
 * CLI bootstrap.
 *
 * Registers WP-CLI command groups under the `wp cp` namespace. Each command
 * group is a class in CLI\Commands\ whose public methods become subcommands
 * (e.g. `wp cp sermonaudio import`).
 *
 * @package CP_Library
 */

namespace CP_Library\CLI;

/**
 * CLI bootstrap.
 */
class Init {

	/**
	 * Singleton instance.
	 *
	 * @var Init|null
	 */
	private static $instance;

	/**
	 * Get the singleton instance.
	 *
	 * @return Init
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Class constructor.
	 */
	private function __construct() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}
		$this->register_commands();
	}

	/**
	 * Register all `wp cp <group>` command classes.
	 */
	protected function register_commands() {
		\WP_CLI::add_command( 'cp sermonaudio', Commands\SermonAudio::class );
	}
}
