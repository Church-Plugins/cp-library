<?php
/**
 * Tests for the filename placed on an audio download.
 *
 * The `name` query argument used to be built from the sermon title with only
 * letters, numbers and underscores, plus ".mp3". Original audio filenames can
 * include spaces, quotes and accented letters. The name that reaches
 * Content-Disposition is the `cp_requested_file_name` result passed through
 * `sanitize_file_name()`, so the extension stays on the name.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Library\Download;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \CP_Library\Download::requested_filename
 */
class DownloadFilenameTest extends TestCase {

	/** @var Download */
	private $download;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->download = ( new ReflectionClass( Download::class ) )->newInstanceWithoutConstructor();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_requested_name_is_cleaned_with_its_extension() {
		Functions\when( 'apply_filters' )->alias(
			static function ( $hook, $value ) {
				return $value;
			}
		);

		$received = null;
		Functions\when( 'sanitize_file_name' )->alias(
			static function ( $filename ) use ( &$received ) {
				$received = $filename;

				return 'Sunday-Sermon.mp3';
			}
		);

		$result = $this->download->requested_filename( 'Sunday Sermon.mp3', array( 'file_name' => 'Sunday Sermon.mp3' ) );

		$this->assertSame( 'Sunday Sermon.mp3', $received, 'the extension is still on the name passed to sanitize_file_name()' );
		$this->assertSame( 'Sunday-Sermon.mp3', $result );
	}

	public function test_plain_mp3_name_is_passed_through_unchanged() {
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'sanitize_file_name' )->returnArg();

		$this->assertSame(
			'sunday_morning.mp3',
			$this->download->requested_filename( 'sunday_morning.mp3', array() )
		);
	}

	public function test_filtered_name_is_cleaned_with_its_extension() {
		Functions\when( 'apply_filters' )->alias(
			static function ( $hook, $value ) {
				if ( 'cp_requested_file_name' === $hook ) {
					return 'Live "Recording".mp3';
				}

				return $value;
			}
		);

		$received = null;
		Functions\when( 'sanitize_file_name' )->alias(
			static function ( $filename ) use ( &$received ) {
				$received = $filename;

				return 'Live-Recording.mp3';
			}
		);

		$result = $this->download->requested_filename( 'fallback.mp3', array( 'file_name' => 'fallback.mp3' ) );

		$this->assertSame( 'Live "Recording".mp3', $received );
		$this->assertSame( 'Live-Recording.mp3', $result );
	}
}
