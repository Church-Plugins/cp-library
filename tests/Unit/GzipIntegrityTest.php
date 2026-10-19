<?php
/**
 * Tests for the gzip completeness check on import files.
 *
 * A truncated gzip stream ends silently: readers just stop, with no warning and
 * no error. An upload cut short in transfer therefore looked like a complete but
 * smaller export — the importer walked the records it could read, hit "EOF",
 * deleted the working file and reported "Import complete — N records processed,
 * 0 errors" over a fraction of the library.
 *
 * gzip records the uncompressed length in the last four bytes of the file, so
 * comparing that against the bytes actually inflated is what catches it. These
 * tests build real gzip data rather than fixtures so the trailer is genuine.
 *
 * Pure filesystem work — no WP functions are reached on these paths.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use CP_Library\Admin\ImportExport\Util;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Library\Admin\ImportExport\Util::is_gzip
 * @covers \CP_Library\Admin\ImportExport\Util::gzip_uncompressed_size
 * @covers \CP_Library\Admin\ImportExport\Util::gzip_is_complete
 */
class GzipIntegrityTest extends TestCase {

	/** @var string[] Paths to clean up. */
	private $temp = [];

	protected function tearDown(): void {
		foreach ( $this->temp as $path ) {
			if ( file_exists( $path ) ) {
				unlink( $path );
			}
		}

		$this->temp = [];
		parent::tearDown();
	}

	/**
	 * Write a file and register it for cleanup.
	 */
	private function write( $contents, $suffix = '.gz' ) {
		$path = tempnam( sys_get_temp_dir(), 'cpl' ) . $suffix;
		file_put_contents( $path, $contents );
		$this->temp[] = $path;

		return $path;
	}

	/**
	 * An NDJSON export body: header record plus $records item records.
	 */
	private function ndjson( $records = 200 ) {
		$out = wp_json_encode_stub( [ 'type' => 'header', 'format' => 'cp-library-export' ] ) . "\n";

		for ( $i = 0; $i < $records; $i++ ) {
			$out .= wp_json_encode_stub(
				[
					'type'        => 'item',
					'original_id' => $i,
					'post'        => [ 'title' => "Sermon {$i}", 'content' => str_repeat( 'x', 200 ) ],
				]
			) . "\n";
		}

		return $out;
	}

	public function test_detects_gzip_by_magic_bytes_not_extension() {
		$gz    = $this->write( gzencode( "a\nb\n" ), '.ndjson' ); // Deliberately mislabelled.
		$plain = $this->write( "a\nb\n", '.gz' );                  // Deliberately mislabelled.

		$this->assertTrue( Util::is_gzip( $gz ), 'content decides, not the extension' );
		$this->assertFalse( Util::is_gzip( $plain ), 'content decides, not the extension' );
	}

	public function test_intact_archive_is_complete() {
		$body = $this->ndjson();
		$file = $this->write( gzencode( $body, 6 ) );

		$this->assertSame( strlen( $body ), Util::gzip_uncompressed_size( $file ) );
		$this->assertTrue( Util::gzip_is_complete( $file, strlen( $body ) ) );
	}

	public function test_truncated_archive_is_rejected() {
		$body = $this->ndjson();
		$raw  = gzencode( $body, 6 );
		$file = $this->write( substr( $raw, 0, (int) ( strlen( $raw ) * 0.6 ) ) );

		// Read it the way the importer does, to get the byte count it would see.
		$handle   = gzopen( $file, 'rb' );
		$inflated = 0;
		$lines    = 0;

		while ( false !== ( $line = gzgets( $handle, 16777216 ) ) ) {
			$inflated += strlen( $line );
			$lines++;
		}

		gzclose( $handle );

		// This is the trap: reading a truncated archive raises nothing at all.
		$this->assertGreaterThan( 0, $lines, 'a truncated archive still yields records' );
		$this->assertLessThan( strlen( $body ), $inflated, 'but not all of them' );

		$this->assertFalse(
			Util::gzip_is_complete( $file, $inflated ),
			'a short read against a full-length trailer must be caught'
		);
	}

	public function test_plain_ndjson_is_always_considered_complete() {
		$body = $this->ndjson( 5 );
		$file = $this->write( $body, '.ndjson' );

		$this->assertTrue(
			Util::gzip_is_complete( $file, strlen( $body ) ),
			'uncompressed uploads have no trailer to check and must not be rejected'
		);
	}

	public function test_unreadable_trailer_does_not_reject() {
		// Too short to hold a trailer: the check must abstain rather than fail
		// the import on a file it cannot judge.
		$file = $this->write( "\x1f\x8b" );

		$this->assertNull( Util::gzip_uncompressed_size( $file ) );
		$this->assertTrue( Util::gzip_is_complete( $file, 0 ) );
	}
}

/**
 * json_encode without WordPress, kept local so the test file has no WP deps.
 */
function wp_json_encode_stub( $data ) {
	return json_encode( $data );
}
