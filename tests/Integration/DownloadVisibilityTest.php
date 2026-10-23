<?php
/**
 * Audio downloads only serve sermons the visitor could read.
 *
 * Download::process_download() used to serve the audio for any item id it
 * was given, so a visitor could fetch the audio of a draft, private,
 * scheduled or password-protected sermon by guessing its id. It now uses the
 * same check as reading a single sermon over the REST API.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Integration;

use CP_Library\Download;

/**
 * Marks a download that was refused with wp_die().
 */
class DownloadRefused extends \RuntimeException {

	/** @var int */
	public $status;

	/**
	 * @param int $status HTTP status.
	 */
	public function __construct( $status ) {
		$this->status = (int) $status;
		parent::__construct( 'download refused' );
	}
}

/**
 * @covers \CP_Library\Download::item_is_downloadable
 * @covers \CP_Library\Download::process_download
 */
class DownloadVisibilityTest extends TestCase {

	/** @var array */
	private $previous_get;

	public function set_up() {
		parent::set_up();
		$this->previous_get = $_GET;
	}

	public function tear_down() {
		$_GET = $this->previous_get;
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	public function test_published_sermon_is_downloadable_by_a_visitor() {
		$this->assertTrue( Download::item_is_downloadable( $this->sermon( 'publish' ) ) );
	}

	public function test_draft_sermon_is_not_downloadable_by_a_visitor() {
		$this->assertFalse( Download::item_is_downloadable( $this->sermon( 'draft' ) ) );
	}

	public function test_private_sermon_is_not_downloadable_by_a_visitor() {
		$this->assertFalse( Download::item_is_downloadable( $this->sermon( 'private' ) ) );
	}

	public function test_scheduled_sermon_is_not_downloadable_by_a_visitor() {
		$this->assertFalse( Download::item_is_downloadable( $this->sermon( 'future' ) ) );
	}

	public function test_password_protected_sermon_is_not_downloadable_by_a_visitor() {
		$this->assertFalse( Download::item_is_downloadable( $this->sermon( 'publish', array( 'post_password' => 'secret' ) ) ) );
	}

	public function test_variation_under_a_draft_sermon_is_not_downloadable_by_a_visitor() {
		$parent_id = $this->sermon( 'draft' );
		$child_id  = $this->sermon( 'publish', array( 'post_parent' => $parent_id ) );

		$this->assertFalse( Download::item_is_downloadable( $child_id ) );
	}

	public function test_draft_sermon_is_downloadable_by_an_editor() {
		$post_id = $this->sermon( 'draft' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertTrue( Download::item_is_downloadable( $post_id ) );
	}

	public function test_other_post_types_are_not_downloadable() {
		$this->assertFalse( Download::item_is_downloadable( self::factory()->post->create() ) );
	}

	public function test_download_request_for_a_draft_sermon_is_refused() {
		$post_id = $this->sermon( 'draft' );
		$_GET    = array(
			'item_id' => (string) $post_id,
			'key'     => 'audio',
		);

		$served = false;
		$serve  = function () use ( &$served ) {
			$served = true;
			throw new DownloadRefused( 200 );
		};

		// Another test may have defined DOING_AJAX, which routes wp_die() through the ajax handler.
		add_filter( 'wp_die_handler', array( $this, 'die_handler' ) );
		add_filter( 'wp_die_ajax_handler', array( $this, 'die_handler' ) );
		add_action( 'cp_process_verified_download', $serve );

		try {
			Download::get_instance()->process_download();
			$this->fail( 'Download did not stop.' );
		} catch ( DownloadRefused $refused ) {
			$this->assertFalse( $served, 'The draft sermon passed the access check.' );
			$this->assertSame( 403, $refused->status );
		} finally {
			remove_filter( 'wp_die_handler', array( $this, 'die_handler' ) );
			remove_filter( 'wp_die_ajax_handler', array( $this, 'die_handler' ) );
			remove_action( 'cp_process_verified_download', $serve );
		}
	}

	/**
	 * @return callable
	 */
	public function die_handler() {
		return array( $this, 'refuse' );
	}

	/**
	 * @param mixed $message Message.
	 * @param mixed $title   Title.
	 * @param mixed $args    Arguments.
	 */
	public function refuse( $message = '', $title = '', $args = array() ) {
		$status = is_array( $args ) && isset( $args['response'] ) ? $args['response'] : 500;

		throw new DownloadRefused( $status );
	}

	/**
	 * @param string $status Post status.
	 * @param array  $args   Extra post fields.
	 * @return int
	 */
	private function sermon( $status, $args = array() ) {
		$item    = $this->make_item();
		$post_id = (int) $item->origin_id;
		$time    = 'future' === $status ? time() + WEEK_IN_SECONDS : time();

		$item->update_meta_value( 'audio_url', 'https://example.com/audio/sermon.mp3' );

		wp_update_post(
			array_merge(
				array(
					'ID'            => $post_id,
					'post_status'   => $status,
					'post_date'     => get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $time ) ),
					'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $time ),
				),
				$args
			)
		);
		clean_post_cache( $post_id );

		return $post_id;
	}
}
