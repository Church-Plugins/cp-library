<?php
/**
 * CP Library integration with OpenAI's ChatGPT API
 *
 * @package CP_Library
 * @since 1.5.0
 */

namespace CP_Library\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OpenAI {
	/**
	 * Singleton instance
	 *
	 * @var OpenAI
	 */
	protected static $_instance;

	/**
	 * Whether the shutdown function has been registered
	 *
	 * @var bool
	 */
	protected $shutdown_registered = false;

	/**
	 * Action queue
	 *
	 * @var \CP_Library\Util\ActionQueue
	 */
	protected $action_queue;

	/**
	 * Get the singleton instance
	 *
	 * @return OpenAI
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof OpenAI ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->action_queue = new \CP_Library\Util\ActionQueue( 'fetch_ai_transcript' );
		$this->actions();
	}

	/**
	 * Register actions
	 */
	protected function actions() {
		add_action( 'cpl_imported_transcript', [ $this, 'enqueue_transcript_fetcher' ], 10, 2 );
		$this->action_queue->on( 'process', [ $this, 'fetch_ai_transcript' ] );
	}

	/**
	 * Enqueue a fetch transcript action
	 *
	 * @param string $transcript
	 * @param int $post_id
	 */
	public function enqueue_transcript_fetcher( $transcript, $post_id ) {
		/**
		 * Get the OpenAI API key
		 *
		 * @param string $api_key
		 * @return string
		 */
		$api_key = apply_filters( 'cpl_openai_api_key', '' );

		if ( ! empty( $api_key ) ) {
			$this->action_queue->push_to_queue( [ 'post_id' => $post_id ] );
			$this->action_queue->save()->dispatch();
			cp_library()->logging->log( 'Enqueued transcript fetcher for post #' . $post_id );
		}
	}

	/**
	 * Fetch a transcript from OpenAI
	 *
	 * @param array $data
	 */
	public function fetch_ai_transcript( $data ) {
		$post_id = $data['post_id'] ?? 0;
		
		if ( ! $post_id ) {
			return;
		}

		// filter documented in the enqueue_transcript_fetcher method
		$api_key = apply_filters( 'cpl_openai_api_key', '' );

		if ( empty( $api_key ) ) {
			return;
		}

		// OpenAI model config
		$endpoint      = 'https://api.openai.com/v1/chat/completions';
	  $system_prompt = 'Your job is to format a transcript into formatted english. Fix misspelled words, capitalization, and punctuation, but preserve what the speaker says word for word, without changing phrases or grammar. There will be timestamps included, formatted as (t:<seconds>). Make sure to keep these in their correct places despite the changed punctuation. Split content into small paragraphs, around 5-7 sentences.';
		$temperature   = 0.0;
		$max_tokens    = 16000;
		$model         = 'gpt-4o-mini';

		// the request will likely exceed the server timeout, so remove the time limit
		set_time_limit(0);

		if ( ! $this->shutdown_registered ) {
			$this->shutdown_registered = true;
			register_shutdown_function( [ $this, 'shutdown' ] );
		}

		// get the post's transcript from the database
		$transcript = get_post_meta( $post_id, 'transcript', true );
		if ( empty( $transcript ) ) {
			return false;
		}

		$args = [
			'model'       => $model,
			'temperature' => $temperature,
			'max_tokens'  => $max_tokens,
			'messages'    => [
				[
					'role'    => 'system',
					'content' => $system_prompt
				],
				[
					'role'    => 'user',
					'content' => $transcript
				]
			],
		];

		/**
		 * Request args sent to OpenAI to fetch the transcript
		 *
		 * @param array $args
		 * @param int $post_id
		 * @return array
		 */
		$args = apply_filters( 'cpl_openai_fetch_transcript_args', $args, $post_id );


		try {
			cp_library()->logging->log( 'Fetching transcript for post #' . $post_id . ' from OpenAI.' );

			$ch = curl_init( $endpoint );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_POST, true );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $args ) );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, [
				'Content-Type: application/json',
				'Authorization: Bearer ' . $api_key,
			] );
			curl_setopt( $ch, CURLOPT_TIMEOUT, 600 );		
			$response = curl_exec( $ch );

			cp_library()->logging->log( 'Transcript fetched for post #' . $post_id . ' from OpenAI.' );
	
			if ( false === $response || curl_getinfo( $ch, CURLINFO_HTTP_CODE ) !== 200 ) {
				 if ( false === $response ) {
					cp_library()->logging->log( 'Failed to fetch transcript for post #' . $post_id . '. Curl error: ' . curl_error( $ch ) );
				} else {
					cp_library()->logging->log( 'Failed to fetch transcript for post #' . $post_id . '. HTTP code: ' . curl_getinfo( $ch, CURLINFO_HTTP_CODE ) . '.' );
				}
				return;
			}
	
			$response = json_decode( $response, true );
		} catch ( \Exception $e ) {
			cp_library()->logging->log( 'Failed to fetch transcript for post #' . $post_id . '. Exception: ' . $e->getMessage() );
			return;
		}
		
		/**
		 * Filter the transcript fetched from OpenAI
		 *
		 * @param string $transcript
		 * @param int $post_id
		 * @return string
		 */
		$transcript = apply_filters( 'cpl_openai_fetched_transcript', $response['choices'][0]['message']['content'], $post_id );

		update_post_meta( $post_id, 'transcript', $transcript );
	}

	/**
	 * On some servers, calling set_time_limit(0) may not work
	 * we can detect that here and log the error
	 */
	public function shutdown() {
		$error = error_get_last();

		if ( $error ) {
			cp_library()->logging->log( 'Script shutdown occurred: ' . $error['message'] );
		}
	}
}
