<?php
/**
 * Remotely hosted audio is redirected to, not streamed through the site.
 *
 * Before 1.8.0 the share menu linked remote audio straight to the file. Once
 * #139 sent every download through the site's endpoint, process_download()
 * passed remote URLs to deliver_download() without the redirect flag, so the
 * site proxied the whole file through PHP: large files hit gateway timeouts
 * and hosts without allow_url_fopen sent an empty file.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Integration;

use CP_Library\Download;
use ReflectionClass;

/**
 * Stops a download at the point it would be delivered.
 */
class DownloadDelivered extends \RuntimeException {

	/** @var string */
	public $requested;

	/** @var bool */
	public $redirect;

	/**
	 * @param string $file     Requested file.
	 * @param bool   $redirect Whether it would be redirected to.
	 */
	public function __construct( $file, $redirect ) {
		$this->requested = $file;
		$this->redirect  = (bool) $redirect;
		parent::__construct( 'download delivered' );
	}
}

/**
 * Download that reports how it would deliver the file instead of sending it.
 */
class RecordingDownload extends Download {

	/**
	 * @param string $file     File.
	 * @param bool   $redirect Redirect flag.
	 * @throws DownloadDelivered Always.
	 */
	public function deliver_download( $file = '', $redirect = false ) {
		throw new DownloadDelivered( $file, $redirect );
	}
}

/**
 * @covers \CP_Library\Download::process_download
 */
class RemoteAudioDownloadTest extends TestCase {

	/** @var array */
	private $previous_get;

	public function set_up() {
		parent::set_up();
		$this->previous_get = $_GET;
	}

	public function tear_down() {
		$_GET = $this->previous_get;
		parent::tear_down();
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_remote_audio_is_redirected_to() {
		$item    = $this->make_item();
		$post_id = (int) $item->origin_id;
		$audio   = 'https://cdn.example.com/audio/sunday-sermon.mp3';

		$item->update_meta_value( 'audio_url', $audio );
		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
		clean_post_cache( $post_id );

		$_GET = array(
			'item_id' => (string) $post_id,
			'key'     => 'audio',
			'name'    => 'sunday-sermon.mp3',
		);

		$download = ( new ReflectionClass( RecordingDownload::class ) )->newInstanceWithoutConstructor();

		try {
			$download->process_download();
			$this->fail( 'The download was not delivered.' );
		} catch ( DownloadDelivered $delivered ) {
			$this->assertSame( $audio, $delivered->requested );
			$this->assertTrue( $delivered->redirect, 'Remote audio was streamed through the site instead of redirected to.' );
		}
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_remote_redirect_location_uses_the_raw_query_string() {
		$item    = $this->make_item();
		$post_id = (int) $item->origin_id;
		$audio   = 'https://media.sermonaudio.com/audio/sermon.mp3?sermonID=123&download=true';

		$item->update_meta_value( 'audio_url', $audio );
		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
		clean_post_cache( $post_id );

		$shown = ( new \CP_Library\Controllers\Item( $post_id ) )->get_audio();
		$this->assertStringContainsString( '&amp;download=true', $shown, 'HTML output should keep the encoded query string.' );
		$this->assertStringNotContainsString( '&download=true', $shown );

		$_GET = array(
			'item_id' => (string) $post_id,
			'key'     => 'audio',
			'name'    => 'sermon.mp3',
		);

		$download = ( new ReflectionClass( RecordingDownload::class ) )->newInstanceWithoutConstructor();

		try {
			$download->process_download();
			$this->fail( 'The download was not delivered.' );
		} catch ( DownloadDelivered $delivered ) {
			$this->assertSame( $audio, $delivered->requested );
			$this->assertStringNotContainsString( '&amp;', $delivered->requested );
			$this->assertTrue( $delivered->redirect );
		}
	}
}
