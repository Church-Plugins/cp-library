<?php
/**
 * CP Sermons YouTube integration.
 */

namespace CP_Library\Integrations;

use CP_Library\Admin\Settings;
use CP_Library\Setup\PostTypes\Item;

/**
 * YouTube integration.
 */
class YouTube {
	/**
	 * Singleton instance.
	 *
	 * @var YouTube
	 */
	protected static $_instance;

	/**
	 * Get the instance.
	 *
	 * @return YouTube
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof YouTube ) {
			self::$_instance = new YouTube();
		}

		return self::$_instance;
	}

	/**
	 * YouTube constructor.
	 */
	protected function __construct() {
		$this->actions();
	}

	/**
	 * Add actions.
	 */
	protected function actions() {
		add_action( 'cpl_import_transcript', [ $this, 'handle_import_request' ], 10, 2 );
		add_action( 'cmb2_render_cpl_import_transcript_button', [ $this, 'display_import_transcript_button' ], 10, 5 );
		add_filter( 'bulk_actions-edit-cpl_item', [ $this, 'add_bulk_actions' ] );
		add_filter( 'handle_bulk_actions-edit-cpl_item', [ $this, 'handle_bulk_actions' ], 10, 3 );
	}

	/**
	 * Handle transcript import request
	 */
	public function handle_import_request() {
		$post_id = absint( $_REQUEST['post_id'] ?? 0 );

		if ( ! $post_id ) {
			wp_send_json_error( 'No post ID provided' );
		}

		$result = $this->import_transcript( $post_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Import a transcript
	 *
	 * @return true|\WP_Error
	 */
	public function import_transcript( $post_id ) {
		if ( ! $post_id ) {
			return new \WP_Error( 'no_post_id', 'No post ID provided' );
		}

		$transcript = get_post_meta( $post_id, 'transcript', true );

		if ( $transcript ) {
			return new \WP_Error( 'transcript_exists', 'Transcript already exists for this post. Please delete existing transcript before running import.' );
		}

		$video_url = get_post_meta( $post_id, 'video_url', true );

		if ( ! $video_url ) {
			return new \WP_Error( 'no_video_url', 'No video URL found for this post' );
		}

		// detect YouTube video
		if ( strpos( $video_url, 'youtube.com' ) !== false ) {
			$video_id = explode( '?v=', $video_url );
			$video_id = $video_id[1] ?? '';
		} elseif ( strpos( $video_url, 'youtu.be' ) !== false ) {
			$video_id = explode( 'youtu.be/', $video_url );
			$video_id = $video_id[1] ?? '';
		} else {
			return new \WP_Error( 'not_youtube_video', 'Video URL is not a YouTube video' );
		}

		if ( ! $video_id ) {
			return new \WP_Error( 'no_video_id', 'Could not detect video ID from URL' );
		}

		// fetch transcript
		$transcript = $this->fetch_youtube_transcript( $video_id );

		if ( is_wp_error( $transcript ) ) {
			return $transcript;
		}

		// update post meta
		$raw_text = implode( ' ', array_map( function( $item ) {
			return $item['text'];
		}, $transcript ) );

		update_post_meta( $post_id, 'transcript', $raw_text );

		return true;
	}

	/**
	 * Fetch a YouTube video transcript
	 *
	 * @param string $video_id YouTube video ID.
	 * @return array|\WP_Error 
	 */
	public function fetch_youtube_transcript( $video_id ) {
		$USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.83 Safari/537.36,gzip(gfe)';
		$RE_XML_TRANSCRIPT = '/<text start="([^"]*)" dur="([^"]*)">([^<]*)<\/text>/';

		$request = wp_remote_get( 
			"https://www.youtube.com/watch?v=$video_id",
			[
				'headers' => [
					'User-Agent' => $USER_AGENT,
				]
			]
		);

		$status_code = wp_remote_retrieve_response_code( $request );

		if ( 200 !== $status_code ) {
			return new \WP_Error( 'youtube_error', 'YouTube returned status code ' . $status_code );
		}

		$raw_html = wp_remote_retrieve_body( $request );

		$split_html = explode( '"captions":', $raw_html );

		if( count( $split_html ) < 2 ) {
			if ( strpos( $raw_html, 'class="g-recaptcha"' ) !== false ) {
				return new \WP_Error( 'youtube_error', 'YouTube is blocking requests with a CAPTCHA' );
			}

			if ( strpos( $raw_html, '"playabilityStatus":' ) !== false ) {
				return new \WP_Error( 'youtube_error', 'YouTube video is not available' );
			}

			return new \WP_Error( 'youtube_error', 'Could not find captions in YouTube response' );
		}

		try {
			$captions = explode( ',"videoDetails', $split_html[1] );
			$captions = $captions[0];
			$captions = str_replace( "\n", '', $captions );
			$captions = json_decode( $captions, true );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'youtube_error', 'Could not decode YouTube captions' );
		}
	
		$transcript_url = $captions['playerCaptionsTracklistRenderer']['captionTracks'][0]['baseUrl'];

		// fetch transcript
		$transcript_request = wp_remote_get( 
			$transcript_url,
			[
				'headers' => [
					'User-Agent' => $USER_AGENT,
				]
			]
		);

		$status_code = wp_remote_retrieve_response_code( $transcript_request );

		if ( 200 !== $status_code ) {
			return new \WP_Error( 'youtube_error', 'YouTube returned status code ' . $status_code );
		}

		$transcript = wp_remote_retrieve_body( $transcript_request );

		preg_match_all( $RE_XML_TRANSCRIPT, $transcript, $results );

		$count = count( $results[0] );

		$output = [];

		for ( $i = 0; $i < $count; $i++ ) {
			$output[] = [
				'text'     => $results[3][$i],
				'duration' => $results[2][$i],
				'offset'   => $results[1][$i],
			];
		}

		return $output;
	}

	/**
	 * Renders a button for importing a transcript.
	 *
	 * @param CMB2_Field $field
	 * @param mixed $escaped_value
	 * @param int $object_id
	 * @param string $object_type
	 * @param CMB2_Types $field_type_object
	 * @since 1.3.0
	 * @return void
	 */
	public function display_import_transcript_button( $field, $escaped_value, $object_id, $object_type, $field_type_object ) {
		$button_text = $field->args( 'button_text' ) ? $field->args( 'button_text' ) : __( 'Import Transcript', 'cp-library' );
		$button_args = $field->args( 'query_args' );

		$button_args['cp_action'] = 'cpl_import_transcript';
		$button_args['post_id']   = $object_id;

		$button_url = add_query_arg( $button_args, admin_url( 'admin-post.php' ) );

		?>
		<script defer>
			jQuery($ => {
				$('#cpl-import-transcript').on('click', function() {
					const url = $(this).data('url');
					$.post(url, function(response) {
						if (response.success) {
							tinymce.get('transcript').setContent(response.data.map(item => item.text).join(' '));
						} else {
							alert(response.data);
						}
					}).catch(error => {
						console.error(error);
					});

					$('#wp-transcript-wrap').find('.wp-editor-area').val('Loading...');
				});
			})
		</script>

		<?php $import_url = add_query_arg( [
			'cp_action'  => 'cpl_import_transcript',
			'post_id' => $object_id,
		], admin_url( 'admin-post.php' ) ); ?>

		<button type="button" id="cpl-import-transcript" data-url="<?php echo esc_url( $import_url ); ?>" class="button cpl-import-transcript-btn"><?php echo \ChurchPlugins\Helpers::get_icon( 'youtube' ) . esc_html__( 'Import from YouTube', 'cp-library' ); ?></button>

		<?php
	}

	/**
	 * Add bulk actions.
	 *
	 * @param array $actions
	 * @return array
	 */
	public function add_bulk_actions( $actions ) {
		$actions['cpl_import_transcript'] = __( 'Import Transcript', 'cp-library' );

		return $actions;
	}

	/**
	 * Handle bulk actions.
	 *
	 * @param string $redirect_to
	 * @param string $doaction
	 * @param array $post_ids
	 * @return string
	 */
	public function handle_bulk_actions( $redirect_to, $doaction, $post_ids ) {
		if ( 'cpl_import_transcript' !== $doaction ) {
			return $redirect_to;
		}

		$successful_transcripts = 0;

		foreach ( $post_ids as $post_id ) {
			$success = $this->import_transcript( $post_id );

			if ( true === $success ) {
				$successful_transcripts++;
			}
		}

		// add success message
		if ( $successful_transcripts ) {
			$redirect_to = add_query_arg( 'cpl_import_transcript', $successful_transcripts, $redirect_to );
		}

		return $redirect_to;
	}
}
