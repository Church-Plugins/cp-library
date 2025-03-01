<?php
/**
 * A utility class used for dispatching an async action when pulling from an API
 *
 * @since 1.4.12
 */

namespace CP_Library\Adapters;

use \ChurchPlugins\Utils\WP_Async_Request;

/**
 * Dispatcher class
 */
class Dispatcher extends WP_Async_Request {
	/**
	 * Action
	 */
	protected $action = 'pull';

	/**
	 * Request limit
	 *
	 * @var int
	 */
	protected $limit = 5; // 5 requests per batch

	/**
	 * Class constructor
	 *
	 * @param string $prefix The prefix for the async action.
	 * @param string $action The action name for the async action.
	 */
	public function __construct( $prefix ) {
		$this->prefix = $prefix;
		parent::__construct();
	}

	/**
	 * Limit number of requests that can be made in one go
	 *
	 * @param int $amount The amount of requests to limit.
	 * @return Dispatcher
	 */
	public function set_limit( $amount ) {
		$this->limit = $amount;
		return $this;
	}

	/**
	 * Set the current batch
	 *
	 * @param int $batch The current batch number.
	 * @return Dispatcher
	 */
	public function set_batch( $batch ) {
		$this->data( [ 'batch' => $batch ] );
		return $this;
	}

	protected function handle() {
		// exit if no batch is set
		if ( ! isset( $_REQUEST['batch'] ) ) {
			return;
		}

		$batch = max( absint( $_REQUEST['batch'] ), 1 );
		$done = false;
		for ( $i = 0; $i < $this->limit; $i++ ) {
			/**
			 * Make request
			 *
			 * @param bool $done Whether the request is done.
			 * @param int  $batch The current batch number.
			 * @return bool
			 * @since 1.4.12
			 */
			$done = apply_filters( $this->prefix . '_make_request', $done, $batch );

			if ( $done ) {
				break;
			}

			$batch++;
		}

		if ( $done ) {
			/**
			 * Fires when all requests are done
			 *
			 * @param int $batch The current batch number.
			 * @since 1.4.12
			 */
			do_action( $this->prefix . '_done', $batch );
		} else {
			// continue making requests
			$this->data( [ 'batch' => $batch ] )->dispatch();
		}
	}
}
