<?php
/**
 * Tests for YouTube transcript import when caption URLs come back empty.
 *
 * The watch page still lists captionTracks, but those baseUrls now carry
 * exp=xpe and return HTTP 200 with a 0-byte body. Import has to ask the
 * InnerTube player endpoint, with an ANDROID client context, for track URLs
 * and then reuse the existing caption XML parser. Share links also stopped
 * resolving: a watch URL with si or t left the extra query on the video id,
 * and /live/, /embed/, and /shorts/ URLs were not recognized at all.
 *
 * When that fetch fails or the caption file is empty, the sermon can still
 * get a transcript from a .vtt or .srt upload, with timestamps, cue numbers,
 * and tags removed.
 *
 * `__` and `apply_filters` pass the value through unless a test says otherwise,
 * so assertions are about our strings and the default ANDROID client.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Library\Integrations\YouTube;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Library\Integrations\YouTube::parse_video_id
 * @covers \CP_Library\Integrations\YouTube::player_request_body
 * @covers \CP_Library\Integrations\YouTube::innertube_client
 * @covers \CP_Library\Integrations\YouTube::caption_track_urls
 * @covers \CP_Library\Integrations\YouTube::parse_caption_xml
 * @covers \CP_Library\Integrations\YouTube::captions_to_text
 */
class YouTubeTranscriptImportTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( '__' )->alias(
			static function ( $text ) {
				return $text;
			}
		);

		Functions\when( 'apply_filters' )->alias(
			static function ( $tag, $value ) {
				return $value;
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @dataProvider video_urls
	 */
	public function test_video_id_is_read_from_youtube_url_shapes( $url, $expected, $reason ) {
		$this->assertSame( $expected, YouTube::parse_video_id( $url ), $reason );
	}

	public function video_urls() {
		return [
			'watch url'              => [ 'https://www.youtube.com/watch?v=jNQXAC9IVRw', 'jNQXAC9IVRw', 'a plain watch url' ],
			'watch url with t'       => [ 'https://www.youtube.com/watch?v=jNQXAC9IVRw&t=43s', 'jNQXAC9IVRw', 'a start time must not stick to the id' ],
			'watch url with si'      => [ 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&si=abc123XYZ01', 'dQw4w9WgXcQ', 'a share id must not stick to the id' ],
			'si before v'            => [ 'https://www.youtube.com/watch?si=abc123XYZ01&v=dQw4w9WgXcQ&t=12', 'dQw4w9WgXcQ', 'parameter order must not matter' ],
			'second question mark'   => [ 'https://www.youtube.com/watch?v=jNQXAC9IVRw?si=abc123XYZ01', 'jNQXAC9IVRw', 'a pasted ?si= must not stick to the id' ],
			'youtu.be'               => [ 'https://youtu.be/dQw4w9WgXcQ', 'dQw4w9WgXcQ', 'a short share link' ],
			'youtu.be with si and t' => [ 'https://youtu.be/v2lcFS3FCEc?si=abc123XYZ01&t=12', 'v2lcFS3FCEc', 'share params on a short link' ],
			'youtu.be with amp'      => [ 'https://youtu.be/jNQXAC9IVRw&t=12', 'jNQXAC9IVRw', 'an & used where a ? belongs' ],
			'live'                   => [ 'https://www.youtube.com/live/v2lcFS3FCEc', 'v2lcFS3FCEc', 'a live url' ],
			'live with si'           => [ 'https://www.youtube.com/live/jNQXAC9IVRw?si=abc123XYZ01', 'jNQXAC9IVRw', 'a live url with a share param' ],
			'embed'                  => [ 'https://www.youtube.com/embed/dQw4w9WgXcQ', 'dQw4w9WgXcQ', 'an embed url' ],
			'embed with start'       => [ 'https://www.youtube.com/embed/jNQXAC9IVRw?start=10', 'jNQXAC9IVRw', 'an embed url with a start time' ],
			'shorts'                 => [ 'https://www.youtube.com/shorts/v2lcFS3FCEc', 'v2lcFS3FCEc', 'a shorts url' ],
			'shorts with feature'    => [ 'https://youtube.com/shorts/dQw4w9WgXcQ?feature=share', 'dQw4w9WgXcQ', 'a shorts url without www' ],
			'nocookie embed'         => [ 'https://www.youtube-nocookie.com/embed/jNQXAC9IVRw', 'jNQXAC9IVRw', 'a privacy-enhanced embed' ],
			'mobile watch'           => [ 'https://m.youtube.com/watch?v=jNQXAC9IVRw', 'jNQXAC9IVRw', 'a mobile watch url' ],
			'embed html'             => [ '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" title="sermon"></iframe>', 'dQw4w9WgXcQ', 'an embed snippet stores the url in src' ],
			'encoded amp'            => [ 'https://www.youtube.com/watch?v=jNQXAC9IVRw&amp;t=43s', 'jNQXAC9IVRw', 'an html-encoded query' ],
			'vimeo'                  => [ 'https://vimeo.com/123456789', '', 'a non-youtube url has no video id' ],
			'channel'                => [ 'https://www.youtube.com/@church', '', 'a channel url is not a video' ],
			'empty'                  => [ '', '', 'an empty value' ],
		];
	}

	public function test_player_request_uses_the_android_client() {
		$body = YouTube::player_request_body( 'jNQXAC9IVRw' );

		$this->assertSame( 'https://www.youtube.com/youtubei/v1/player', YouTube::player_endpoint() );
		$this->assertSame( 'jNQXAC9IVRw', $body['videoId'] );
		$this->assertSame( 'ANDROID', $body['context']['client']['clientName'] );
		$this->assertSame( '20.10.38', $body['context']['client']['clientVersion'] );
	}

	public function test_innertube_client_version_is_filterable() {
		Functions\when( 'apply_filters' )->alias(
			static function ( $tag, $value ) {
				if ( 'cpl_youtube_innertube_client' === $tag ) {
					$value['clientVersion'] = '21.2.1';
				}

				return $value;
			}
		);

		$body = YouTube::player_request_body( 'dQw4w9WgXcQ' );

		$this->assertSame( '21.2.1', $body['context']['client']['clientVersion'] );
		$this->assertSame( 'ANDROID', $body['context']['client']['clientName'] );
		$this->assertSame( 'dQw4w9WgXcQ', $body['videoId'] );
	}

	public function test_caption_tracks_are_kept_when_the_url_contains_exp_xpe() {
		$urls = YouTube::caption_track_urls( [
			'captions' => [
				'playerCaptionsTracklistRenderer' => [
					'captionTracks' => [
						[ 'baseUrl' => 'https://www.youtube.com/api/timedtext?v=jNQXAC9IVRw&exp=xpe&lang=en' ],
						[ 'languageCode' => 'es' ],
						'not-a-track',
					],
				],
			],
		] );

		$this->assertSame(
			[ 'https://www.youtube.com/api/timedtext?v=jNQXAC9IVRw&exp=xpe&lang=en' ],
			$urls
		);
	}

	public function test_missing_caption_tracks_yield_no_urls() {
		$this->assertSame( [], YouTube::caption_track_urls( [] ) );
		$this->assertSame( [], YouTube::caption_track_urls( 'nope' ) );
	}

	public function test_caption_xml_parser_reads_text_cues() {
		$xml = '<transcript><text start="1.5" dur="2.0">Hello &amp; welcome</text><text start="4" dur="1">   </text><text start="5" dur="1.25">Next line</text></transcript>';

		$this->assertSame(
			[
				[
					'text'     => 'Hello &amp; welcome',
					'duration' => '2.0',
					'offset'   => '1.5',
				],
				[
					'text'     => 'Next line',
					'duration' => '1.25',
					'offset'   => '5',
				],
			],
			YouTube::parse_caption_xml( $xml )
		);
	}

	public function test_empty_caption_xml_has_no_cues() {
		$this->assertSame( [], YouTube::parse_caption_xml( '' ) );
		$this->assertSame( [], YouTube::parse_caption_xml( '<transcript></transcript>' ) );
	}

	public function test_unavailable_message_tells_the_user_to_upload_or_paste() {
		$message = YouTube::captions_unavailable_message();

		$this->assertStringContainsString( '.vtt', $message );
		$this->assertStringContainsString( '.srt', $message );
		$this->assertStringContainsString( 'paste the transcript', $message );
	}

	public function test_srt_upload_becomes_plain_text() {
		$srt = "1\r\n00:00:01,000 --> 00:00:04,000\r\nHello <b>world</b>\r\n\r\n2\r\n00:00:04,000 --> 00:00:06,000\r\n{\\an8}Second &amp; line\r\n";

		$this->assertSame( 'Hello world Second & line', YouTube::captions_to_text( $srt ) );
	}

	public function test_vtt_upload_drops_headers_timestamps_and_tags() {
		$vtt = <<<'VTT'
WEBVTT
Kind: captions
Language: en

NOTE
This is a comment

STYLE
::cue { color: white }

00:00:00.000 --> 00:00:02.000 align:start position:0%
<v Pastor>Welcome to church

00:01.500 --> 00:03.000
See you <c.colorE5E5E5>Sunday</c>
VTT;

		$this->assertSame( 'Welcome to church See you Sunday', YouTube::captions_to_text( $vtt ) );
	}

	public function test_a_numeric_caption_line_is_kept() {
		$srt = "2\n00:00:01,000 --> 00:00:02,000\n3\n";

		$this->assertSame( '3', YouTube::captions_to_text( $srt ) );
	}

	public function test_caption_file_with_no_spoken_text_is_empty() {
		$this->assertSame( '', YouTube::captions_to_text( "WEBVTT\n\nNOTE nothing here\n" ) );
	}

	public function test_srv3_caption_urls_are_fetched_as_legacy_xml() {
		$this->assertSame(
			'https://www.youtube.com/api/timedtext?v=jNQXAC9IVRw&lang=en',
			YouTube::caption_fetch_url( 'https://www.youtube.com/api/timedtext?v=jNQXAC9IVRw&fmt=srv3&lang=en' )
		);
		$this->assertSame(
			'https://www.youtube.com/api/timedtext?v=jNQXAC9IVRw',
			YouTube::caption_fetch_url( 'https://www.youtube.com/api/timedtext?fmt=srv3&v=jNQXAC9IVRw' )
		);
		$this->assertSame(
			'https://www.youtube.com/api/timedtext?v=jNQXAC9IVRw&exp=xpe',
			YouTube::caption_fetch_url( 'https://www.youtube.com/api/timedtext?v=jNQXAC9IVRw&exp=xpe' )
		);
	}

	public function test_caption_url_must_be_https_on_youtube() {
		$this->assertTrue( YouTube::is_youtube_caption_url( 'https://www.youtube.com/api/timedtext?v=jNQXAC9IVRw' ) );
		$this->assertFalse( YouTube::is_youtube_caption_url( 'http://www.youtube.com/api/timedtext?v=jNQXAC9IVRw' ) );
		$this->assertFalse( YouTube::is_youtube_caption_url( 'https://example.com/api/timedtext?v=jNQXAC9IVRw' ) );
	}
}
