<?php

namespace CP_Library\Util;

use CP_Library\Integrations\YouTube;

/**
 * Display-time YouTube thumbnail URLs.
 *
 * Nothing here writes a featured image, attachment, or sermon meta. The only
 * write is a transient that remembers, per video id, whether the thumbnail
 * exists. A confirmed max-resolution image is kept for 30 days. A confirmed
 * miss is kept for 7 days. A failed or timed-out check is kept for an hour
 * and the sermon shows hqdefault.jpg until that hour is up.
 */
class YouTubeThumbnail {

	/**
	 * Confirmed maxresdefault.jpg.
	 */
	const TTL_MAXRES = 2592000;

	/**
	 * Confirmed missing maxres, or both sizes missing.
	 */
	const TTL_MISSING = 604800;

	/**
	 * The check failed. Show hqdefault.jpg until this expires.
	 */
	const TTL_UNKNOWN = 3600;

	/**
	 * Remote checks started in one PHP request. Further videos use hqdefault
	 * without another request.
	 */
	const MAX_PROBES = 8;

	/**
	 * Transient prefix. The value is maxres, hq, none, or unknown.
	 */
	const TRANSIENT_PREFIX = 'cpl_yt_thumb_';

	/**
	 * YouTube's missing thumbnail is a 120x90 JPEG of about 1097 bytes.
	 * A real frame is much larger.
	 */
	const MIN_REAL_BYTES = 2000;

	/**
	 * Decision for each video id already resolved in this request.
	 *
	 * @var array<string,string>
	 */
	protected static $resolved = array();

	/**
	 * HTTP checks started in this request.
	 *
	 * @var int
	 */
	protected static $probes = 0;

	/**
	 * A timeout or transport error happened. Do not start more checks.
	 *
	 * @var bool
	 */
	protected static $stop_probing = false;

	/**
	 * Test seam. Null follows the WP_CLI constant.
	 *
	 * @var bool|null
	 */
	public static $force_cli = null;

	/**
	 * Clear the per-request probe memory. Transients are left alone.
	 */
	public static function reset_request_state() {
		self::$resolved      = array();
		self::$probes        = 0;
		self::$stop_probing  = false;
		self::$force_cli     = null;
	}

	/**
	 * Video id from the sermon's video URL, or from the optional YouTube field
	 * when the video URL is empty.
	 *
	 * video_url must be a YouTube URL or embed. A bare 11-character token in
	 * that field is not treated as a YouTube id: the field also holds file
	 * URLs and other providers, and an 11-character string is not identifiable
	 * on its own. The dedicated YouTube field does accept a bare id.
	 *
	 * A non-YouTube video URL is the sermon's video, so a leftover YouTube
	 * field is ignored.
	 *
	 * @param string $video_url     video_url meta.
	 * @param string $youtube_field video_id_youtube meta, a URL or a bare id.
	 * @return string 11-character id, or an empty string.
	 */
	public static function id_from_sources( $video_url, $youtube_field = '' ) {
		$video_url = trim( (string) $video_url );
		$from_url  = YouTube::parse_video_id( $video_url );

		if ( '' !== $from_url ) {
			return $from_url;
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
	 * Thumbnail URL for a video id, or an empty string when YouTube has no
	 * real image and the sermon should use the series image instead.
	 *
	 * A transport error returns hqdefault.jpg. WP-CLI, cron, and wp-admin
	 * screens do not send a request: a cached answer is used, and otherwise
	 * the YouTube fallback is skipped.
	 *
	 * @param string $video_id Video id.
	 * @return string
	 */
	public static function url_for( $video_id ) {
		$video_id = self::sanitize_id( $video_id );

		if ( '' === $video_id ) {
			return '';
		}

		if ( isset( self::$resolved[ $video_id ] ) ) {
			return self::url_for_decision( $video_id, self::$resolved[ $video_id ] );
		}

		$cached = self::cached_decision( $video_id );

		if ( null !== $cached ) {
			self::$resolved[ $video_id ] = $cached;

			return self::url_for_decision( $video_id, $cached );
		}

		if ( ! self::probe_allowed() ) {
			self::$resolved[ $video_id ] = 'skip';

			return '';
		}

		if ( self::probe_budget_spent() ) {
			self::$resolved[ $video_id ] = 'hq-unverified';

			return self::url_for_decision( $video_id, 'hq-unverified' );
		}

		$decision                    = self::decide( $video_id );
		self::$resolved[ $video_id ] = $decision;
		self::remember( $video_id, $decision );

		return self::url_for_decision( $video_id, $decision );
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
	 * Off unless the setting is on. The podcast feed and wp-admin screens are
	 * excluded. admin-ajax.php is a front-end filter request (is_admin() is
	 * true there) and is allowed. The CSV export is a normal admin POST, so
	 * it stays excluded.
	 *
	 * @param bool   $enabled  Setting value.
	 * @param string $context  'podcast' skips the fallback. Other values are ignored.
	 * @param bool   $is_feed  True while a feed, including the podcast feed, renders.
	 * @param bool   $is_admin True on wp-admin and admin-ajax requests.
	 * @param bool   $is_ajax  True for admin-ajax.php, including sermon filtering.
	 * @return bool
	 */
	public static function fallback_allowed( $enabled, $context, $is_feed, $is_admin, $is_ajax = false ) {
		if ( ! $enabled ) {
			return false;
		}

		if ( 'podcast' === $context ) {
			return false;
		}

		if ( $is_feed ) {
			return false;
		}

		if ( $is_admin && ! $is_ajax ) {
			return false;
		}

		return true;
	}

	/**
	 * Decide from a normalized HTTP response whether the file is a real image.
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
		if ( self::is_cli() || self::is_cron() ) {
			return false;
		}

		if ( function_exists( 'is_admin' ) && is_admin() ) {
			$ajax = function_exists( 'wp_doing_ajax' ) && wp_doing_ajax();

			if ( ! $ajax ) {
				return false;
			}
		}

		if ( function_exists( 'apply_filters' ) ) {
			return (bool) apply_filters( 'cpl_youtube_thumbnail_probe', true );
		}

		return true;
	}

	/**
	 * Markup for the YouTube thumbnail this feature produces.
	 *
	 * With the fallback off, or for any other URL (a Vimeo image, the bundled
	 * logo), an existing attachment is returned and otherwise nothing. That is
	 * how the item graphic block rendered before this feature.
	 *
	 * @param string $thumb_url                 Thumbnail URL.
	 * @param string $attachment_html           Markup already built for a local attachment.
	 * @param array  $attr                      alt, class, and style.
	 * @param bool   $youtube_fallback_enabled  The YouTube Thumbnail setting.
	 * @return string
	 */
	public static function remote_image_html( $thumb_url, $attachment_html, array $attr, $youtube_fallback_enabled = false ) {
		if ( is_string( $attachment_html ) && '' !== $attachment_html ) {
			return $attachment_html;
		}

		if ( ! $youtube_fallback_enabled ) {
			return '';
		}

		$file = self::youtube_thumbnail_file( (string) $thumb_url );

		if ( '' === $file ) {
			return '';
		}

		$src = esc_url( (string) $thumb_url, array( 'http', 'https' ) );

		if ( '' === $src ) {
			return '';
		}

		$size  = ( 'maxresdefault.jpg' === $file ) ? array( 1280, 720 ) : array( 480, 360 );
		$class = isset( $attr['class'] ) ? $attr['class'] : '';
		$style = isset( $attr['style'] ) ? $attr['style'] : '';
		$alt   = isset( $attr['alt'] ) ? $attr['alt'] : '';

		$html  = '<img src="' . $src . '" alt="' . esc_attr( $alt ) . '"';
		$html .= ' width="' . (int) $size[0] . '" height="' . (int) $size[1] . '" loading="lazy"';

		if ( '' !== (string) $class ) {
			$html .= ' class="' . esc_attr( $class ) . '"';
		}

		if ( '' !== (string) $style ) {
			$html .= ' style="' . esc_attr( $style ) . '"';
		}

		return $html . ' />';
	}

	/**
	 * maxres when it is real, otherwise hqdefault when that is real.
	 *
	 * @param string $video_id Sanitized video id.
	 * @return string maxres, hq, none, or unknown.
	 */
	protected static function decide( $video_id ) {
		$maxres = self::probe_image( $video_id, 'maxresdefault.jpg' );

		if ( null === $maxres ) {
			return 'unknown';
		}

		if ( true === $maxres ) {
			return 'maxres';
		}

		if ( self::probe_budget_spent() ) {
			return 'hq-unverified';
		}

		$hq = self::probe_image( $video_id, 'hqdefault.jpg' );

		if ( null === $hq ) {
			return 'unknown';
		}

		return $hq ? 'hq' : 'none';
	}

	/**
	 * HEAD one thumbnail file. A 200 without Content-Length falls back to a
	 * short GET so the 120x90 placeholder can still be recognized.
	 *
	 * @param string $video_id Sanitized video id.
	 * @param string $file     maxresdefault.jpg or hqdefault.jpg.
	 * @return bool|null True when the file is a real image, false when it is
	 *                   confirmed missing, null when the request failed.
	 */
	protected static function probe_image( $video_id, $file ) {
		self::$probes++;

		$url  = self::image_url( $video_id, $file );
		$args = array(
			'timeout'     => 2,
			'redirection' => 3,
		);

		$head = wp_remote_head( $url, $args );

		if ( self::is_error( $head ) ) {
			self::$stop_probing = true;

			return null;
		}

		$normalized     = self::normalize_http_response( $head );
		$missing_length = ( 200 === $normalized['code'] && null === $normalized['content_length'] && '' === $normalized['body'] );

		if ( $missing_length ) {
			self::$probes++;
			$get_args                        = $args;
			$get_args['limit_response_size'] = 8192;
			$get                             = wp_remote_get( $url, $get_args );

			if ( self::is_error( $get ) ) {
				self::$stop_probing = true;

				return null;
			}

			$normalized = self::normalize_http_response( $get );
		}

		if ( 404 === $normalized['code'] ) {
			return false;
		}

		if ( 200 !== $normalized['code'] ) {
			self::$stop_probing = true;

			return null;
		}

		return self::response_has_maxres( $normalized );
	}

	/**
	 * @param string $video_id Sanitized video id.
	 * @param string $decision Cached or freshly decided token.
	 * @return string
	 */
	protected static function url_for_decision( $video_id, $decision ) {
		if ( 'maxres' === $decision ) {
			return self::image_url( $video_id, 'maxresdefault.jpg' );
		}

		if ( 'hq' === $decision || 'unknown' === $decision || 'hq-unverified' === $decision ) {
			return self::image_url( $video_id, 'hqdefault.jpg' );
		}

		return '';
	}

	/**
	 * @param string $video_id Sanitized video id.
	 * @return string|null
	 */
	protected static function cached_decision( $video_id ) {
		$cached = get_transient( self::transient_key( $video_id ) );

		if ( 'maxres' === $cached || 'hq' === $cached || 'none' === $cached || 'unknown' === $cached ) {
			return $cached;
		}

		if ( '1' === $cached ) {
			return 'maxres';
		}

		if ( '0' === $cached ) {
			return 'hq';
		}

		return null;
	}

	/**
	 * @param string $video_id Sanitized video id.
	 * @param string $decision  Decision token.
	 */
	protected static function remember( $video_id, $decision ) {
		if ( 'hq-unverified' === $decision || 'skip' === $decision ) {
			return;
		}

		if ( 'maxres' === $decision ) {
			$ttl = self::TTL_MAXRES;
		} elseif ( 'unknown' === $decision ) {
			$ttl = self::TTL_UNKNOWN;
		} else {
			$ttl = self::TTL_MISSING;
		}

		set_transient( self::transient_key( $video_id ), $decision, $ttl );
	}

	/**
	 * @return bool
	 */
	protected static function probe_budget_spent() {
		return self::$stop_probing || self::$probes >= self::MAX_PROBES;
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
	 * @param string $url Thumbnail URL.
	 * @return string maxresdefault.jpg, hqdefault.jpg, or an empty string.
	 */
	protected static function youtube_thumbnail_file( $url ) {
		if ( ! preg_match( '#^https://img\.youtube\.com/vi/[A-Za-z0-9_-]{11}/(maxresdefault|hqdefault)\.jpg$#', $url, $matches ) ) {
			return '';
		}

		return $matches[1] . '.jpg';
	}

	/**
	 * @param string $video_id Video id.
	 * @return string
	 */
	protected static function transient_key( $video_id ) {
		return self::TRANSIENT_PREFIX . $video_id;
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
	 * @return bool
	 */
	protected static function is_cli() {
		if ( null !== self::$force_cli ) {
			return (bool) self::$force_cli;
		}

		return defined( 'WP_CLI' ) && WP_CLI;
	}

	/**
	 * @return bool
	 */
	protected static function is_cron() {
		return function_exists( 'wp_doing_cron' ) && wp_doing_cron();
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
