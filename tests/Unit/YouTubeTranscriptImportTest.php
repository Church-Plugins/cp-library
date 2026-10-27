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
 * @covers \CP_Library\Integrations\YouTube::decode_caption_entities
 * @covers \CP_Library\Integrations\YouTube::normalize_caption_text
 * @covers \CP_Library\Integrations\YouTube::format_imported_transcript
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
					'text'     => 'Hello & welcome',
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

	/**
	 * VTT cue ids are not always numbers. `intro-2` sits on the line before the
	 * timing line and was stored as spoken text, because only `^\d+$` labels
	 * were skipped. A blank line still means the previous line is spoken text.
	 */
	public function test_a_named_cue_identifier_is_not_spoken_text() {
		$vtt = <<<'VTT'
WEBVTT

intro-2
00:00:01.000 --> 00:00:03.000
Hello church

00:00:04.000 --> 00:00:06.000
See you Sunday
VTT;

		$this->assertSame( 'Hello church See you Sunday', YouTube::captions_to_text( $vtt ) );
	}

	/**
	 * strip_tags() leaves a '<' that never finds a '>'. A caption line such as
	 * `<img src=x onerror=alert(1)` has no closing bracket, so it was stored
	 * and later printed as markup.
	 */
	public function test_unclosed_tag_fragment_is_removed_from_caption_text() {
		$srt = "1\n00:00:01,000 --> 00:00:02,000\nHello <img src=x onerror=alert(1)\n";

		$this->assertSame( 'Hello', YouTube::captions_to_text( $srt ) );
	}

	/**
	 * A space after '<' is a comparison. `<3` has no closing '>' and
	 * wp_strip_all_tags() treats it as an unclosed tag, so it does not remain.
	 */
	public function test_comparison_text_is_not_treated_as_a_tag() {
		$srt = "1\n00:00:01,000 --> 00:00:02,000\na < b and <3\n";

		$this->assertSame( 'a < b and', YouTube::captions_to_text( $srt ) );
	}

	/**
	 * The sermon page prints the transcript through escape_transcript().
	 * wp_kses_post() deleted everything from a "<" through the next ">", so
	 * "1 < 2 && 3 > 2" was shown as "1  2" even though the saved meta, the
	 * REST field, and the admin pop-up still had the full sentence. Escaping
	 * the whole string keeps the comparison and does not render markup.
	 */
	public function test_public_transcript_escapes_literal_angle_brackets() {
		$html = YouTube::escape_transcript( '1 < 2 && 3 > 2' );

		$this->assertSame( '1 &lt; 2 &amp;&amp; 3 &gt; 2', $html );
		$this->assertStringNotContainsString( '<', $html );
	}

	/**
	 * Real tags, including an unclosed fragment and a script element, are
	 * escaped rather than rendered. The stored characters stay readable.
	 */
	public function test_public_transcript_does_not_render_markup_or_script() {
		$html = YouTube::escape_transcript( "Hello <img src=x onerror=alert(1)\n<p>Next</p><script>alert(1)</script>" );

		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( '<p>', $html );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringContainsString( 'Hello', $html );
		$this->assertStringContainsString( '&lt;p&gt;Next&lt;/p&gt;', $html );
		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $html );
	}

	/**
	 * The classic sermon form is not multipart, so choosing a caption file and
	 * clicking Update posted an empty $_FILES entry.
	 */
	public function test_sermon_edit_form_accepts_caption_files() {
		if ( ! defined( 'CP_LIBRARY_UPREFIX' ) ) {
			define( 'CP_LIBRARY_UPREFIX', 'cpl' );
		}

		Functions\when( 'get_current_screen' )->justReturn( (object) array( 'post_type' => 'cpl_item' ) );

		$youtube = ( new \ReflectionClass( YouTube::class ) )->newInstanceWithoutConstructor();

		ob_start();
		$youtube->add_caption_form_enctype();
		$this->assertSame( ' enctype="multipart/form-data"', ob_get_clean() );
	}

	public function test_other_post_types_do_not_get_the_caption_enctype() {
		if ( ! defined( 'CP_LIBRARY_UPREFIX' ) ) {
			define( 'CP_LIBRARY_UPREFIX', 'cpl' );
		}

		Functions\when( 'get_current_screen' )->justReturn( (object) array( 'post_type' => 'post' ) );

		$youtube = ( new \ReflectionClass( YouTube::class ) )->newInstanceWithoutConstructor();

		ob_start();
		$youtube->add_caption_form_enctype();
		$this->assertSame( '', ob_get_clean() );
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

	/**
	 * YouTube timedtext is already entity-encoded. An apostrophe often arrives
	 * as &#39;, and the ampersand in that character reference is often escaped
	 * again, so the XML text is &amp;#39;. parse_caption_xml stored that raw
	 * capture, and import_transcript wrote it to post meta with no decode, so
	 * the saved transcript contained how&amp;#39;s. Markup is stripped after one
	 * decode and before the final decode, so a decoded "<" stays text. Do not
	 * run htmlspecialchars on the way in. (t:N) markers are added from the cue
	 * offset and must stay literal. Normalizing the result again does not
	 * change it.
	 *
	 * @dataProvider caption_entity_texts
	 */
	public function test_caption_entities_are_decoded_and_not_encoded_again( $raw, $expected ) {
		$this->assertSame( $expected, YouTube::normalize_caption_text( $raw ) );
		$this->assertSame( $expected, YouTube::normalize_caption_text( $expected ) );
	}

	public function caption_entity_texts() {
		return [
			'numeric apostrophe'        => [ 'how&#39;s', "how's" ],
			'double-encoded apostrophe' => [ 'how&amp;#39;s', "how's" ],
			'ampersand'                  => [ 'Faith &amp; works', 'Faith & works' ],
			'double-encoded ampersand'   => [ 'Tom &amp;amp; Jerry', 'Tom & Jerry' ],
			'encoded apostrophe only'    => [ '&amp;#39;', "'" ],
			'double quote'               => [ 'say &quot;hello&quot;', 'say "hello"' ],
			'plain apostrophe'           => [ "how's", "how's" ],
			'less-than before a digit'   => [ '5 &amp;lt;6 then more', '5 <6 then more' ],
			'less-than before a letter'  => [ 'if a&amp;lt;b we go', 'if a<b we go' ],
			'escaped tag is literal'     => [ '&amp;lt;b&amp;gt;hi', '<b>hi' ],
			'real font markup'           => [ '<font color="x">hi</font>', 'hi' ],
			'nbsp only'                  => [ '&nbsp;', '' ],
		];
	}

	/**
	 * The saved transcript is "(t:N) " plus the decoded cue. Fractional offsets
	 * are floored, and a second pass over decoded text must not turn "'" or
	 * "&" back into entities. A "(t:N)" that is spoken text stays spoken text.
	 */
	public function test_imported_transcript_decodes_entities_and_keeps_timestamp_markers() {
		$xml = '<transcript>'
			. '<text start="6.4" dur="2.0">how&amp;#39;s everyone doing</text>'
			. '<text start="8" dur="1.0">good</text>'
			. '<text start="11.9" dur="2.0">awesome it&#39;s &quot;good&quot; &amp; fine (t:3)</text>'
			. '<text start="14" dur="1.0">   </text>'
			. '</transcript>';

		$saved = YouTube::format_imported_transcript( YouTube::parse_caption_xml( $xml ) );

		$this->assertSame(
			'(t:6) how\'s everyone doing (t:8) good (t:11) awesome it\'s "good" & fine (t:3)',
			$saved
		);
		$this->assertStringNotContainsString( '&amp;', $saved );
		$this->assertStringNotContainsString( '&#39;', $saved );
		$this->assertStringNotContainsString( '&quot;', $saved );
		$this->assertStringNotContainsString( '(t:6.4)', $saved );
		$this->assertStringNotContainsString( '(t:11.9)', $saved );

		$again = YouTube::format_imported_transcript( [
			[
				'offset' => '6',
				'text'   => "how's everyone doing",
			],
			[
				'offset' => '8',
				'text'   => 'good',
			],
		] );

		$this->assertSame( "(t:6) how's everyone doing (t:8) good", $again );
	}

	/**
	 * A literal "<" in a cue used to end the saved text, and a second
	 * normalize turned an escaped "<b>hi" into "hi". A cue that is only a
	 * no-break space used to leave an empty "(t:N)" marker.
	 */
	public function test_imported_transcript_keeps_literal_angle_brackets_and_skips_nbsp() {
		$xml = '<transcript>'
			. '<text start="1.2" dur="2">5 &amp;lt;6 then more</text>'
			. '<text start="4" dur="1">if a&amp;lt;b we go</text>'
			. '<text start="6" dur="1">&amp;lt;b&amp;gt;hi</text>'
			. '<text start="8" dur="1">Tom &amp;amp; Jerry</text>'
			. '<text start="9" dur="1">&amp;#39;</text>'
			. '<text start="10" dur="1"><font color="x">hi</font></text>'
			. '<text start="12" dur="1">&nbsp;</text>'
			. '</transcript>';

		$cues  = YouTube::parse_caption_xml( $xml );
		$saved = YouTube::format_imported_transcript( $cues );

		$this->assertSame(
			'(t:1) 5 <6 then more (t:4) if a<b we go (t:6) <b>hi (t:8) Tom & Jerry (t:9) \' (t:10) hi',
			$saved
		);
		$this->assertStringNotContainsString( '(t:12)', $saved );
		$this->assertStringNotContainsString( "\u{00A0}", $saved );

		$again = YouTube::format_imported_transcript( $cues );

		$this->assertSame( $saved, $again );
		$this->assertSame( '<b>hi', YouTube::normalize_caption_text( '<b>hi' ) );
	}

	/**
	 * floor() throws a TypeError on PHP 8 when the offset is not numeric.
	 * An empty string or a clock-style value becomes 0. A numeric string
	 * still floors.
	 */
	public function test_non_numeric_cue_offsets_default_to_zero() {
		$saved = YouTube::format_imported_transcript(
			array(
				array(
					'offset' => '',
					'text'   => 'Blank',
				),
				array(
					'offset' => '1:05',
					'text'   => 'Clock',
				),
				array(
					'offset' => '6.4',
					'text'   => 'Fine',
				),
			)
		);

		$this->assertSame( '(t:0) Blank (t:0) Clock (t:6) Fine', $saved );
	}

	/**
	 * A cue whose text is only U+00A0 is not an empty "(t:N)" marker.
	 */
	public function test_nbsp_cue_text_is_skipped_without_a_timestamp_marker() {
		$saved = YouTube::format_imported_transcript(
			array(
				array(
					'offset' => '3',
					'text'   => "\u{00A0}",
				),
				array(
					'offset' => '5',
					'text'   => 'Hello',
				),
			)
		);

		$this->assertSame( '(t:5) Hello', $saved );
	}

	/**
	 * cpl_fetch_transcript is documented as array|WP_Error. A WP_Error, a
	 * non-array, or cues that produce no text must come back as a WP_Error.
	 * The sermon meta is left alone and cpl_imported_transcript does not fire.
	 *
	 * @dataProvider fetch_results_that_must_not_save
	 */
	public function test_fetch_filter_errors_do_not_save_or_fire_the_import_action( $filter_result ) {
		$log     = $this->track_import_side_effects();
		$youtube = $this->youtube_ready_to_import( $filter_result );

		$result = $youtube->import_transcript( 15 );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( array(), $log->meta );
		$this->assertNotContains( 'cpl_imported_transcript', $log->actions );

		if ( is_wp_error( $filter_result ) ) {
			$this->assertSame( $filter_result, $result );
		} else {
			$this->assertSame( 'youtube_captions_empty', $result->get_error_code() );
		}
	}

	public function fetch_results_that_must_not_save() {
		return array(
			'wp error'    => array( new \WP_Error( 'fetch_failed', 'no captions' ) ),
			'null'        => array( null ),
			'string'      => array( 'not-cues' ),
			'empty array' => array( array() ),
			'nbsp only'   => array(
				array(
					array(
						'offset' => '3',
						'text'   => "\u{00A0}",
					),
				),
			),
		);
	}

	public function test_a_fetched_transcript_is_saved_and_the_import_action_fires() {
		$log     = $this->track_import_side_effects();
		$cues    = array(
			array(
				'offset' => '6.4',
				'text'   => "how's everyone",
			),
		);
		$youtube = $this->youtube_ready_to_import( $cues );

		$result = $youtube->import_transcript( 15 );

		$this->assertTrue( $result );
		$this->assertSame(
			array(
				array( 15, 'transcript', "(t:6) how's everyone" ),
			),
			$log->meta
		);
		$this->assertSame( array( 'cpl_imported_transcript' ), $log->actions );
	}

	/**
	 * @param mixed $filter_result Value the cpl_fetch_transcript filter returns.
	 * @return TranscriptImportYouTube
	 */
	private function youtube_ready_to_import( $filter_result ) {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'is_wp_error' )->alias(
			static function ( $thing ) {
				return $thing instanceof \WP_Error;
			}
		);
		Functions\when( 'apply_filters' )->alias(
			static function ( $tag, $value ) use ( $filter_result ) {
				if ( 'cpl_fetch_transcript' === $tag ) {
					return $filter_result;
				}

				return $value;
			}
		);

		$youtube           = new TranscriptImportYouTube();
		$youtube->fetched  = array(
			array(
				'text'   => 'Hello',
				'offset' => '1',
			),
		);

		return $youtube;
	}

	/**
	 * @return object{meta: array, actions: array}
	 */
	private function track_import_side_effects() {
		$log = (object) array(
			'meta'    => array(),
			'actions' => array(),
		);

		Functions\when( 'update_post_meta' )->alias(
			static function () use ( $log ) {
				$log->meta[] = func_get_args();

				return true;
			}
		);
		Functions\when( 'do_action' )->alias(
			static function ( $hook ) use ( $log ) {
				$log->actions[] = $hook;
			}
		);

		return $log;
	}
}

/**
 * Serves a fixed video URL and a fixed fetch result so import_transcript()
 * can be exercised without WordPress or the item tables.
 */
class TranscriptImportYouTube extends YouTube {

	/** @var mixed */
	public $fetched;

	public function __construct() {
	}

	public function fetch_youtube_transcript( $video_id ) {
		return $this->fetched;
	}

	protected function item_for_import( $post_id ) {
		return new TranscriptImportItem();
	}
}

/**
 * Stand-in for the sermon item.
 */
class TranscriptImportItem {

	/** @var TranscriptImportItemModel */
	public $model;

	public function __construct() {
		$this->model = new TranscriptImportItemModel();
	}
}

/**
 * Stand-in for the item model.
 */
class TranscriptImportItemModel {

	public function get_meta_value( $key ) {
		return 'https://www.youtube.com/watch?v=jNQXAC9IVRw';
	}
}
