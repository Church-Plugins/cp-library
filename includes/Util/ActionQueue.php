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
	 * @var string
	 */
	protected $action = 'fetch_queue';

	/**
	 * Enqueue an action
	 *
	 * @param string $action
	 * @param array $data
	 */
	public function add( $action, $data ) {
		$this->push_to_queue( [
			'action' => $action,
			'data'   => $data,
		] );
		$this->save()->dispatch();	
	}

	/**
	 * Task to be run with each item
	 */
	protected function task( $data ) {
		try {
			/**
			 * Run the action
			 *
			 * @param string $url
			 * @param array $args
			 */
			do_action( 'cpl_action_queue_process_' . $data['action'], $data['data'] );
		} catch ( \Exception $e ) {
			// Log the error
			error_log( $e->getMessage() );
		}
		
		return false;
	}
}
