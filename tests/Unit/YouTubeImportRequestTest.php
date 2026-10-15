<?php
/**
 * Tests for the YouTube transcript import request.
 *
 * The handler used to read a post id from the request and import straight
 * away. A request must now carry a matching nonce, the user must be able to
 * edit the target item, and the id must be a positive integer for an item.
 * A failed check stops before anything is imported. Bulk import from the list
 * screen requires edit_posts, and each target still requires edit_post.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Library\Integrations\YouTube;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Skips the constructor, which registers hooks. Records import attempts.
 */
class ImportRequestYouTube extends YouTube {

	/** @var int[] */
	public $imported = array();

	public function import_transcript( $post_id ) {
		$this->imported[] = $post_id;

		return true;
	}
}

/**
 * @covers \CP_Library\Integrations\YouTube::handle_import_request
 * @covers \CP_Library\Integrations\YouTube::handle_bulk_actions
 * @covers \CP_Library\Integrations\YouTube::get_import_request_url
 */
class YouTubeImportRequestTest extends TestCase {

	/** @var ImportRequestYouTube */
	private $youtube;

	/** @var array */
	private $errors = array();

	/** @var mixed */
	private $success;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$_REQUEST     = array();
		$this->errors = array();
		$this->success = null;

		$this->youtube = ( new ReflectionClass( ImportRequestYouTube::class ) )->newInstanceWithoutConstructor();

		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'is_wp_error' )->alias(
			static function ( $thing ) {
				return $thing instanceof \WP_Error;
			}
		);
		Functions\when( 'wp_send_json_error' )->alias(
			function ( $data = null ) {
				$this->errors[] = $data;
			}
		);
		Functions\when( 'wp_send_json_success' )->alias(
			function ( $data = null ) {
				$this->success = $data;
			}
		);
	}

	protected function tearDown(): void {
		$_REQUEST = array();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_missing_post_id_stops_before_import() {
		$this->youtube->handle_import_request();

		$this->assertSame( array(), $this->youtube->imported );
		$this->assertSame( array( 'No post ID provided' ), $this->errors );
	}

	/**
	 * @dataProvider rejected_post_ids
	 */
	public function test_non_numeric_post_id_stops_before_import( $raw ) {
		$_REQUEST['post_id'] = $raw;

		$this->youtube->handle_import_request();

		$this->assertSame( array(), $this->youtube->imported );
		$this->assertSame( array( 'No post ID provided' ), $this->errors );
	}

	public function rejected_post_ids() {
		return array(
			'empty string' => array( '' ),
			'zero'         => array( '0' ),
			'negative'     => array( '-5' ),
			'mixed text'   => array( '42abc' ),
			'array'        => array( array( '42' ) ),
		);
	}

	public function test_missing_nonce_stops_before_import() {
		$_REQUEST['post_id'] = '42';
		Functions\when( 'wp_verify_nonce' )->justReturn( false );

		$this->youtube->handle_import_request();

		$this->assertSame( array(), $this->youtube->imported );
		$this->assertSame( array( 'The request could not be verified.' ), $this->errors );
	}

	public function test_wrong_nonce_stops_before_import() {
		$_REQUEST['post_id']  = '42';
		$_REQUEST['_wpnonce'] = 'nope';
		Functions\when( 'wp_verify_nonce' )->alias(
			static function ( $nonce, $action ) {
				return 'good' === $nonce && 'cpl_import_transcript_42' === $action;
			}
		);

		$this->youtube->handle_import_request();

		$this->assertSame( array(), $this->youtube->imported );
		$this->assertSame( array( 'The request could not be verified.' ), $this->errors );
	}

	public function test_user_who_cannot_edit_the_item_stops_before_import() {
		$_REQUEST['post_id']  = '42';
		$_REQUEST['_wpnonce'] = 'good';
		Functions\when( 'wp_verify_nonce' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( false );

		$this->youtube->handle_import_request();

		$this->assertSame( array(), $this->youtube->imported );
		$this->assertSame( array( 'You are not allowed to edit this item.' ), $this->errors );
	}

	public function test_verified_editor_import_runs() {
		$this->allow_item( 42 );

		$this->youtube->handle_import_request();

		$this->assertSame( array( 42 ), $this->youtube->imported );
		$this->assertTrue( $this->success );
		$this->assertSame( array(), $this->errors );
	}

	public function test_verified_request_for_a_non_item_stops_before_import() {
		$_REQUEST['post_id']  = '42';
		$_REQUEST['_wpnonce'] = 'good';
		Functions\when( 'wp_verify_nonce' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_post' )->justReturn( (object) array( 'ID' => 42, 'post_type' => 'post' ) );

		$this->youtube->handle_import_request();

		$this->assertSame( array(), $this->youtube->imported );
		$this->assertSame( array( 'Invalid item.' ), $this->errors );
	}

	public function test_import_url_includes_the_nonce() {
		Functions\when( 'wp_create_nonce' )->alias(
			static function ( $action ) {
				return 'nonce-for-' . $action;
			}
		);
		Functions\when( 'admin_url' )->justReturn( 'https://example.test/wp-admin/admin-post.php' );
		Functions\when( 'add_query_arg' )->alias(
			static function ( $args, $url ) {
				return $url . '?' . http_build_query( $args );
			}
		);

		$url = $this->youtube->get_import_request_url( 42 );

		$this->assertStringContainsString( 'cp_action=cpl_import_transcript', $url );
		$this->assertStringContainsString( 'post_id=42', $url );
		$this->assertStringContainsString( '_wpnonce=nonce-for-cpl_import_transcript_42', $url );
	}

	public function test_unrelated_bulk_action_is_left_alone() {
		$redirect = $this->youtube->handle_bulk_actions( 'https://example.test/edit.php', 'trash', array( 5 ) );

		$this->assertSame( 'https://example.test/edit.php', $redirect );
		$this->assertSame( array(), $this->youtube->imported );
	}

	public function test_bulk_without_edit_posts_imports_nothing() {
		Functions\when( 'current_user_can' )->justReturn( false );

		$redirect = $this->youtube->handle_bulk_actions( 'https://example.test/edit.php', 'cpl_import_transcript', array( 5, 9 ) );

		$this->assertSame( 'https://example.test/edit.php', $redirect );
		$this->assertSame( array(), $this->youtube->imported );
	}

	public function test_bulk_imports_only_items_the_user_can_edit() {
		Functions\when( 'current_user_can' )->alias(
			static function ( $cap, $post_id = 0 ) {
				if ( 'edit_posts' === $cap ) {
					return true;
				}

				return 'edit_post' === $cap && 5 === (int) $post_id;
			}
		);
		Functions\when( 'add_query_arg' )->alias(
			static function ( $key, $value, $url ) {
				return $url . '?' . $key . '=' . $value;
			}
		);

		$redirect = $this->youtube->handle_bulk_actions(
			'https://example.test/edit.php',
			'cpl_import_transcript',
			array( '5', '9', 'nope', array( '3' ) )
		);

		$this->assertSame( array( 5 ), $this->youtube->imported );
		$this->assertSame( 'https://example.test/edit.php?cpl_import_transcript=1', $redirect );
	}

	/**
	 * Stub a request that should be allowed through to import.
	 *
	 * @param int $post_id Post ID.
	 */
	private function allow_item( $post_id ) {
		$_REQUEST['post_id']  = (string) $post_id;
		$_REQUEST['_wpnonce'] = 'good';

		Functions\when( 'wp_verify_nonce' )->alias(
			static function ( $nonce, $action ) use ( $post_id ) {
				return 'good' === $nonce && 'cpl_import_transcript_' . $post_id === $action;
			}
		);
		Functions\when( 'current_user_can' )->alias(
			static function ( $cap, $id = 0 ) use ( $post_id ) {
				return 'edit_post' === $cap && (int) $post_id === (int) $id;
			}
		);
		Functions\when( 'get_post' )->alias(
			static function ( $id ) use ( $post_id ) {
				return (int) $id === (int) $post_id
					? (object) array( 'ID' => (int) $post_id, 'post_type' => 'cpl_item' )
					: null;
			}
		);
	}
}
