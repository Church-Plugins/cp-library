<?php
/**
 * Locks the opt-in YouTube thumbnail fallback.
 *
 * Sermons with a YouTube video and no image of their own were showing the
 * series image (or the site default). Sites can now opt in, on the Main
 * settings tab next to Default Thumbnail. With the setting off, the order
 * stays sermon, Vimeo, series, service type, default. With it on, a YouTube
 * video fills the same slot Vimeo already uses, and only while a site page
 * is rendered. The URL is not stored as the featured image. Podcast artwork
 * does not use it: channel art is the podcast image or the singular featured
 * image, and the episode description asks for the thumbnail with the podcast
 * context.
 *
 * maxresdefault.jpg is not guaranteed. A missing one is YouTube's 120x90
 * placeholder (about 1097 bytes), so a HEAD under 2000 bytes selects
 * hqdefault.jpg. The answer is cached in a transient for 30 days.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Library\Admin\Settings;
use CP_Library\Controllers\Item;
use CP_Library\Util\YouTubeThumbnail;
use PHPUnit\Framework\TestCase;

/**
 * Sermon thumbnail double. Skips the database and the Vimeo HTTP call.
 */
class ThumbnailOrderItem extends Item {

	/** @var array<string,string> */
	public $meta = array();

	/** @var string */
	public $vimeo = '';

	/** @var string */
	public $series = '';

	/** @var string */
	public $service = '';

	/** @var string */
	public $default_thumb = 'https://example.test/default.jpg';

	/** @var int */
	public $youtube_calls = 0;

	/** @var int */
	public $vimeo_calls = 0;

	/** @var int */
	public $service_calls = 0;

	/** @var bool */
	public $resolve_youtube = false;

	public function __construct() {
		$this->post  = (object) array( 'ID' => 42 );
		$this->model = new ThumbnailMetaModel();
	}

	/**
	 * @param array<string,string> $meta Meta values.
	 */
	public function set_meta( array $meta ) {
		$this->meta         = $meta;
		$this->model->meta  = $meta;
	}

	protected function maybeGetVimeoThumb() {
		$this->vimeo_calls++;

		return $this->vimeo;
	}

	protected function maybe_get_youtube_thumb() {
		$this->youtube_calls++;

		if ( $this->resolve_youtube ) {
			return parent::maybe_get_youtube_thumb();
		}

		return isset( $this->meta['__youtube_thumb'] ) ? $this->meta['__youtube_thumb'] : '';
	}

	protected function series_thumbnail() {
		return $this->series;
	}

	protected function service_type_thumbnail() {
		$this->service_calls++;

		return $this->service;
	}

	public function get_default_thumb() {
		return $this->default_thumb;
	}

	public function filter( $value, $function ) {
		return $value;
	}
}

/**
 * Meta stand-in for video_url and video_id_youtube.
 */
class ThumbnailMetaModel {

	/** @var array<string,string> */
	public $meta = array();

	public function get_meta_value( $key, $default = '' ) {
		return isset( $this->meta[ $key ] ) ? $this->meta[ $key ] : $default;
	}

	public static function get_prop( $key ) {
		return 'cpl_item';
	}
}

/**
 * Records the context passed into the podcast description image.
 */
class PodcastDescriptionItem extends Item {

	/** @var string[] */
	public $thumb_contexts = array();

	public function __construct() {
	}

	public function get_thumbnail( $context = '' ) {
		$this->thumb_contexts[] = $context;

		return 'https://example.test/series.jpg';
	}

	public function get_title() {
		return 'Hope & "Grace"';
	}

	public function filter( $value, $function ) {
		return $value;
	}
}

/**
 * @covers \CP_Library\Util\YouTubeThumbnail
 * @covers \CP_Library\Controllers\Item::get_thumbnail
 * @covers \CP_Library\Admin\Settings::youtube_thumbnail_fallback_enabled
 */
class YouTubeThumbnailFallbackTest extends TestCase {

	/** @var array<string,mixed> */
	public $options = array();

	/** @var mixed */
	public $sermon_image = false;

	/** @var bool */
	public $is_feed = false;

	/** @var bool */
	public $is_admin = false;

	/** @var bool */
	public $is_ajax = false;

	/** @var bool */
	public $is_cron = false;

	/** @var bool */
	public $read_transients = true;

	/** @var int */
	public $writes = 0;

	/** @var int */
	public $head_requests = 0;

	/** @var int */
	public $get_requests = 0;

	/** @var array<string,mixed> */
	public $transients = array();

	/** @var array<string,int> */
	public $transient_ttls = array();

	/** @var array<string,mixed>|null */
	public $head_response = null;

	/** @var array<string,mixed>|\WP_Error|null */
	public $hq_response = null;

	/** @var array<string,mixed>|\WP_Error|null */
	public $get_response = null;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->options       = array();
		$this->sermon_image  = false;
		$this->is_feed         = false;
		$this->is_admin        = false;
		$this->is_ajax         = false;
		$this->is_cron         = false;
		$this->read_transients = true;
		$this->writes          = 0;
		$this->head_requests   = 0;
		$this->get_requests    = 0;
		$this->transients      = array();
		$this->transient_ttls  = array();
		$this->head_response   = null;
		$this->hq_response     = null;
		$this->get_response    = null;

		YouTubeThumbnail::reset_request_state();

		$test = $this;

		Functions\when( 'get_option' )->alias(
			static function ( $key, $default = false ) use ( $test ) {
				return array_key_exists( $key, $test->options ) ? $test->options[ $key ] : $default;
			}
		);

		Functions\when( 'apply_filters' )->alias(
			static function ( $tag, $value ) {
				return $value;
			}
		);

		Functions\when( 'get_the_post_thumbnail_url' )->alias(
			static function ( $post_id ) use ( $test ) {
				if ( 42 === (int) $post_id ) {
					return $test->sermon_image;
				}

				return false;
			}
		);

		Functions\when( 'is_feed' )->alias(
			static function () use ( $test ) {
				return $test->is_feed;
			}
		);

		Functions\when( 'is_admin' )->alias(
			static function () use ( $test ) {
				return $test->is_admin;
			}
		);

		Functions\when( 'wp_doing_ajax' )->alias(
			static function () use ( $test ) {
				return $test->is_ajax;
			}
		);

		Functions\when( 'wp_doing_cron' )->alias(
			static function () use ( $test ) {
				return $test->is_cron;
			}
		);

		Functions\when( 'is_tax' )->justReturn( false );
		Functions\when( 'is_singular' )->justReturn( false );

		Functions\when( 'update_post_meta' )->alias(
			static function () use ( $test ) {
				$test->writes++;

				return true;
			}
		);

		Functions\when( 'set_post_thumbnail' )->alias(
			static function () use ( $test ) {
				$test->writes++;

				return true;
			}
		);

		Functions\when( 'get_transient' )->alias(
			static function ( $key ) use ( $test ) {
				if ( ! $test->read_transients ) {
					return false;
				}

				return array_key_exists( $key, $test->transients ) ? $test->transients[ $key ] : false;
			}
		);

		Functions\when( 'set_transient' )->alias(
			static function ( $key, $value, $ttl = 0 ) use ( $test ) {
				$test->transients[ $key ]     = $value;
				$test->transient_ttls[ $key ] = (int) $ttl;

				return true;
			}
		);

		Functions\when( 'wp_remote_head' )->alias(
			static function ( $url = '' ) use ( $test ) {
				$test->head_requests++;

				if ( is_string( $url ) && false !== strpos( $url, 'hqdefault.jpg' ) && null !== $test->hq_response ) {
					return $test->hq_response;
				}

				if ( null !== $test->head_response ) {
					return $test->head_response;
				}

				return array(
					'response' => array( 'code' => 200 ),
					'headers'  => array( 'content-length' => '84000' ),
					'body'     => '',
				);
			}
		);

		Functions\when( 'wp_remote_get' )->alias(
			static function () use ( $test ) {
				$test->get_requests++;

				if ( null !== $test->get_response ) {
					return $test->get_response;
				}

				return array(
					'response' => array( 'code' => 200 ),
					'headers'  => array(),
					'body'     => str_repeat( 'x', 3000 ),
				);
			}
		);

		Functions\when( 'esc_url' )->alias(
			static function ( $url ) {
				$url = trim( (string) $url );

				if ( ! preg_match( '#^https?://#i', $url ) ) {
					return '';
				}

				return str_replace( array( '"', "'", '<', '>' ), '', $url );
			}
		);

		Functions\when( 'esc_attr' )->alias(
			static function ( $text ) {
				return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
			}
		);

		Functions\when( 'get_the_excerpt' )->justReturn( 'Excerpt' );
		Functions\when( 'wpautop' )->alias(
			static function ( $text ) {
				return $text;
			}
		);
	}

	protected function tearDown(): void {
		YouTubeThumbnail::reset_request_state();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_setting_off_keeps_vimeo_ahead_of_series_and_ignores_youtube() {
		$item                        = $this->item();
		$item->vimeo                 = 'https://example.test/vimeo.jpg';
		$item->series                = 'https://example.test/series.jpg';
		$item->meta['__youtube_thumb'] = 'https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg';
		$item->set_meta( $item->meta );

		$this->assertSame( 'https://example.test/vimeo.jpg', $item->get_thumbnail() );
		$this->assertSame( 0, $item->youtube_calls, 'the YouTube lookup must not run while the setting is off' );
		$this->assertSame( 1, $item->vimeo_calls );
		$this->assertSame( 0, $this->head_requests );
		$this->assertSame( 0, $this->writes );
	}

	public function test_setting_on_with_youtube_and_no_sermon_image_uses_maxres() {
		$this->enable_setting();
		$item                   = $this->item();
		$item->resolve_youtube  = true;
		$item->vimeo            = 'https://example.test/vimeo.jpg';
		$item->series           = 'https://example.test/series.jpg';
		$item->set_meta(
			array(
				'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=43s',
			)
		);

		$this->assertSame(
			'https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg',
			$item->get_thumbnail()
		);
		$this->assertSame( 0, $item->vimeo_calls, 'YouTube occupies the Vimeo slot, so Vimeo is not also requested' );
		$this->assertSame( 0, $item->service_calls );
		$this->assertSame( 1, $this->head_requests );
		$this->assertSame( 'maxres', $this->transients[ YouTubeThumbnail::TRANSIENT_PREFIX . 'dQw4w9WgXcQ' ] );
		$this->assertSame( YouTubeThumbnail::TTL_MAXRES, $this->transient_ttls[ YouTubeThumbnail::TRANSIENT_PREFIX . 'dQw4w9WgXcQ' ] );
		$this->assertSame( 0, $this->writes, 'the thumbnail must not be saved as the featured image or post meta' );
	}

	public function test_maxres_placeholder_falls_back_to_hqdefault() {
		$this->enable_setting();
		$this->head_response = array(
			'response' => array( 'code' => 200 ),
			'headers'  => array( 'content-length' => '1097' ),
			'body'     => '',
		);
		$this->hq_response = array(
			'response' => array( 'code' => 200 ),
			'headers'  => array( 'content-length' => '18000' ),
			'body'     => '',
		);

		$item                  = $this->item();
		$item->resolve_youtube = true;
		$item->series          = 'https://example.test/series.jpg';
		$item->set_meta(
			array(
				'video_url' => 'https://youtu.be/jNQXAC9IVRw',
			)
		);

		$this->assertSame(
			'https://img.youtube.com/vi/jNQXAC9IVRw/hqdefault.jpg',
			$item->get_thumbnail()
		);
		$this->assertSame( 'hq', $this->transients[ YouTubeThumbnail::TRANSIENT_PREFIX . 'jNQXAC9IVRw' ] );
		$this->assertSame( YouTubeThumbnail::TTL_MISSING, $this->transient_ttls[ YouTubeThumbnail::TRANSIENT_PREFIX . 'jNQXAC9IVRw' ] );

		$item->get_thumbnail();

		$this->assertSame( 2, $this->head_requests, 'a cached answer must not ask YouTube again' );
		$this->assertSame( 0, $this->writes );
	}

	public function test_missing_maxres_http_status_uses_hqdefault() {
		$this->head_response = array(
			'response' => array( 'code' => 404 ),
			'headers'  => array(),
			'body'     => '',
		);
		$this->hq_response = array(
			'response' => array( 'code' => 200 ),
			'headers'  => array( 'content-length' => '18000' ),
			'body'     => '',
		);

		$this->assertSame(
			'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
			YouTubeThumbnail::url_for( 'dQw4w9WgXcQ' )
		);
		$this->assertSame( 'hq', $this->transients[ YouTubeThumbnail::TRANSIENT_PREFIX . 'dQw4w9WgXcQ' ] );
		$this->assertSame( YouTubeThumbnail::TTL_MISSING, $this->transient_ttls[ YouTubeThumbnail::TRANSIENT_PREFIX . 'dQw4w9WgXcQ' ] );
		$this->assertSame( 2, $this->head_requests );
		$this->assertSame( 0, $this->get_requests );
	}

	public function test_sermon_image_wins_over_youtube_series_and_service_type() {
		$this->enable_setting();
		$this->sermon_image          = 'https://example.test/sermon.jpg';
		$item                        = $this->item();
		$item->vimeo                 = 'https://example.test/vimeo.jpg';
		$item->series                = 'https://example.test/series.jpg';
		$item->service               = 'https://example.test/service.jpg';
		$item->meta['__youtube_thumb'] = 'https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg';
		$item->set_meta( $item->meta );

		$this->assertSame( 'https://example.test/sermon.jpg', $item->get_thumbnail() );
		$this->assertSame( 0, $item->youtube_calls );
		$this->assertSame( 0, $item->vimeo_calls );
		$this->assertSame( 0, $this->head_requests );
		$this->assertSame( 0, $this->writes );
	}

	public function test_series_image_is_used_when_there_is_no_video() {
		$this->enable_setting();
		$item         = $this->item();
		$item->series = 'https://example.test/series.jpg';
		$item->service = 'https://example.test/service.jpg';

		$this->assertSame( 'https://example.test/series.jpg', $item->get_thumbnail() );
		$this->assertSame( 0, $item->service_calls, 'a series image still comes before the service type image' );
		$this->assertSame( 0, $this->head_requests );
	}

	public function test_service_type_then_default_when_no_series_and_no_video() {
		$this->enable_setting();
		$item          = $this->item();
		$item->service = 'https://example.test/service.jpg';

		$this->assertSame( 'https://example.test/service.jpg', $item->get_thumbnail() );

		$bare = $this->item();
		$this->assertSame( 'https://example.test/default.jpg', $bare->get_thumbnail() );
	}

	public function test_vimeo_still_fills_the_video_slot_when_the_sermon_is_not_youtube() {
		$this->enable_setting();
		$item         = $this->item();
		$item->vimeo  = 'https://example.test/vimeo.jpg';
		$item->series = 'https://example.test/series.jpg';
		$item->resolve_youtube = true;
		$item->set_meta(
			array(
				'video_url'         => 'https://vimeo.com/123456789',
				'video_id_youtube'  => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
			)
		);

		$this->assertSame( 'https://example.test/vimeo.jpg', $item->get_thumbnail() );
		$this->assertSame( 0, $this->head_requests, 'a Vimeo URL must not be probed as YouTube' );
		$this->assertSame( 0, $this->writes );
	}

	public function test_podcast_context_and_feed_skip_youtube_but_keep_vimeo() {
		$this->enable_setting();
		$item                        = $this->item();
		$item->vimeo                 = 'https://example.test/vimeo.jpg';
		$item->series                = 'https://example.test/series.jpg';
		$item->meta['__youtube_thumb'] = 'https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg';
		$item->set_meta( $item->meta );

		$this->assertSame( 'https://example.test/vimeo.jpg', $item->get_thumbnail( 'podcast' ) );
		$this->assertSame( 0, $item->youtube_calls );

		$this->is_feed = true;
		$feed          = $this->item();
		$feed->series  = 'https://example.test/series.jpg';
		$feed->meta['__youtube_thumb'] = 'https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg';
		$feed->set_meta( $feed->meta );

		$this->assertSame( 'https://example.test/series.jpg', $feed->get_thumbnail() );
		$this->assertSame( 0, $feed->youtube_calls );
		$this->assertSame( 0, $this->head_requests );
	}

	public function test_podcast_description_asks_for_the_podcast_thumbnail_and_escapes_it() {
		$this->options['cpl_podcast_options'] = array( 'show_item_image' => 'on' );
		$item = new PodcastDescriptionItem();

		$html = $item->get_podcast_description_html();

		$this->assertSame( array( 'podcast' ), $item->thumb_contexts );
		$this->assertStringContainsString( 'src="https://example.test/series.jpg"', $html );
		$this->assertStringContainsString( 'alt="Hope &amp; &quot;Grace&quot;"', $html );
		$this->assertStringNotContainsString( 'img.youtube.com', $html );
	}

	public function test_podcast_description_without_the_image_setting_does_not_ask_for_a_thumbnail() {
		$item = new PodcastDescriptionItem();

		$html = $item->get_podcast_description_html();

		$this->assertSame( array(), $item->thumb_contexts );
		$this->assertStringNotContainsString( '<img', $html );
	}

	public function test_podcast_channel_template_does_not_use_the_sermon_thumbnail() {
		$root = dirname( __DIR__, 2 );

		$channel = file_get_contents( $root . '/templates/podcast.php' );
		$item    = file_get_contents( $root . '/templates/parts/podcast-item.php' );

		$this->assertStringNotContainsString( 'get_thumbnail', $channel );
		$this->assertStringContainsString( "get_thumbnail( 'podcast' )", $item );
	}

	public function test_admin_request_does_not_probe_youtube() {
		$this->enable_setting();
		$this->is_admin = true;

		$item                  = $this->item();
		$item->resolve_youtube = true;
		$item->series          = 'https://example.test/series.jpg';
		$item->set_meta(
			array(
				'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
			)
		);

		$this->assertSame( 'https://example.test/series.jpg', $item->get_thumbnail() );
		$this->assertSame( 0, $item->youtube_calls );
		$this->assertSame( 0, $this->head_requests );
		$this->assertFalse( YouTubeThumbnail::probe_allowed() );
	}

	/**
	 * @dataProvider youtube_urls
	 */
	public function test_common_youtube_urls_become_thumbnail_ids( $url, $expected, $reason ) {
		$this->assertSame( $expected, YouTubeThumbnail::id_from_sources( $url ), $reason );

		if ( '' === $expected ) {
			$this->assertSame( '', YouTubeThumbnail::url_for( $expected ) );
			return;
		}

		$this->transients[ 'cpl_yt_maxres_' . $expected ] = '1';
		$this->assertSame(
			'https://img.youtube.com/vi/' . $expected . '/maxresdefault.jpg',
			YouTubeThumbnail::image_url( $expected, 'maxresdefault.jpg' )
		);
	}

	public function youtube_urls() {
		return array(
			'watch'            => array( 'https://www.youtube.com/watch?v=jNQXAC9IVRw', 'jNQXAC9IVRw', 'watch?v=' ),
			'watch extra'      => array( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&si=abc123XYZ01&t=12', 'dQw4w9WgXcQ', 'extra params are not part of the id' ),
			'param order'      => array( 'https://www.youtube.com/watch?si=abc123XYZ01&v=dQw4w9WgXcQ', 'dQw4w9WgXcQ', 'v can follow other params' ),
			'youtu.be'         => array( 'https://youtu.be/dQw4w9WgXcQ', 'dQw4w9WgXcQ', 'youtu.be' ),
			'youtu.be params'  => array( 'https://youtu.be/v2lcFS3FCEc?si=abc123XYZ01&t=12', 'v2lcFS3FCEc', 'youtu.be with params' ),
			'embed'            => array( 'https://www.youtube.com/embed/dQw4w9WgXcQ', 'dQw4w9WgXcQ', 'embed' ),
			'embed start'      => array( 'https://www.youtube.com/embed/jNQXAC9IVRw?start=10', 'jNQXAC9IVRw', 'embed with a start time' ),
			'shorts'           => array( 'https://www.youtube.com/shorts/v2lcFS3FCEc', 'v2lcFS3FCEc', 'shorts' ),
			'live'             => array( 'https://www.youtube.com/live/jNQXAC9IVRw?si=abc', 'jNQXAC9IVRw', 'live' ),
			'nocookie'         => array( 'https://www.youtube-nocookie.com/embed/jNQXAC9IVRw', 'jNQXAC9IVRw', 'privacy-enhanced embed' ),
			'mobile'           => array( 'https://m.youtube.com/watch?v=jNQXAC9IVRw', 'jNQXAC9IVRw', 'mobile watch' ),
			'embed html'       => array( '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>', 'dQw4w9WgXcQ', 'embed snippet' ),
			'bare id in video url' => array( 'dQw4w9WgXcQ', '', 'video_url is not treated as a YouTube id from 11 characters alone' ),
			'vimeo'            => array( 'https://vimeo.com/123456789', '', 'Vimeo is not a YouTube id' ),
			'mp4'              => array( 'https://cdn.example.test/sermon.mp4', '', 'a file URL is not a YouTube id' ),
			'facebook'         => array( 'https://www.facebook.com/watch/?v=123', '', 'Facebook is not a YouTube id' ),
			'channel'          => array( 'https://www.youtube.com/@church', '', 'a channel URL is not a video' ),
			'empty'            => array( '', '', 'no video' ),
		);
	}

	public function test_optional_youtube_field_is_used_only_when_video_url_is_not_another_host() {
		$this->assertSame(
			'dQw4w9WgXcQ',
			YouTubeThumbnail::id_from_sources( '', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' )
		);
		$this->assertSame(
			'jNQXAC9IVRw',
			YouTubeThumbnail::id_from_sources( '', 'jNQXAC9IVRw' ),
			'the dedicated YouTube field still accepts a bare id'
		);
		$this->assertSame(
			'',
			YouTubeThumbnail::id_from_sources( 'dQw4w9WgXcQ' ),
			'the same token in video_url is not a YouTube URL'
		);
		$this->assertSame(
			'',
			YouTubeThumbnail::id_from_sources( 'https://vimeo.com/123456789', 'https://youtu.be/dQw4w9WgXcQ' ),
			'a Vimeo video URL is the sermon video, so a leftover YouTube field is ignored'
		);
	}

	public function test_response_size_and_placeholder_dimensions() {
		$this->assertFalse(
			YouTubeThumbnail::response_has_maxres(
				array(
					'code'           => 200,
					'content_length' => 1097,
					'body'           => '',
				)
			)
		);
		$this->assertFalse(
			YouTubeThumbnail::response_has_maxres(
				array(
					'code'           => 404,
					'content_length' => null,
					'body'           => '',
				)
			)
		);
		$this->assertTrue(
			YouTubeThumbnail::response_has_maxres(
				array(
					'code'           => 200,
					'content_length' => 84000,
					'body'           => '',
				)
			)
		);

		$placeholder = 'GIF89a' . pack( 'v', 120 ) . pack( 'v', 90 ) . str_repeat( "\x00", 2100 );
		$real        = 'GIF89a' . pack( 'v', 1280 ) . pack( 'v', 720 ) . str_repeat( "\x00", 2100 );

		$this->assertFalse(
			YouTubeThumbnail::response_has_maxres(
				array(
					'code'           => 200,
					'content_length' => null,
					'body'           => $placeholder,
				)
			),
			'a 120x90 image is the YouTube placeholder'
		);
		$this->assertTrue(
			YouTubeThumbnail::response_has_maxres(
				array(
					'code'           => 200,
					'content_length' => null,
					'body'           => $real,
				)
			)
		);
	}

	public function test_transport_error_uses_hqdefault_and_caches_it_for_an_hour() {
		$this->head_response = new \WP_Error( 'http_request_failed', 'timed out' );
		$key                 = YouTubeThumbnail::TRANSIENT_PREFIX . 'dQw4w9WgXcQ';

		$this->assertSame(
			'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
			YouTubeThumbnail::url_for( 'dQw4w9WgXcQ' )
		);
		$this->assertSame( 'unknown', $this->transients[ $key ] );
		$this->assertSame( YouTubeThumbnail::TTL_UNKNOWN, $this->transient_ttls[ $key ] );
		$this->assertSame( 1, $this->head_requests );

		YouTubeThumbnail::reset_request_state();
		YouTubeThumbnail::url_for( 'dQw4w9WgXcQ' );

		$this->assertSame( 1, $this->head_requests, 'the hour-long cache must absorb the next lookup' );
		$this->assertSame( '1', $this->transients[ YouTubeThumbnail::UNREACHABLE_KEY ] );
		$this->assertSame( YouTubeThumbnail::TTL_UNREACHABLE, $this->transient_ttls[ YouTubeThumbnail::UNREACHABLE_KEY ] );
		$this->assertSame( 0, $this->writes );
	}

	public function test_first_timeout_sets_the_site_unreachable_flag() {
		$this->head_response = new \WP_Error( 'http_request_failed', 'timed out' );

		YouTubeThumbnail::url_for( 'dQw4w9WgXcQ' );

		$this->assertSame( '1', $this->transients[ YouTubeThumbnail::UNREACHABLE_KEY ] );
		$this->assertSame( YouTubeThumbnail::TTL_UNREACHABLE, $this->transient_ttls[ YouTubeThumbnail::UNREACHABLE_KEY ] );
		$this->assertSame( 1, $this->head_requests );

		YouTubeThumbnail::reset_request_state();
		unset( $this->transients[ YouTubeThumbnail::UNREACHABLE_KEY ] );
		$this->head_response = array(
			'response' => array( 'code' => 200 ),
			'headers'  => array(),
			'body'     => '',
		);
		$this->get_response = new \WP_Error( 'http_request_failed', 'connection refused' );

		YouTubeThumbnail::url_for( 'jNQXAC9IVRw' );

		$this->assertSame( '1', $this->transients[ YouTubeThumbnail::UNREACHABLE_KEY ] );
		$this->assertSame( 1, $this->get_requests, 'a failed GET is a transport error too' );
	}

	public function test_unreachable_flag_makes_later_requests_skip_probes_until_it_expires() {
		$this->head_response = new \WP_Error( 'http_request_failed', 'timed out' );

		YouTubeThumbnail::url_for( 'dQw4w9WgXcQ' );
		$this->assertSame( 1, $this->head_requests );

		YouTubeThumbnail::reset_request_state();

		$this->assertSame(
			'https://img.youtube.com/vi/jNQXAC9IVRw/hqdefault.jpg',
			YouTubeThumbnail::url_for( 'jNQXAC9IVRw' )
		);
		$this->assertSame( 1, $this->head_requests, 'no remote check while YouTube is marked unreachable' );
		$this->assertArrayNotHasKey(
			YouTubeThumbnail::TRANSIENT_PREFIX . 'jNQXAC9IVRw',
			$this->transients,
			'an unverified hqdefault is not stored as a miss'
		);
		$this->assertSame(
			'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
			YouTubeThumbnail::url_for( 'dQw4w9WgXcQ' ),
			'a video already cached is still used'
		);
		$this->assertSame( 1, $this->head_requests );

		unset( $this->transients[ YouTubeThumbnail::UNREACHABLE_KEY ] );
		YouTubeThumbnail::reset_request_state();
		$this->head_response = null;

		$this->assertSame(
			'https://img.youtube.com/vi/jNQXAC9IVRw/maxresdefault.jpg',
			YouTubeThumbnail::url_for( 'jNQXAC9IVRw' )
		);
		$this->assertSame( 2, $this->head_requests, 'probing resumes after the flag expires' );
	}

	public function test_http_status_and_placeholder_do_not_mark_youtube_unreachable() {
		$this->head_response = array(
			'response' => array( 'code' => 404 ),
			'headers'  => array(),
			'body'     => '',
		);
		$this->hq_response = array(
			'response' => array( 'code' => 200 ),
			'headers'  => array( 'content-length' => '18000' ),
			'body'     => '',
		);

		YouTubeThumbnail::url_for( 'dQw4w9WgXcQ' );
		$this->assertArrayNotHasKey( YouTubeThumbnail::UNREACHABLE_KEY, $this->transients );

		YouTubeThumbnail::reset_request_state();
		$this->head_response = array(
			'response' => array( 'code' => 200 ),
			'headers'  => array( 'content-length' => '1097' ),
			'body'     => '',
		);

		YouTubeThumbnail::url_for( 'jNQXAC9IVRw' );
		$this->assertArrayNotHasKey( YouTubeThumbnail::UNREACHABLE_KEY, $this->transients, 'the 120x90 placeholder is a per-video result' );

		YouTubeThumbnail::reset_request_state();
		$this->head_response = array(
			'response' => array( 'code' => 500 ),
			'headers'  => array(),
			'body'     => '',
		);
		$this->hq_response = null;

		YouTubeThumbnail::url_for( 'v2lcFS3FCEc' );
		$this->assertArrayNotHasKey( YouTubeThumbnail::UNREACHABLE_KEY, $this->transients, 'an HTTP 500 is not a transport failure' );
		$this->assertGreaterThan( 0, $this->head_requests );
	}

	public function test_head_without_content_length_uses_one_small_get() {
		$this->head_response = array(
			'response' => array( 'code' => 200 ),
			'headers'  => array(),
			'body'     => '',
		);

		$this->assertSame(
			'https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg',
			YouTubeThumbnail::url_for( 'dQw4w9WgXcQ' )
		);
		$this->assertSame( 1, $this->head_requests );
		$this->assertSame( 1, $this->get_requests );
		$this->assertSame( 'maxres', $this->transients[ YouTubeThumbnail::TRANSIENT_PREFIX . 'dQw4w9WgXcQ' ] );
		$this->assertSame( YouTubeThumbnail::TTL_MAXRES, $this->transient_ttls[ YouTubeThumbnail::TRANSIENT_PREFIX . 'dQw4w9WgXcQ' ] );
	}

	public function test_remote_image_html_escapes_a_youtube_url_and_keeps_attachment_html() {
		$html = YouTubeThumbnail::remote_image_html(
			'https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg',
			'',
			array(
				'alt'   => 'Hope & "Grace"',
				'class' => 'cpl-thumb',
				'style' => 'width:100%;',
			),
			true
		);

		$this->assertStringContainsString( 'src="https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg"', $html );
		$this->assertStringContainsString( 'alt="Hope &amp; &quot;Grace&quot;"', $html );
		$this->assertStringContainsString( 'class="cpl-thumb"', $html );
		$this->assertStringContainsString( 'width="1280"', $html );
		$this->assertStringContainsString( 'height="720"', $html );
		$this->assertStringContainsString( 'loading="lazy"', $html );

		$hq = YouTubeThumbnail::remote_image_html(
			'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
			'',
			array(),
			true
		);
		$this->assertStringContainsString( 'width="480"', $hq );
		$this->assertStringContainsString( 'height="360"', $hq );

		$this->assertSame( '', YouTubeThumbnail::remote_image_html( 'javascript:alert(1)', '', array(), true ) );
		$this->assertSame( '<img src="local" />', YouTubeThumbnail::remote_image_html( 'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg', '<img src="local" />', array(), true ) );
	}

	public function test_item_graphic_setting_off_does_not_render_a_vimeo_or_logo_thumbnail() {
		$this->assertSame(
			'',
			YouTubeThumbnail::remote_image_html( 'https://i.vimeocdn.com/video/123_640.jpg', '', array( 'alt' => 'Sermon' ), false ),
			'a Vimeo-only sermon kept an empty item graphic before this feature'
		);
		$this->assertSame(
			'',
			YouTubeThumbnail::remote_image_html( 'https://example.test/wp-content/plugins/cp-library/assets/images/cpl-logo.jpg', '', array(), false )
		);
		$this->assertSame(
			'',
			YouTubeThumbnail::remote_image_html( 'https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg', '', array(), false ),
			'the remote image is drawn only while the setting is on'
		);
	}

	/**
	 * @dataProvider checkbox_values
	 */
	public function test_checkbox_setting_is_sanitized_and_off_when_missing( $raw, $stored, $enabled, $reason ) {
		$this->assertSame( $stored, Settings::sanitize_checkbox( $raw ), $reason );

		$this->options['cpl_main_options'] = array( 'youtube_thumbnail_fallback' => $stored );
		$this->assertSame( $enabled, Settings::youtube_thumbnail_fallback_enabled(), $reason );
	}

	public function test_missing_setting_is_off() {
		$this->assertFalse( Settings::youtube_thumbnail_fallback_enabled() );
	}

	public function checkbox_values() {
		return array(
			'checked on'  => array( 'on', '1', true, 'CMB2 sends on for a checked box' ),
			'checked 1'   => array( '1', '1', true, 'a stored 1 stays on' ),
			'yes'         => array( 'yes', '1', true, 'yes is on' ),
			'empty'       => array( '', '', false, 'an unchecked box stores nothing' ),
			'zero'        => array( '0', '', false, 'zero is off' ),
			'script'      => array( '<script>alert(1)</script>', '', false, 'markup is not a checkbox' ),
			'array on'    => array( array( 'on' ), '1', true, 'a single-value array is accepted' ),
		);
	}

	/**
	 * @dataProvider fallback_matrix
	 */
	public function test_fallback_is_limited_to_site_pages( $enabled, $context, $is_feed, $is_admin, $is_ajax, $expected ) {
		$this->assertSame(
			$expected,
			YouTubeThumbnail::fallback_allowed( $enabled, $context, $is_feed, $is_admin, $is_ajax )
		);
	}

	public function fallback_matrix() {
		return array(
			'off'             => array( false, '', false, false, false, false ),
			'site'            => array( true, '', false, false, false, true ),
			'legacy size arg' => array( true, 'large', false, false, false, true ),
			'podcast context' => array( true, 'podcast', false, false, false, false ),
			'feed'            => array( true, '', true, false, false, false ),
			'admin screen'    => array( true, '', false, true, false, false ),
			'front-end ajax'  => array( true, '', false, true, true, true ),
		);
	}

	public function test_front_end_ajax_still_uses_the_youtube_thumbnail() {
		$this->enable_setting();
		$this->is_admin = true;
		$this->is_ajax  = true;

		$item                  = $this->item();
		$item->resolve_youtube = true;
		$item->series          = 'https://example.test/series.jpg';
		$item->set_meta(
			array(
				'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
			)
		);

		$this->assertSame(
			'https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg',
			$item->get_thumbnail()
		);
		$this->assertSame( 1, $this->head_requests );
		$this->assertTrue( YouTubeThumbnail::probe_allowed() );
	}

	public function test_probe_is_skipped_on_an_admin_screen_when_nothing_is_cached() {
		$this->is_admin = true;

		$this->assertSame( '', YouTubeThumbnail::url_for( 'dQw4w9WgXcQ' ) );
		$this->assertSame( 0, $this->head_requests );
		$this->assertSame( array(), $this->transients, 'a skipped admin probe must not cache hqdefault as a miss' );
		$this->assertFalse( YouTubeThumbnail::probe_allowed() );
	}

	public function test_repeated_lookups_for_one_video_probe_once() {
		$this->read_transients = false;

		YouTubeThumbnail::url_for( 'dQw4w9WgXcQ' );
		YouTubeThumbnail::url_for( 'dQw4w9WgXcQ' );
		YouTubeThumbnail::url_for( 'dQw4w9WgXcQ' );
		YouTubeThumbnail::url_for( 'dQw4w9WgXcQ' );

		$this->assertSame( 1, $this->head_requests, 'four renders of one sermon share one probe' );
	}

	public function test_one_timeout_stops_later_probes_in_the_same_request() {
		$this->head_response = new \WP_Error( 'http_request_failed', 'timed out' );

		$this->assertSame(
			'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
			YouTubeThumbnail::url_for( 'dQw4w9WgXcQ' )
		);
		$this->assertSame(
			'https://img.youtube.com/vi/jNQXAC9IVRw/hqdefault.jpg',
			YouTubeThumbnail::url_for( 'jNQXAC9IVRw' )
		);

		$this->assertSame( 1, $this->head_requests );
		$this->assertArrayNotHasKey(
			YouTubeThumbnail::TRANSIENT_PREFIX . 'jNQXAC9IVRw',
			$this->transients,
			'a video that was not checked is not stored as a miss'
		);
	}

	public function test_probe_cap_uses_hqdefault_without_another_request() {
		for ( $i = 0; $i < YouTubeThumbnail::MAX_PROBES; $i++ ) {
			YouTubeThumbnail::url_for( sprintf( 'vid%08d', $i ) );
		}

		$this->assertSame( YouTubeThumbnail::MAX_PROBES, $this->head_requests );

		$extra = YouTubeThumbnail::url_for( 'jNQXAC9IVRw' );

		$this->assertSame( 'https://img.youtube.com/vi/jNQXAC9IVRw/hqdefault.jpg', $extra );
		$this->assertSame( YouTubeThumbnail::MAX_PROBES, $this->head_requests );
		$this->assertArrayNotHasKey( YouTubeThumbnail::TRANSIENT_PREFIX . 'jNQXAC9IVRw', $this->transients );
	}

	public function test_both_thumbnails_missing_falls_through_to_the_series_image() {
		$this->enable_setting();
		$missing = array(
			'response' => array( 'code' => 404 ),
			'headers'  => array(),
			'body'     => '',
		);
		$this->head_response = $missing;
		$this->hq_response   = $missing;

		$item                  = $this->item();
		$item->resolve_youtube = true;
		$item->series          = 'https://example.test/series.jpg';
		$item->set_meta(
			array(
				'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
			)
		);

		$this->assertSame( 'https://example.test/series.jpg', $item->get_thumbnail() );
		$this->assertSame( 'none', $this->transients[ YouTubeThumbnail::TRANSIENT_PREFIX . 'dQw4w9WgXcQ' ] );
		$this->assertSame( YouTubeThumbnail::TTL_MISSING, $this->transient_ttls[ YouTubeThumbnail::TRANSIENT_PREFIX . 'dQw4w9WgXcQ' ] );
		$this->assertSame( 0, $this->writes );
	}

	public function test_cli_and_cron_do_not_probe_and_skip_youtube_without_a_cache() {
		$this->enable_setting();
		$this->is_cron = true;

		$item                  = $this->item();
		$item->resolve_youtube = true;
		$item->series          = 'https://example.test/series.jpg';
		$item->set_meta(
			array(
				'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
			)
		);

		$this->assertSame( 'https://example.test/series.jpg', $item->get_thumbnail() );
		$this->assertSame( 0, $this->head_requests );
		$this->assertFalse( YouTubeThumbnail::probe_allowed() );

		$this->transients[ YouTubeThumbnail::TRANSIENT_PREFIX . 'dQw4w9WgXcQ' ] = 'maxres';
		YouTubeThumbnail::reset_request_state();
		$this->is_cron = true;

		$this->assertSame(
			'https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg',
			$item->get_thumbnail()
		);
		$this->assertSame( 0, $this->head_requests, 'a cached answer is used without a new request' );

		$this->is_cron = false;
		unset( $this->transients[ YouTubeThumbnail::TRANSIENT_PREFIX . 'dQw4w9WgXcQ' ] );
		YouTubeThumbnail::reset_request_state();
		YouTubeThumbnail::$force_cli = true;

		$cli                  = $this->item();
		$cli->resolve_youtube = true;
		$cli->series          = 'https://example.test/series.jpg';
		$cli->set_meta(
			array(
				'video_url' => 'https://youtu.be/jNQXAC9IVRw',
			)
		);

		$this->assertSame( 'https://example.test/series.jpg', $cli->get_thumbnail() );
		$this->assertSame( 0, $this->head_requests );
		$this->assertFalse( YouTubeThumbnail::probe_allowed() );
	}

	/**
	 * @return ThumbnailOrderItem
	 */
	private function item() {
		return new ThumbnailOrderItem();
	}

	private function enable_setting() {
		$this->options['cpl_main_options'] = array(
			'youtube_thumbnail_fallback' => '1',
		);
	}
}
