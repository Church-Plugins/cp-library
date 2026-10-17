<?php
/**
 * CP Sermons YouTube integration.
 */

namespace CP_Library\Integrations;

use CP_Library\Admin\Settings;
use CP_Library\Controllers\Item;

/**
 * YouTube integration.
 */
class YouTube {
	/**
	 * InnerTube player endpoint. The ANDROID client context below is what
	 * returns caption track URLs that a plain GET can still read.
	 */
	const PLAYER_ENDPOINT = 'https://www.youtube.com/youtubei/v1/player';

	/**
	 * Singleton instance.
	 *
	 * @var YouTube
	 */
	protected static $_instance;

	/**
	 * Caption-upload result code added to the post-save redirect.
	 *
	 * @var string
	 */
	protected $caption_upload_result = '';

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
		add_action( 'cmb2_render_cpl_transcript_caption_upload', [ $this, 'display_caption_upload' ], 10, 5 );
		add_action( 'wp_ajax_cpl_upload_transcript_caption', [ $this, 'handle_caption_upload' ] );
		add_action( 'save_post', [ $this, 'save_caption_upload' ], 100 );
		// Classic #post is not multipart, so Update never filled $_FILES. The block
		// editor posts each metabox with FormData, which already includes this file.
		add_action( 'post_edit_form_tag', [ $this, 'add_caption_form_enctype' ] );
		add_filter( 'redirect_post_location', [ $this, 'filter_caption_upload_redirect' ] );
		add_action( 'admin_notices', [ $this, 'render_admin_notices' ] );
		add_filter( 'bulk_actions-edit-' . CP_LIBRARY_UPREFIX . '_item', [ $this, 'add_bulk_actions' ] );
		add_filter( 'handle_bulk_actions-edit-' . CP_LIBRARY_UPREFIX . '_item', [ $this, 'handle_bulk_actions' ], 10, 3 );
	}

	/**
	 * Nonce action for a transcript import request.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function import_request_nonce_action( $post_id ) {
		return 'cpl_import_transcript_' . absint( $post_id );
	}

	/**
	 * Admin URL that starts a transcript import for one item.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function get_import_request_url( $post_id ) {
		$post_id = absint( $post_id );

		return add_query_arg(
			array(
				'cp_action' => 'cpl_import_transcript',
				'post_id'   => $post_id,
				'_wpnonce'  => wp_create_nonce( $this->import_request_nonce_action( $post_id ) ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Handle transcript import request.
	 *
	 * Confirms the request and that the user can edit the target item
	 * before importing.
	 */
	public function handle_import_request() {
		$post_id = $this->requested_post_id();

		if ( ! $post_id ) {
			wp_send_json_error( 'No post ID provided' );
			return;
		}

		if ( ! wp_verify_nonce( $this->requested_nonce(), $this->import_request_nonce_action( $post_id ) ) ) {
			wp_send_json_error( 'The request could not be verified.' );
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( 'You are not allowed to edit this item.' );
			return;
		}

		if ( ! $this->is_item_post( $post_id ) ) {
			wp_send_json_error( 'Invalid item.' );
			return;
		}

		$result = $this->import_transcript( $post_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
			return;
		}

		wp_send_json_success( [
			'transcript' => get_post_meta( $post_id, 'transcript', true ),
		] );
	}

	/**
	 * Whether the post is an item.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	protected function is_item_post( $post_id ) {
		$post = get_post( $post_id );

		return is_object( $post )
			&& ! empty( $post->post_type )
			&& CP_LIBRARY_UPREFIX . '_item' === $post->post_type;
	}

	/**
	 * Positive integer post ID from the import request.
	 *
	 * @return int
	 */
	protected function requested_post_id() {
		if ( ! isset( $_REQUEST['post_id'] ) || is_array( $_REQUEST['post_id'] ) || is_object( $_REQUEST['post_id'] ) ) {
			return 0;
		}

		$raw = trim( (string) wp_unslash( (string) $_REQUEST['post_id'] ) );

		if ( '' === $raw || ! ctype_digit( $raw ) ) {
			return 0;
		}

		return absint( $raw );
	}

	/**
	 * Nonce value from the import request.
	 *
	 * @return string
	 */
	protected function requested_nonce() {
		if ( ! isset( $_REQUEST['_wpnonce'] ) || is_array( $_REQUEST['_wpnonce'] ) || is_object( $_REQUEST['_wpnonce'] ) ) {
			return '';
		}

		return sanitize_text_field( (string) wp_unslash( (string) $_REQUEST['_wpnonce'] ) );
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

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'forbidden', __( 'You cannot edit this item.', 'cp-library' ) );
		}

		try {
			$item = new Item( $post_id );
		} catch ( \ChurchPlugins\Exception $e ) {
			return new \WP_Error( 'item_error', $e->getMessage() );
		}

		$transcript = get_post_meta( $post_id, 'transcript', true );

		if ( $transcript ) {
			return new \WP_Error( 'transcript_exists', 'Transcript already exists for this post. Please delete existing transcript before running import.' );
		}

		$video_url = $item->model->get_meta_value( 'video_url' );

		if ( ! $video_url ) {
			return new \WP_Error( 'no_video_url', 'No video URL found for this post' );
		}

		$video_id = self::parse_video_id( $video_url );

		if ( ! $video_id ) {
			if ( false === strpos( $video_url, 'youtube.com' ) && false === strpos( $video_url, 'youtu.be' ) ) {
				return new \WP_Error( 'not_youtube_video', 'Video URL is not a YouTube video' );
			}

			return new \WP_Error( 'no_video_id', 'Could not detect video ID from URL' );
		}

		// fetch transcript
		$transcript = $this->fetch_youtube_transcript( $video_id );

		if ( is_wp_error( $transcript ) ) {
			return $transcript;
		}

		/**
		 * Transcript data
		 *
		 * @var array  $transcript Transcript data.
		 * @var int    $post_id    Post ID.
		 * @var string $video_url Video URL.
		 * @return array|\WP_Error
		 */
		$transcript = apply_filters( 'cpl_fetch_transcript', $transcript, $post_id, $video_url );

		// update post meta
		$raw_text = implode( ' ', array_map( function( $item ) {
			return '(t:' . floor( $item['offset'] ) . ') ' . $item['text'];
		}, $transcript ) );

		// create a string of timestamp & character count groups
		$char_groups = '';
		foreach ( $transcript as $item ) {
			$char_groups .= floor( $item['offset'] ) . 's' . strlen( $item['text'] ) . 'c';
		}

		update_post_meta( $post_id, 'transcript', $raw_text );

		/**
		 * Fires after a transcript has been imported
		 *
		 * @param string $raw_text Raw text of the transcript.
		 * @param int    $post_id  Post ID.
		 * @return void
		 */
		do_action( 'cpl_imported_transcript', $raw_text, $post_id );

		return true;
	}

	/**
	 * Fetch a YouTube video transcript.
	 *
	 * Caption URLs scraped from the watch page now include exp=xpe and return
	 * HTTP 200 with an empty body. The ANDROID InnerTube player response still
	 * carries captionTracks whose baseUrl a plain GET can read. The client
	 * context is filterable so clientVersion can change without a release.
	 *
	 * @param string $video_id YouTube video ID.
	 * @return array|\WP_Error
	 */
	public function fetch_youtube_transcript( $video_id ) {
		if ( ! preg_match( '/^[A-Za-z0-9_-]{11}$/', (string) $video_id ) ) {
			return new \WP_Error( 'no_video_id', 'Could not detect video ID from URL' );
		}

		$request = wp_remote_post(
			self::player_endpoint(),
			[
				'timeout' => 15,
				'headers' => [
					'Content-Type'    => 'application/json',
					'Accept-Language' => 'en-US',
				],
				'body'    => wp_json_encode( self::player_request_body( $video_id ) ),
			]
		);

		if ( is_wp_error( $request ) ) {
			return new \WP_Error( 'youtube_captions_empty', self::captions_unavailable_message() );
		}

		$status_code = (int) wp_remote_retrieve_response_code( $request );
		$body        = wp_remote_retrieve_body( $request );

		if ( 200 !== $status_code || ! is_string( $body ) || '' === trim( $body ) ) {
			return new \WP_Error( 'youtube_captions_empty', self::captions_unavailable_message() );
		}

		$player = json_decode( $body, true );
		$urls   = self::caption_track_urls( $player );

		if ( ! $urls ) {
			return new \WP_Error( 'youtube_captions_empty', self::captions_unavailable_message() );
		}

		foreach ( $urls as $transcript_url ) {
			// srv3 XML is not the <text start dur> shape this parser reads.
			// Dropping that parameter asks timedtext for the legacy caption XML.
			$transcript_url = self::caption_fetch_url( $transcript_url );

			if ( ! self::is_youtube_caption_url( $transcript_url ) ) {
				continue;
			}

			$transcript_request = wp_remote_get(
				$transcript_url,
				[
					'timeout'            => 15,
					'reject_unsafe_urls' => true,
				]
			);

			if ( is_wp_error( $transcript_request ) ) {
				continue;
			}

			$transcript_status = (int) wp_remote_retrieve_response_code( $transcript_request );
			$transcript        = wp_remote_retrieve_body( $transcript_request );

			if ( 200 !== $transcript_status || ! is_string( $transcript ) || '' === trim( $transcript ) ) {
				continue;
			}

			$output = self::parse_caption_xml( $transcript );

			if ( ! $output ) {
				continue;
			}

			/**
			 * Array of YouTube transcript data.
			 *
			 * @var array  $output   Array of YouTube transcript data.
			 * @var string $video_id YouTube video ID.
			 * @return array|\WP_Error
			 */
			$output = apply_filters( 'cpl_fetch_youtube_transcript', $output, $video_id );

			if ( is_wp_error( $output ) ) {
				return $output;
			}

			if ( ! is_array( $output ) || ! $output ) {
				continue;
			}

			return $output;
		}

		return new \WP_Error( 'youtube_captions_empty', self::captions_unavailable_message() );
	}

	/**
	 * Player endpoint used to list caption tracks.
	 *
	 * @return string
	 */
	public static function player_endpoint() {
		return self::PLAYER_ENDPOINT;
	}

	/**
	 * JSON body posted to the InnerTube player endpoint.
	 *
	 * @param string $video_id YouTube video ID.
	 * @return array
	 */
	public static function player_request_body( $video_id ) {
		return [
			'context' => [
				'client' => self::innertube_client( $video_id ),
			],
			'videoId' => (string) $video_id,
		];
	}

	/**
	 * ANDROID client context sent with the player request.
	 *
	 * Filter `cpl_youtube_innertube_client` to change clientName or
	 * clientVersion when YouTube rotates the client, without shipping a release.
	 * Return an array with clientName and clientVersion. Anything else is ignored
	 * and the built-in ANDROID client is used.
	 *
	 * @param string $video_id YouTube video ID.
	 * @return array{clientName: string, clientVersion: string}
	 */
	public static function innertube_client( $video_id = '' ) {
		$client = [
			'clientName'    => 'ANDROID',
			// Replace 20.10.38 when YouTube retires that client, or return clientVersion from the cpl_youtube_innertube_client filter.
			'clientVersion' => '20.10.38',
		];

		$filtered = apply_filters( 'cpl_youtube_innertube_client', $client, $video_id );

		if ( is_array( $filtered ) && ! empty( $filtered['clientName'] ) && ! empty( $filtered['clientVersion'] ) ) {
			$client = $filtered;
			$client['clientName'] = (string) $filtered['clientName'];
			$client['clientVersion'] = (string) $filtered['clientVersion'];
		}

		return $client;
	}

	/**
	 * Caption track URLs from an InnerTube player response, in response order.
	 *
	 * Tracks whose baseUrl contains exp=xpe are kept. That parameter marks the
	 * watch-page URLs that come back empty, but the player endpoint's URLs are
	 * still worth fetching; an empty body is handled by the caller.
	 *
	 * @param mixed $player Decoded player response.
	 * @return string[]
	 */
	public static function caption_track_urls( $player ) {
		if ( ! is_array( $player ) ) {
			return [];
		}

		$tracks = $player['captions']['playerCaptionsTracklistRenderer']['captionTracks'] ?? null;

		if ( ! is_array( $tracks ) ) {
			return [];
		}

		$urls = [];

		foreach ( $tracks as $track ) {
			if ( ! is_array( $track ) || empty( $track['baseUrl'] ) || ! is_string( $track['baseUrl'] ) ) {
				continue;
			}

			$urls[] = $track['baseUrl'];
		}

		return $urls;
	}

	/**
	 * Caption URL to GET.
	 *
	 * Timedtext URLs that ask for srv3 return a different XML shape than the
	 * `<text start dur>` documents the importer already parses. Removing that
	 * parameter keeps the existing parser.
	 *
	 * @param string $url Caption track baseUrl.
	 * @return string
	 */
	public static function caption_fetch_url( $url ) {
		return str_replace(
			[ '?fmt=srv3&', '?fmt=srv3', '&fmt=srv3' ],
			[ '?', '', '' ],
			(string) $url
		);
	}

	/**
	 * Whether a caption track URL points at YouTube.
	 *
	 * @param string $url Caption track baseUrl.
	 * @return bool
	 */
	public static function is_youtube_caption_url( $url ) {
		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['scheme'] ) ) {
			return false;
		}

		if ( 'https' !== strtolower( $parts['scheme'] ) ) {
			return false;
		}

		$host = strtolower( $parts['host'] );
		$host = preg_replace( '/^www\./', '', $host );

		return in_array( $host, [ 'youtube.com', 'm.youtube.com', 'youtube-nocookie.com' ], true );
	}

	/**
	 * Parse a timedtext caption XML body into offset/duration/text rows.
	 *
	 * @param string $transcript Raw caption XML.
	 * @return array<int, array{text: string, duration: string, offset: string}>
	 */
	public static function parse_caption_xml( $transcript ) {
		$pattern = '/<text start="([^"]*)" dur="([^"]*)">([^<]*)<\/text>/';

		preg_match_all( $pattern, (string) $transcript, $results );

		$count  = count( $results[0] );
		$output = [];

		for ( $i = 0; $i < $count; $i++ ) {
			$text = $results[3][ $i ];

			if ( '' === trim( html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) ) ) {
				continue;
			}

			$output[] = [
				'text'     => $text,
				'duration' => $results[2][ $i ],
				'offset'   => $results[1][ $i ],
			];
		}

		return $output;
	}

	/**
	 * Message shown when YouTube does not return caption text.
	 *
	 * @return string
	 */
	public static function captions_unavailable_message() {
		return __( 'YouTube did not return a caption file for this video. Upload a .vtt or .srt caption file, or paste the transcript into the Transcript field.', 'cp-library' );
	}

	/**
	 * Read a YouTube video id from a watch, share, live, embed, or shorts URL.
	 *
	 * Also accepts an embed snippet whose src is one of those URLs. Extra query
	 * arguments such as si and t are ignored.
	 *
	 * @param string $video_url Video URL or embed HTML.
	 * @return string 11-character video id, or an empty string.
	 */
	public static function parse_video_id( $video_url ) {
		$video_url = trim( html_entity_decode( (string) $video_url, ENT_QUOTES, 'UTF-8' ) );

		if ( '' === $video_url ) {
			return '';
		}

		$id = self::video_id_from_url( $video_url );

		if ( '' !== $id ) {
			return $id;
		}

		if ( preg_match_all( '#(?:https?:)?//[^\s"\'<>]+#i', $video_url, $matches ) ) {
			foreach ( $matches[0] as $url ) {
				$id = self::video_id_from_url( $url );

				if ( '' !== $id ) {
					return $id;
				}
			}
		}

		return '';
	}

	/**
	 * Video id from a single URL.
	 *
	 * @param string $video_url URL.
	 * @return string
	 */
	protected static function video_id_from_url( $video_url ) {
		$video_url = self::ensure_url_scheme( $video_url );
		$parts     = wp_parse_url( $video_url );

		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}

		$host = strtolower( $parts['host'] );
		$host = preg_replace( '/^www\./', '', $host );
		$path = isset( $parts['path'] ) ? $parts['path'] : '';

		if ( 'youtu.be' === $host ) {
			$segments = explode( '/', trim( $path, '/' ) );

			return self::normalize_video_id( $segments[0] ?? '' );
		}

		$youtube_hosts = [
			'youtube.com',
			'm.youtube.com',
			'music.youtube.com',
			'youtube-nocookie.com',
		];

		if ( ! in_array( $host, $youtube_hosts, true ) ) {
			return '';
		}

		if ( ! empty( $parts['query'] ) ) {
			$query = [];
			parse_str( $parts['query'], $query );

			if ( isset( $query['v'] ) && is_string( $query['v'] ) ) {
				return self::normalize_video_id( $query['v'] );
			}
		}

		if ( preg_match( '~^/(?:embed|live|shorts)/([^/?#]+)~i', $path, $matches ) ) {
			return self::normalize_video_id( rawurldecode( $matches[1] ) );
		}

		return '';
	}

	/**
	 * Give a scheme to protocol-relative and bare YouTube URLs.
	 *
	 * @param string $video_url URL or URL-like string.
	 * @return string
	 */
	protected static function ensure_url_scheme( $video_url ) {
		if ( 0 === strpos( $video_url, '//' ) ) {
			return 'https:' . $video_url;
		}

		if ( preg_match( '#^[a-z][a-z0-9+.-]*:#i', $video_url ) ) {
			return $video_url;
		}

		if ( preg_match( '#^(?:www\.)?(?:youtube\.com|youtu\.be|m\.youtube\.com|music\.youtube\.com|youtube-nocookie\.com)/#i', $video_url ) ) {
			return 'https://' . $video_url;
		}

		return $video_url;
	}

	/**
	 * Keep an id only when it is a YouTube video id.
	 *
	 * @param string $id Candidate id.
	 * @return string
	 */
	protected static function normalize_video_id( $id ) {
		$id = trim( rawurldecode( (string) $id ) );
		$pieces = preg_split( '/[?&#\s]/', $id );
		$id = is_array( $pieces ) ? (string) $pieces[0] : '';

		if ( preg_match( '/^[A-Za-z0-9_-]{11}$/', $id ) ) {
			return $id;
		}

		return '';
	}

	/**
	 * Convert a WebVTT or SubRip caption file to plain transcript text.
	 *
	 * Timestamps, the line directly before a timing line, file headers, and cue tags are removed.
	 *
	 * @param string $raw Caption file contents.
	 * @return string
	 */
	public static function captions_to_text( $raw ) {
		$raw = (string) $raw;

		if ( 0 === strncmp( $raw, "\xEF\xBB\xBF", 3 ) ) {
			$raw = substr( $raw, 3 );
		}

		$raw   = str_replace( [ "\r\n", "\r" ], "\n", $raw );
		$lines = explode( "\n", $raw );
		$count = count( $lines );
		$texts = [];
		$skip  = false;

		for ( $i = 0; $i < $count; $i++ ) {
			$line = trim( $lines[ $i ] );

			if ( '' === $line ) {
				$skip = false;
				continue;
			}

			if ( preg_match( '/^(NOTE|STYLE|REGION)\b/i', $line ) ) {
				$skip = true;
				continue;
			}

			if ( $skip ) {
				continue;
			}

			if ( preg_match( '/^WEBVTT\b/i', $line ) || self::is_caption_metadata_line( $line ) || self::is_timestamp_line( $line ) ) {
				continue;
			}

			// Cue identifiers are not always numbers (`intro-2`). The identifier is
			// the line immediately above the timing line. A blank line means the
			// previous line is spoken text, not an identifier.
			$next = ( $i + 1 < $count ) ? trim( $lines[ $i + 1 ] ) : '';

			if ( self::is_timestamp_line( $next ) ) {
				continue;
			}

			$text = self::strip_caption_markup( $line );

			if ( '' !== $text ) {
				$texts[] = $text;
			}
		}

		return trim( implode( ' ', $texts ) );
	}

	/**
	 * Whether a line is a WebVTT header field rather than spoken text.
	 *
	 * @param string $line Trimmed line.
	 * @return bool
	 */
	protected static function is_caption_metadata_line( $line ) {
		return (bool) preg_match( '/^(?:Kind|Language|X-TIMESTAMP-MAP)\s*:/i', $line );
	}

	/**
	 * Whether a line is a VTT or SRT timing line.
	 *
	 * @param string $line Trimmed line.
	 * @return bool
	 */
	protected static function is_timestamp_line( $line ) {
		return (bool) preg_match(
			'/(?:(?:\d{1,3}:)?\d{1,2}:\d{2}(?:[.,]\d{1,3})?)\s*-->\s*(?:(?:\d{1,3}:)?\d{1,2}:\d{2}(?:[.,]\d{1,3})?)/',
			$line
		);
	}

	/**
	 * Remove cue tags and collapse whitespace.
	 *
	 * @param string $line Caption text line.
	 * @return string
	 */
	protected static function strip_caption_markup( $line ) {
		$line = html_entity_decode( (string) $line, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$line = preg_replace( '/\{[^}]*\}/', '', $line );

		if ( ! is_string( $line ) ) {
			return '';
		}

		$line = wp_strip_all_tags( $line );
		$line = self::strip_unclosed_markup( $line );
		$line = preg_replace( '/\s+/', ' ', $line );

		return is_string( $line ) ? trim( $line ) : '';
	}

	/**
	 * Remove a '<' that starts a tag but never reaches a closing '>'.
	 *
	 * Called after wp_strip_all_tags(), which still leaves some of those fragments.
	 *
	 * @param string $text Text that may contain an unclosed tag.
	 * @return string
	 */
	public static function strip_unclosed_markup( $text ) {
		$text = preg_replace( '/<[A-Za-z\/!?][^>\r\n]*(?=\r|\n|$)/', '', (string) $text );

		return is_string( $text ) ? $text : '';
	}

	/**
	 * Sanitize transcript HTML before it is printed.
	 *
	 * Closed tags are left for wp_kses_post(). An unclosed '<' fragment is removed
	 * first, because kses does not treat it as a tag.
	 *
	 * @param string $transcript Stored transcript.
	 * @return string
	 */
	public static function escape_transcript( $transcript ) {
		$transcript = self::strip_unclosed_markup( $transcript );

		return wp_kses_post( $transcript );
	}

	/**
	 * Renders a button for importing a transcript.
	 *
	 * @param CMB2_Field $field
	 * @param mixed $escaped_value
	 * @param int $object_id
	 * @since 1.5.0
	 * @return void
	 */
	public function display_import_transcript_button( $field, $escaped_value, $object_id ) {
		$video_url = get_post_meta( $object_id, 'video_url', true );

		if ( ! $video_url || '' === self::parse_video_id( $video_url ) ) {
			return;
		}

		$unavailable = self::captions_unavailable_message();
		?>
		<script>
			jQuery($ => {
				const setTranscript = (text) => {
					const editor = window.tinymce && tinymce.get('transcript');

					if (editor) {
						editor.setContent(text);
					}

					$('#transcript, #wp-transcript-wrap .wp-editor-area').val(text);
				};

				const showImportError = (message) => {
					const text = message || <?php echo wp_json_encode( $unavailable ); ?>;
					let $notice = $('#cpl-transcript-import-notice');

					if (!$notice.length) {
						$notice = $('<div id="cpl-transcript-import-notice" class="notice notice-error inline"><p></p></div>');
						$('#cpl-import-transcript').before($notice);
					}

					$notice.find('p').text(text);
					window.alert(text);
				};

				$('#cpl-import-transcript').on('click', function() {
					const $button = $(this);
					const url = $button.data('url');

					$button.prop('disabled', true);

					$.post(url).done(function(response) {
						if (response && response.success) {
							$('#cpl-transcript-import-notice').remove();
							setTranscript(response.data && response.data.transcript ? response.data.transcript : '');
							return;
						}

						showImportError(response && response.data);
					}).fail(function() {
						showImportError(<?php echo wp_json_encode( $unavailable ); ?>);
					}).always(function() {
						$button.prop('disabled', false);
					});
				});
			});
		</script>

		<?php $import_url = $this->get_import_request_url( $object_id ); ?>

		<p class="description"><?php esc_html_e( 'If YouTube does not return captions, upload a .vtt or .srt file or paste the transcript.', 'cp-library' ); ?></p>
		<button type="button" id="cpl-import-transcript" data-url="<?php echo esc_url( $import_url ); ?>" class="button cpl-import-transcript-btn"><?php echo \ChurchPlugins\Helpers::get_icon( 'youtube' ) . esc_html__( 'Import from YouTube', 'cp-library' ); ?></button>

		<?php
	}

	/**
	 * File input for a .vtt or .srt caption file.
	 *
	 * @param object $field      CMB2 field.
	 * @param mixed  $escaped_value Escaped value.
	 * @param int    $object_id  Post ID.
	 * @return void
	 */
	public function display_caption_upload( $field, $escaped_value, $object_id ) {
		$object_id = absint( $object_id );
		$nonce     = wp_create_nonce( 'cpl_upload_transcript_caption_' . $object_id );
		?>
		<p>
			<label for="cpl_transcript_caption"><strong><?php esc_html_e( 'Upload a .vtt or .srt caption file', 'cp-library' ); ?></strong></label><br />
			<input type="file" name="cpl_transcript_caption" id="cpl_transcript_caption" accept=".vtt,.srt,text/vtt" />
		</p>
		<p class="description"><?php esc_html_e( 'Timestamps, cue identifiers, and tags are removed. The file is saved when it finishes uploading. Clicking Update also saves a file that is still selected. You can paste the transcript into the field above.', 'cp-library' ); ?></p>
		<div id="cpl-caption-upload-notice" class="notice inline" style="display:none;"><p></p></div>
		<script>
			jQuery($ => {
				const setTranscript = (text) => {
					const editor = window.tinymce && tinymce.get('transcript');
					const html = String(text).replace(/[&<>]/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[ch]));

					if (editor) {
						editor.setContent(html);
					}

					$('#transcript, #wp-transcript-wrap .wp-editor-area').val(text);
				};

				const showCaptionNotice = (message, isError) => {
					const $notice = $('#cpl-caption-upload-notice');
					$notice.removeClass('notice-error notice-success').addClass(isError ? 'notice-error' : 'notice-success');
					$notice.find('p').text(message);
					$notice.show();
				};

				$('#cpl_transcript_caption').on('change', function() {
					const file = this.files && this.files[0];

					if (!file) {
						return;
					}

					const data = new FormData();
					data.append('action', 'cpl_upload_transcript_caption');
					data.append('post_id', <?php echo wp_json_encode( (string) $object_id ); ?>);
					data.append('nonce', <?php echo wp_json_encode( $nonce ); ?>);
					data.append('caption', file);

					showCaptionNotice(<?php echo wp_json_encode( __( 'Reading caption file...', 'cp-library' ) ); ?>, false);

					$.ajax({
						url: window.ajaxurl,
						method: 'POST',
						data: data,
						processData: false,
						contentType: false
					}).done(function(response) {
						if (response && response.success && response.data && response.data.transcript) {
							setTranscript(response.data.transcript);
							$('#cpl_transcript_caption').val('');
							showCaptionNotice(<?php echo wp_json_encode( __( 'Caption file added to the transcript.', 'cp-library' ) ); ?>, false);
							return;
						}

						const message = response && response.data ? response.data : <?php echo wp_json_encode( __( 'That caption file could not be read. Upload a .vtt or .srt file, or paste the transcript into the Transcript field.', 'cp-library' ) ); ?>;
						showCaptionNotice(message, true);
					}).fail(function() {
						showCaptionNotice(<?php echo wp_json_encode( __( 'That caption file could not be read. Upload a .vtt or .srt file, or paste the transcript into the Transcript field.', 'cp-library' ) ); ?>, true);
					});
				});
			});
		</script>
		<?php
	}

	/**
	 * AJAX handler for a caption file chosen on the sermon screen.
	 *
	 * @return void
	 */
	public function handle_caption_upload() {
		$post_id = absint( $_POST['post_id'] ?? 0 );

		check_ajax_referer( 'cpl_upload_transcript_caption_' . $post_id, 'nonce' );

		if ( $post_id ) {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				wp_send_json_error( __( 'You cannot edit this transcript.', 'cp-library' ) );
			}
		} elseif ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'You cannot edit this transcript.', 'cp-library' ) );
		}

		$result = self::caption_text_from_upload( $_FILES['caption'] ?? [] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		if ( $post_id ) {
			update_post_meta( $post_id, 'transcript', $result );
		}

		wp_send_json_success( [
			'transcript' => $result,
		] );
	}

	/**
	 * Save a caption file submitted with the sermon form.
	 *
	 * Runs after CMB2 so the converted text replaces whatever the transcript
	 * editor posted on this save.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_caption_upload( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( get_post_type( $post_id ) !== CP_LIBRARY_UPREFIX . '_item' ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( empty( $_FILES['cpl_transcript_caption'] ) || ! is_array( $_FILES['cpl_transcript_caption'] ) ) {
			return;
		}

		$file = $_FILES['cpl_transcript_caption'];

		if ( ! isset( $file['error'] ) || UPLOAD_ERR_NO_FILE === (int) $file['error'] ) {
			return;
		}

		$result = self::caption_text_from_upload( $file );

		if ( is_wp_error( $result ) ) {
			$this->caption_upload_result = $result->get_error_code();
			return;
		}

		update_post_meta( $post_id, 'transcript', $result );
	}

	/**
	 * Add multipart encoding on the sermon edit form.
	 *
	 * CMB2 file fields use the media library, not a native file input, so this
	 * does not change how those fields post. Limited to sermons so other edit
	 * screens keep the default encoding.
	 *
	 * @return void
	 */
	public function add_caption_form_enctype() {
		$screen = get_current_screen();

		if ( ! $screen || CP_LIBRARY_UPREFIX . '_item' !== $screen->post_type ) {
			return;
		}

		echo ' enctype="multipart/form-data"';
	}

	/**
	 * Carry a caption-upload error onto the sermon edit screen.
	 *
	 * @param string $location Redirect URL.
	 * @return string
	 */
	public function filter_caption_upload_redirect( $location ) {
		if ( $this->caption_upload_result ) {
			$location = add_query_arg( 'cpl_caption_upload', $this->caption_upload_result, $location );
		}

		return $location;
	}

	/**
	 * Turn an uploaded caption file into transcript text.
	 *
	 * @param array $file A $_FILES entry.
	 * @return string|\WP_Error
	 */
	public static function caption_text_from_upload( $file ) {
		if ( ! is_array( $file ) ) {
			return new \WP_Error( 'failed', __( 'That caption file could not be read. Upload a .vtt or .srt file, or paste the transcript into the Transcript field.', 'cp-library' ) );
		}

		if ( isset( $file['error'] ) && UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new \WP_Error( 'failed', __( 'That caption file could not be read. Upload a .vtt or .srt file, or paste the transcript into the Transcript field.', 'cp-library' ) );
		}

		$name = isset( $file['name'] ) ? (string) $file['name'] : '';
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, [ 'vtt', 'srt' ], true ) ) {
			return new \WP_Error( 'type', __( 'Upload a .vtt or .srt caption file, or paste the transcript into the Transcript field.', 'cp-library' ) );
		}

		$tmp = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';

		if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			return new \WP_Error( 'failed', __( 'That caption file could not be read. Upload a .vtt or .srt file, or paste the transcript into the Transcript field.', 'cp-library' ) );
		}

		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;

		if ( $size < 1 || $size > 2 * 1024 * 1024 ) {
			return new \WP_Error( 'failed', __( 'That caption file could not be read. Upload a .vtt or .srt file, or paste the transcript into the Transcript field.', 'cp-library' ) );
		}

		$raw = file_get_contents( $tmp );

		if ( ! is_string( $raw ) || false !== strpos( $raw, "\0" ) ) {
			return new \WP_Error( 'failed', __( 'That caption file could not be read. Upload a .vtt or .srt file, or paste the transcript into the Transcript field.', 'cp-library' ) );
		}

		$text = self::captions_to_text( $raw );

		if ( '' === $text ) {
			return new \WP_Error( 'empty', __( 'That caption file did not contain any text. Upload a different .vtt or .srt file, or paste the transcript into the Transcript field.', 'cp-library' ) );
		}

		return $text;
	}

	/**
	 * Admin notices for a failed YouTube import or a rejected caption file.
	 *
	 * @return void
	 */
	public function render_admin_notices() {
		if ( ! is_admin() ) {
			return;
		}

		if ( isset( $_GET['cpl_import_transcript'] ) ) {
			$count = absint( wp_unslash( $_GET['cpl_import_transcript'] ) );

			if ( $count ) {
				echo '<div class="notice notice-success is-dismissible"><p>';
				echo esc_html( sprintf(
					/* translators: %d: number of sermons */
					_n( 'Imported %d transcript from YouTube.', 'Imported %d transcripts from YouTube.', $count, 'cp-library' ),
					$count
				) );
				echo '</p></div>';
			}
		}

		if ( ! empty( $_GET['cpl_transcript_import_failed'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( self::captions_unavailable_message() ) . '</p></div>';
		}

		if ( empty( $_GET['cpl_caption_upload'] ) ) {
			return;
		}

		$code = sanitize_key( wp_unslash( $_GET['cpl_caption_upload'] ) );

		if ( 'type' === $code ) {
			$message = __( 'Upload a .vtt or .srt caption file, or paste the transcript into the Transcript field.', 'cp-library' );
		} elseif ( 'empty' === $code ) {
			$message = __( 'That caption file did not contain any text. Upload a different .vtt or .srt file, or paste the transcript into the Transcript field.', 'cp-library' );
		} elseif ( 'failed' === $code ) {
			$message = __( 'That caption file could not be read. Upload a .vtt or .srt file, or paste the transcript into the Transcript field.', 'cp-library' );
		} else {
			return;
		}

		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
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

		// The list screen requires edit_posts. Each target must be an item the user can edit.
		if ( ! current_user_can( 'edit_posts' ) || ! is_array( $post_ids ) ) {
			return $redirect_to;
		}

		$successful_transcripts = 0;
		$failed_transcripts     = 0;

		foreach ( $post_ids as $post_id ) {
			if ( is_array( $post_id ) || is_object( $post_id ) ) {
				continue;
			}

			$post_id = absint( $post_id );

			if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) || ! $this->is_item_post( $post_id ) ) {
				continue;
			}

			$success = $this->import_transcript( $post_id );

			if ( true === $success ) {
				$successful_transcripts++;
			} elseif ( is_wp_error( $success ) && 'youtube_captions_empty' === $success->get_error_code() ) {
				$failed_transcripts++;
			}
		}

		if ( $successful_transcripts ) {
			$redirect_to = add_query_arg( 'cpl_import_transcript', $successful_transcripts, $redirect_to );
		}

		if ( $failed_transcripts ) {
			$redirect_to = add_query_arg( 'cpl_transcript_import_failed', $failed_transcripts, $redirect_to );
		}

		return $redirect_to;
	}
}
