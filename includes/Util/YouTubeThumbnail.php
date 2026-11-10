<?php

namespace CP_Library\Util;

use CP_Library\Integrations\YouTube;

/**
 * Display-time YouTube thumbnail URLs.
 *
 * Nothing here writes a featured image, attachment, or sermon meta. The only
 * write is a transient that remembers whether maxresdefault.jpg exists for a
 * video id, so later page views do not ask YouTube again.
 */
class YouTubeThumbnail {

	/**
	 * Transient lifetime. Video thumbnails rarely change.
	 */
	const CACHE_TTL = 2592000;

	/**
	 * YouTube's missing maxresdefault.jpg is a 120x90 JPEG of about 1097 bytes.
	 * A real max-resolution frame is much larger. Anything under this size is
	 * the placeholder.
	 */
	const MIN_REAL_BYTES = 2000;

	/**
	 * Video id from the sermon's video URL, or from the optional YouTube field
	 * when the video URL is empty.
	 *
	 * A non-YouTube video URL is the sermon's video, so a leftover YouTube
	 * field is ignored.
	 *
	 * @param string $video_url      video_url meta.
	 * @param string $youtube_field  video_id_youtube meta, a URL or a bare id.
	 * @return string 11-character id, or an empty string.
	 */
	public static function id_from_sources( $video_url, $youtube_field = '' ) {
		$video_url = trim( (string) $video_url );
		$from_url  = YouTube::parse_video_id( $video_url );

		if ( '' !== $from_url ) {
			return $from_url;
		}

		if ( self::is_bare_id( $video_url ) ) {
			return $video_url;
		}

		if ( '' !== $video_url && ! self::mentions_youtube( $video_url ) ) {
			return '';
		}

		$field = trim( (string) $youtube_field );

		if ( '' === $field ) {
			return '';
		}

		$from_field = YouTube::parse_video_id( $field );

		if ( '' !== $from_field ) {
			return $from_field;
		}

		if ( self::is_bare_id( $field ) ) {
			return $field;
		}

		return '';
	}

	/**
	 * Thumbnail URL for a video id, choosing maxresdefault or hqdefault.
	 *
	 * hqdefault.jpg exists for every video. maxresdefault.jpg does not, and
	 * when it is missing YouTube still returns a tiny placeholder image.
	 *
	 * @param string $video_id Video id.
	 * @return string
	 */
	public static function url_for( $video_id ) {
		$video_id = self::sanitize_id( $video_id );

		if ( '' === $video_id ) {
			return '';
		}

		$file = self::has_maxres( $video_id ) ? 'maxresdefault.jpg' : 'hqdefault.jpg';

		return self::image_url( $video_id, $file );
	}

	/**
	 * Build an img.youtube.com thumbnail URL. The id is already sanitized.
	 *
	 * @param string $video_id Video id.
	 * @param string $file     maxresdefault.jpg or hqdefault.jpg.
	 * @return string
	 */
	public static function image_url( $video_id, $file ) {
		$video_id = self::sanitize_id( $video_id );

		if ( '' === $video_id ) {
			return '';
		}

		if ( 'maxresdefault.jpg' !== $file ) {
			$file = 'hqdefault.jpg';
		}

		return 'https://img.youtube.com/vi/' . $video_id . '/' . $file;
	}

	/**
	 * Whether the site should use a YouTube thumbnail for this request.
	 *
	 * Off unless the setting is on. The podcast feed and wp-admin are excluded.
	 * wp-admin includes the sermon CSV export, which would otherwise save the
	 * remote image as a featured image on import, and it avoids a YouTube
	 * request while rendering admin screens.
	 *
	 * @param bool   $enabled  Setting value.
	 * @param string $context  'podcast' skips the fallback. Other values are ignored.
	 * @param bool   $is_feed  True while a feed, including the podcast feed, renders.
	 * @param bool   $is_admin True on wp-admin requests.
	 * @return bool
	 */
	public static function fallback_allowed( $enabled, $context, $is_feed, $is_admin ) {
		if ( ! $enabled ) {
			return false;
		}

		if ( 'podcast' === $context ) {
			return false;
		}

		if ( $is_feed ) {
			return false;
		}

		if ( $is_admin ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether maxresdefault.jpg is a real image for this video.
	 *
	 * A transient stores '1' or '0'. Admin requests do not probe; they use
	 * hqdefault when a caller asks for a URL directly, and the sermon
	 * thumbnail skips YouTube in wp-admin entirely.
	 *
	 * @param string $video_id Video id.
	 * @return bool
	 */
	public static function has_maxres( $video_id ) {
		$video_id = self::sanitize_id( $video_id );

		if ( '' === $video_id ) {
			return false;
		}

		$key    = self::transient_key( $video_id );
		$cached = get_transient( $key );

		if ( '1' === $cached || '0' === $cached ) {
			return '1' === $cached;
		}

		if ( ! self::probe_allowed() ) {
			return false;
		}

		$has = self::probe( $video_id );

		// null means the check itself failed. Use hqdefault for this request
		// and try again next time, instead of remembering a timeout for a month.
		if ( null === $has ) {
			return false;
		}

		set_transient( $key, $has ? '1' : '0', self::CACHE_TTL );

		return $has;
	}

	/**
	 * Decide from a normalized HTTP response whether maxres exists.
	 *
	 * @param array $response code, content_length (int|null), body.
	 * @return bool
	 */
	public static function response_has_maxres( array $response ) {
		if ( 200 !== (int) ( isset( $response['code'] ) ? $response['code'] : 0 ) ) {
			return false;
		}

		if ( isset( $response['content_length'] ) && is_numeric( $response['content_length'] ) ) {
			return (int) $response['content_length'] >= self::MIN_REAL_BYTES;
		}

		$body = isset( $response['body'] ) ? (string) $response['body'] : '';

		if ( '' === $body || strlen( $body ) < self::MIN_REAL_BYTES ) {
			return false;
		}

		if ( function_exists( 'getimagesizefromstring' ) ) {
			// Invalid bytes return false. The @ keeps a truncated image from warning.
			$info = @getimagesizefromstring( $body );

			if ( is_array( $info ) && isset( $info[0], $info[1] ) && (int) $info[0] <= 120 && (int) $info[1] <= 90 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether this request may contact img.youtube.com.
	 *
	 * @return bool
	 */
	public static function probe_allowed() {
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return false;
		}

		if ( function_exists( 'apply_filters' ) ) {
			return (bool) apply_filters( 'cpl_youtube_thumbnail_probe', true );
		}

		return true;
	}

	/**
	 * HEAD maxresdefault.jpg. A 200 without Content-Length falls back to a
	 * short GET so the 120x90 placeholder can still be recognized.
	 *
	 * @param string $video_id Sanitized video id.
	 * @return bool|null True when maxres exists, false when it is confirmed
	 *                   missing, null when the request failed.
	 */
	protected static function probe( $video_id ) {
		$url  = self::image_url( $video_id, 'maxresdefault.jpg' );
		$args = array(
			'timeout'     => 2,
			'redirection' => 3,
		);

		$head = wp_remote_head( $url, $args );

		if ( self::is_error( $head ) ) {
			return null;
		}

		$normalized     = self::normalize_http_response( $head );
		$missing_length = ( 200 === $normalized['code'] && null === $normalized['content_length'] && '' === $normalized['body'] );

		if ( $missing_length ) {
			$get_args                        = $args;
			$get_args['limit_response_size'] = 8192;
			$get                             = wp_remote_get( $url, $get_args );

			if ( self::is_error( $get ) ) {
				return null;
			}

			$normalized = self::normalize_http_response( $get );
		}

		if ( 404 === $normalized['code'] ) {
			return false;
		}

		if ( 200 !== $normalized['code'] ) {
			return null;
		}

		return self::response_has_maxres( $normalized );
	}

	/**
	 * Reduce a WordPress HTTP response to the fields the size check uses.
	 *
	 * @param mixed $response wp_remote_* response or WP_Error.
	 * @return array{code:int,content_length:int|null,body:string}
	 */
	public static function normalize_http_response( $response ) {
		$empty = array(
			'code'           => 0,
			'content_length' => null,
			'body'           => '',
		);

		if ( self::is_error( $response ) || ! is_array( $response ) ) {
			return $empty;
		}

		$code = 0;

		if ( isset( $response['response']['code'] ) ) {
			$code = (int) $response['response']['code'];
		}

		$length  = null;
		$headers = isset( $response['headers'] ) ? $response['headers'] : array();

		if ( is_array( $headers ) || $headers instanceof \Traversable ) {
			foreach ( $headers as $name => $value ) {
				if ( 'content-length' !== strtolower( (string) $name ) ) {
					continue;
				}

				if ( is_array( $value ) ) {
					$value = end( $value );
				}

				if ( is_numeric( $value ) ) {
					$length = (int) $value;
				}
			}
		}

		$body = isset( $response['body'] ) ? (string) $response['body'] : '';

		return array(
			'code'           => $code,
			'content_length' => $length,
			'body'           => $body,
		);
	}

	/**
	 * Markup for a thumbnail that is not a media-library attachment.
	 *
	 * The item graphic block renders attachments with wp_get_attachment_image().
	 * A YouTube URL has no attachment, so the block uses this instead.
	 *
	 * @param string $thumb_url       Thumbnail URL.
	 * @param string $attachment_html Markup already built for a local attachment.
	 * @param array  $attr            alt, class, and style.
	 * @return string
	 */
	public static function remote_image_html( $thumb_url, $attachment_html, array $attr ) {
		if ( is_string( $attachment_html ) && '' !== $attachment_html ) {
			return $attachment_html;
		}

		$src = esc_url( (string) $thumb_url, array( 'http', 'https' ) );

		if ( '' === $src ) {
			return '';
		}

		$class = isset( $attr['class'] ) ? $attr['class'] : '';
		$style = isset( $attr['style'] ) ? $attr['style'] : '';
		$alt   = isset( $attr['alt'] ) ? $attr['alt'] : '';

		$html = '<img src="' . $src . '" alt="' . esc_attr( $alt ) . '"';

		if ( '' !== (string) $class ) {
			$html .= ' class="' . esc_attr( $class ) . '"';
		}

		if ( '' !== (string) $style ) {
			$html .= ' style="' . esc_attr( $style ) . '"';
		}

		return $html . ' />';
	}

	/**
	 * @param string $video_id Video id.
	 * @return string
	 */
	protected static function transient_key( $video_id ) {
		return 'cpl_yt_maxres_' . $video_id;
	}

	/**
	 * @param string $id Candidate id.
	 * @return string
	 */
	protected static function sanitize_id( $id ) {
		$id = trim( (string) $id );

		if ( self::is_bare_id( $id ) ) {
			return $id;
		}

		return '';
	}

	/**
	 * @param string $id Candidate id.
	 * @return bool
	 */
	protected static function is_bare_id( $id ) {
		return 1 === preg_match( '/^[A-Za-z0-9_-]{11}$/', (string) $id );
	}

	/**
	 * @param string $value URL or embed HTML.
	 * @return bool
	 */
	protected static function mentions_youtube( $value ) {
		$value = strtolower( (string) $value );

		return false !== strpos( $value, 'youtube.com' )
			|| false !== strpos( $value, 'youtube-nocookie.com' )
			|| false !== strpos( $value, 'youtu.be' );
	}

	/**
	 * @param mixed $response HTTP response.
	 * @return bool
	 */
	protected static function is_error( $response ) {
		if ( $response instanceof \WP_Error ) {
			return true;
		}

		return function_exists( 'is_wp_error' ) && is_wp_error( $response );
	}
}
