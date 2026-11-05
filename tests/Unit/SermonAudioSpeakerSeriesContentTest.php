<?php
/**
 * A blank SermonAudio bio must not wipe a hand-written speaker or series.
 *
 * Name-linking copies SermonAudio's name, bio or description, and image onto
 * the local post. The image was already omitted when SermonAudio had none, so
 * a local portrait survived. The bio was not: an empty feed bio was stored as
 * post_content and replaced the hand-written one, on the link and on every
 * later sync. A blank feed value now keeps the stored text. A non-blank value
 * still replaces it. The image rules are unchanged.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Library\Adapters\SermonAudio;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

require_once dirname( __DIR__ ) . '/fixtures/wp-post-stub.php';

/**
 * @covers \CP_Library\Adapters\SermonAudio::text_to_store_for_speaker_or_series
 * @covers \CP_Library\Adapters\SermonAudio::insert_imported_post
 * @covers \CP_Library\Adapters\SermonAudio::format_speaker
 * @covers \CP_Library\Adapters\SermonAudio::format_item_type
 */
class SermonAudioSpeakerSeriesContentTest extends TestCase {

	/** @var SermonAudio */
	private $adapter;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( 'cp_library' )->justReturn( $this->library() );

		$this->adapter = ( new ReflectionClass( SermonAudio::class ) )->newInstanceWithoutConstructor();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_an_empty_feed_bio_keeps_the_local_bio() {
		$local = "It's a \\ path";
		$saved = $this->save_speaker(
			array(
				'ID'           => 15,
				'post_title'   => 'Pastor John',
				'post_content' => '',
			),
			$local
		);

		$this->assertSame( addslashes( $local ), $saved['post_content'] );
		$this->assertSame( 'Pastor John', $saved['post_title'] );
	}

	public function test_a_whitespace_feed_bio_keeps_the_local_bio() {
		$saved = $this->save_speaker(
			array(
				'ID'           => 15,
				'post_title'   => 'John Smith',
				'post_content' => " \n\t ",
			),
			'Hand-written bio'
		);

		$this->assertSame( 'Hand-written bio', $saved['post_content'] );
	}

	public function test_a_feed_bio_replaces_the_local_bio() {
		$saved = $this->save_speaker(
			array(
				'ID'           => 15,
				'post_title'   => 'John Smith',
				'post_content' => "SermonAudio's bio",
			),
			'Hand-written bio'
		);

		$this->assertSame( "SermonAudio's bio", $saved['post_content'] );
	}

	public function test_an_empty_feed_description_keeps_the_local_description() {
		$saved = $this->save_series(
			array(
				'ID'           => 22,
				'post_title'   => 'Romans',
				'post_content' => '',
			),
			'Hand-written description'
		);

		$this->assertSame( 'Hand-written description', $saved['post_content'] );
	}

	public function test_a_feed_description_replaces_the_local_description() {
		$saved = $this->save_series(
			array(
				'ID'           => 22,
				'post_title'   => 'Romans',
				'post_content' => 'From SermonAudio',
			),
			'Hand-written description'
		);

		$this->assertSame( 'From SermonAudio', $saved['post_content'] );
	}

	public function test_a_blank_feed_name_does_not_keep_the_local_bio() {
		$saved = $this->save_series(
			array(
				'ID'           => 22,
				'post_title'   => '',
				'post_content' => '',
			),
			'Hand-written description'
		);

		$this->assertSame( '', $saved['post_content'] );
	}

	public function test_a_new_speaker_with_an_empty_bio_stores_an_empty_bio() {
		$saved = $this->save_speaker(
			array(
				'post_title'   => 'John Smith',
				'post_content' => '',
			),
			'unused'
		);

		$this->assertSame( '', $saved['post_content'] );
		$this->assertArrayNotHasKey( 'ID', $saved );
	}

	public function test_text_to_store_keeps_local_text_only_when_the_feed_bio_is_blank_and_the_feed_has_a_name() {
		$kept = SermonAudio::text_to_store_for_speaker_or_series( '', 'Hand-written bio', 'John Smith' );
		$this->assertSame( 'Hand-written bio', $kept['text'] );
		$this->assertTrue( $kept['slash'] );

		$blank = SermonAudio::text_to_store_for_speaker_or_series( null, 'Hand-written bio', 'John Smith' );
		$this->assertSame( 'Hand-written bio', $blank['text'] );
		$this->assertTrue( $blank['slash'] );

		$replaced = SermonAudio::text_to_store_for_speaker_or_series( '  SA bio  ', 'Hand-written bio', 'John Smith' );
		$this->assertSame( '  SA bio  ', $replaced['text'] );
		$this->assertFalse( $replaced['slash'] );

		$inserted = SermonAudio::text_to_store_for_speaker_or_series( '', null, 'John Smith' );
		$this->assertSame( '', $inserted['text'] );
		$this->assertFalse( $inserted['slash'] );

		// A blank name must leave a blank bio blank. A series with no title
		// and no description is rejected, so its stored description stays
		// only because the update does not run.
		$empty_payload = SermonAudio::text_to_store_for_speaker_or_series( '', 'Hand-written description', '' );
		$this->assertSame( '', $empty_payload['text'] );
		$this->assertFalse( $empty_payload['slash'] );

		$whitespace_name = SermonAudio::text_to_store_for_speaker_or_series( '', 'Hand-written description', '   ' );
		$this->assertSame( '', $whitespace_name['text'] );
		$this->assertFalse( $whitespace_name['slash'] );
	}

	public function test_an_empty_feed_image_is_omitted_and_a_feed_image_is_kept() {
		$no_portrait = $this->adapter->format_speaker( $this->speaker( '', '' ) );
		$this->assertSame( '', $no_portrait['post_content'] );
		$this->assertArrayNotHasKey( 'featured_image', $no_portrait['cpl_data'] );

		$portrait = $this->adapter->format_speaker( $this->speaker( 'SA bio', 'https://cdn.example/portrait.jpg' ) );
		$this->assertSame( 'SA bio', $portrait['post_content'] );
		$this->assertSame( 'https://cdn.example/portrait.jpg', $portrait['cpl_data']['featured_image'] );

		$no_series_image = $this->adapter->format_item_type( $this->series( '', '', '' ) );
		$this->assertArrayNotHasKey( 'featured_image', $no_series_image['cpl_data'] );

		$series_image = $this->adapter->format_item_type( $this->series( '', 'https://cdn.example/series.jpg', 'https://cdn.example/broadcaster.jpg' ) );
		$this->assertSame( 'https://cdn.example/series.jpg', $series_image['cpl_data']['featured_image'] );

		$broadcaster_image = $this->adapter->format_item_type( $this->series( 'From SermonAudio', '', 'https://cdn.example/broadcaster.jpg' ) );
		$this->assertSame( 'From SermonAudio', $broadcaster_image['post_content'] );
		$this->assertSame( 'https://cdn.example/broadcaster.jpg', $broadcaster_image['cpl_data']['featured_image'] );
	}

	/**
	 * @param array  $item
	 * @param string $local_content
	 * @return array
	 */
	private function save_speaker( array $item, $local_content ) {
		$item['post_type']   = 'cpl_speaker';
		$item['post_status'] = 'publish';

		return $this->save( $item, $local_content );
	}

	/**
	 * @param array  $item
	 * @param string $local_content
	 * @return array
	 */
	private function save_series( array $item, $local_content ) {
		$item['post_type']   = 'cpl_item_type';
		$item['post_status'] = 'publish';

		return $this->save( $item, $local_content );
	}

	/**
	 * @param array  $item
	 * @param string $local_content Stored post_content. Ignored when $item has no ID.
	 * @return array Fields passed to wp_insert_post().
	 */
	private function save( array $item, $local_content ) {
		$post                = new \WP_Post();
		$post->ID            = isset( $item['ID'] ) ? (int) $item['ID'] : 0;
		$post->post_content  = $local_content;
		$post->post_type     = $item['post_type'];
		$post->post_status   = 'publish';
		$post->post_name     = 'existing-slug';

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'wp_slash' )->alias( 'addslashes' );

		$saved = null;
		Functions\when( 'wp_insert_post' )->alias(
			function ( $postarr ) use ( &$saved ) {
				$saved = $postarr;

				return isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 50;
			}
		);

		$method = new ReflectionMethod( SermonAudio::class, 'insert_imported_post' );
		$method->setAccessible( true );
		$method->invoke( $this->adapter, $item );

		$this->assertIsArray( $saved );

		return $saved;
	}

	/**
	 * @param string $bio
	 * @param string $portrait
	 * @return object
	 */
	private function speaker( $bio, $portrait ) {
		return (object) array(
			'speakerID'   => 'sa-spk',
			'displayName' => 'John Smith',
			'bio'         => $bio,
			'portraitURL' => $portrait,
		);
	}

	/**
	 * @param string $description
	 * @param string $image
	 * @param string $broadcaster_image
	 * @return object
	 */
	private function series( $description, $image, $broadcaster_image ) {
		return (object) array(
			'seriesID'    => 'sa-ser',
			'title'       => 'Romans',
			'description' => $description,
			'image'       => $image,
			'broadcaster' => (object) array( 'imageURL' => $broadcaster_image ),
		);
	}

	/**
	 * @return object
	 */
	private function library() {
		$item                 = new \stdClass();
		$item->post_type      = 'cpl_item';
		$speaker              = new \stdClass();
		$speaker->post_type   = 'cpl_speaker';
		$series               = new \stdClass();
		$series->post_type    = 'cpl_item_type';
		$post_types            = new \stdClass();
		$post_types->item      = $item;
		$post_types->speaker   = $speaker;
		$post_types->item_type = $series;
		$setup                = new \stdClass();
		$setup->post_types    = $post_types;
		$library              = new \stdClass();
		$library->setup       = $setup;

		return $library;
	}
}
