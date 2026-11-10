<?php
/**
 * Merge Speakers must answer with JSON and must not warn.
 *
 * merge_speaker() read setup->taxonomies->speaker. That property does not
 * exist (speakers are a post type; the taxonomies object has scripture,
 * season, and topic). PHP warned at Tools.php, and with display_errors on
 * the warning was written out ahead of the JSON, including when WP_DEBUG
 * was off.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Integration;

use CP_Library\Admin\Tools;

/**
 * Marks a JSON response that ended the request.
 */
class MergeJsonEnded extends \RuntimeException {}

/**
 * @covers \CP_Library\Admin\Tools::merge_speakers
 * @covers \CP_Library\Admin\Tools::merge_speaker
 */
class MergeSpeakersResponseTest extends TestCase {

	/**
	 * @var array
	 */
	private $previous_post;

	public function set_up() {
		parent::set_up();

		$this->previous_post = $_POST;
		add_filter( 'wp_die_ajax_handler', array( $this, 'ajax_die_handler' ) );
	}

	public function tear_down() {
		$_POST = $this->previous_post;
		remove_filter( 'wp_die_ajax_handler', array( $this, 'ajax_die_handler' ) );
		parent::tear_down();
	}

	public function test_merge_speakers_returns_json_without_undefined_property_warnings() {
		$this->make_speaker( 'Pat Example' );
		$this->make_speaker( 'Pat Example' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST['nonce'] = wp_create_nonce( 'cpl_merge_speakers' );

		$warnings = array();
		set_error_handler(
			function ( $errno, $errstr, $errfile, $errline ) use ( &$warnings ) {
				$warnings[] = array( $errno, $errstr, $errfile, $errline );
				return true;
			}
		);

		try {
			$decoded = $this->call_json(
				function () {
					Tools::get_instance()->merge_speakers();
				}
			);
		} finally {
			restore_error_handler();
		}

		$property_warnings = array_values(
			array_filter(
				$warnings,
				function ( $warning ) {
					return false !== strpos( $warning[1], 'Undefined property' )
						|| false !== strpos( $warning[1], 'taxonomies' );
				}
			)
		);

		$this->assertSame( array(), $property_warnings );
		$this->assertTrue( $decoded['success'] );
		$this->assertArrayHasKey( 'html', $decoded['data'] );

		global $wpdb;
		$remaining = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s AND post_status = 'publish'",
				'cpl_speaker',
				'Pat Example'
			)
		);
		$this->assertSame( '1', (string) $remaining );
	}

	/**
	 * @param callable $callback Callback.
	 * @return array
	 */
	private function call_json( $callback ) {
		if ( ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}

		ob_start();

		try {
			$callback();
			$body = ob_get_clean();
			$this->fail( 'Merge did not end the request. Body: ' . $body );
		} catch ( MergeJsonEnded $ended ) {
			$body = ob_get_clean();
		}

		$this->assertStringNotContainsString( 'Warning:', $body );
		$decoded = json_decode( $body, true );
		$this->assertIsArray( $decoded, 'Response was not JSON: ' . $body );

		return $decoded;
	}

	/**
	 * @param callable $handler Current handler.
	 * @return callable
	 */
	public function ajax_die_handler( $handler ) {
		return array( $this, 'end_json' );
	}

	/**
	 * @param mixed $message Message.
	 * @param mixed $title   Title.
	 * @param mixed $args    Arguments.
	 */
	public function end_json( $message = '', $title = '', $args = array() ) {
		throw new MergeJsonEnded();
	}
}
