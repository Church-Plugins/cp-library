<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName
/**
 * Base class for handling migrations
 *
 * @package CP_Library
 * @since 1.3.0
 */

namespace CP_Library\Admin\Migrate;

/**
 * Base class for handling migrations
 *
 * @since 1.3.0
 */
abstract class Migration extends \WP_Background_Process {
	/**
	 * The plugin name to migrate from
	 *
	 * @var string
	 */
	public $name;

	/**
	 * The migration type identifier
	 *
	 * @var string
	 */
	public $type;

	/**
	 * The class constructor
	 */
	protected function __construct() {
		$this->action = "cpl_migration_{$this->type}";
		parent::__construct();
		add_action( "wp_ajax_cpl_poll_migration_{$this->type}", array( $this, 'send_progress' ) );
		add_action( "wp_ajax_cpl_start_migration_{$this->type}", array( $this, 'start_migration' ) );
		add_action( "wp_ajax_cpl_pause_migration_{$this->type}", array( $this, 'pause_migration' ) );
		add_action( "wp_ajax_cpl_resume_migration_{$this->type}", array( $this, 'resume_migration' ) );
	}

	/**
	 * Check for the count of items to migrate, 0 if none.
	 *
	 * @return int
	 */
	abstract public function get_item_count();

	/**
	 * Gets all data to migrate.
	 *
	 * @return mixed[]
	 */
	abstract public function get_migration_data();

	/**
	 * Migrate a single item
	 *
	 * @param mixed $post The data to migrate.
	 * @return void
	 */
	abstract public function migrate_item( $post );

	/**
	 * Handles the task
	 *
	 * @param mixed $item The data to migrate.
	 */
	public function task( $item ) {
		try {
			$this->migrate_item( $item );
			$status = get_transient( "cpl_migration_status_{$this->type}" );
			if ( ! $status ) {
				return false;
			}
			$status['progress']++;
			set_transient( "cpl_migration_status_{$this->type}", $status, HOUR_IN_SECONDS );
		} catch ( \ChurchPlugins\Exception $e ) {
			error_log( $e->getMessage() );
			return false;
		}
		return false;
	}

	/**
	 * Starts the migration
	 *
	 * @return void
	 */
	public function start_migration() {
		try {
			$items = $this->get_migration_data();
		} catch ( \ChurchPlugins\Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}

		set_transient(
			"cpl_migration_status_{$this->type}",
			array(
				'status'          => 'started',
				'migration_count' => max( count( $items ), 1 ),
				'progress'        => 0,
			),
			HOUR_IN_SECONDS
		);

		foreach ( $items as $item ) {
			$this->push_to_queue( $item );
		}

		$this->save()->dispatch();

		wp_send_json_success();
	}

	/**
	 * Pauses the migration
	 *
	 * @return void
	 */
	public function pause_migration() {
		$this->pause();
		wp_send_json_success();
	}

	/**
	 * Resume the migration
	 *
	 * @return void
	 */
	public function resume_migration() {
		$this->resume();
		wp_send_json_success();
	}

	/**
	 * Sends the migration progress to the client
	 *
	 * @return void
	 */
	public function send_progress() {
		$status = get_transient( "cpl_migration_status_{$this->type}" );

		if ( ! $status ) {
			wp_send_json_error(
				array(
					'progress' => 0,
					'status'   => 'loading',
				)
			);
		}

		$percentage = ( $status['progress'] / $status['migration_count'] ) * 100;

		wp_send_json_success(
			array(
				'progress' => $percentage,
				'status'   => 'in_progress',
			)
		);
	}
}
