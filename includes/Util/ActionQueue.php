<?php
/**
 * Manages a queue of arbitrary data to process in the background
 *
 * @package CP_Library
 * @since 1.5.0
 */

namespace CP_Library\Util;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ActionQueue
 */
class ActionQueue extends \WP_Background_Process {
	/**
	 * @var string
	 */
	protected $prefix = 'cp_library';

	/**
	 * Class constructor
	 *
	 * @param string $action The action key
	 * @since 1.5.2
	 */
	public function __construct( $action ) {
		$this->action = 'action_queue_' . $action;
		parent::__construct();
	}

	/**
	 * @var array
	 */
	protected $listeners = [];

	/**
	 * Task to be run with each item
	 */
	protected function task( $data ) {
		try {
			$this->trigger( 'process', $data );
		} catch ( \Exception $e ) {
			// Log the error
			error_log( 'Error processing action in ActionQueue: ' . $this->action );
			error_log( $e->getMessage() );
		}
		
		return false;
	}

	/**
	 * Trigger an event
	 *
	 * @param string $action
	 * @param mixed ...$args
	 */
	public function trigger( $action, ...$args ) {
		if ( isset( $this->listeners[ $action ] ) ) {
			foreach ( $this->listeners[ $action ] as $callback ) {
				call_user_func_array( $callback, $args );
			}
		}
	}

	/**
	 * Register a listener for an action
	 *
	 * @param string $action
	 * @param callable $callback
	 */
	public function on( $action, $callback ) {
		if ( ! isset( $this->listeners[ $action ] ) ) {
			$this->listeners[ $action ] = [];
		}
		$this->listeners[ $action ][] = $callback;
	}

	/**
	 * Unregister a listener for an action
	 *
	 * @param string $action
	 * @param callable $callback
	 */
	public function off( $action, $callback ) {
		if ( isset( $this->listeners[ $action ] ) ) {
			$key = array_search( $callback, $this->listeners[ $action ] );
			if ( $key !== false ) {
				unset( $this->listeners[ $action ][ $key ] );
			}
		}
	}
}
