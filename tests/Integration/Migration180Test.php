<?php
/**
 * Tests the 1.8.0 upgrade migration that removes old sermon CSV exports.
 *
 * Before 1.8.0 each export was written to the uploads folder as
 * `{label}_{date}.csv` and never removed. 1.8.0 streams exports, and an export
 * only cleans up files named with the current sermon label, so files from
 * before a label rename, or on a site that never exports again, stayed
 * public. The migration removes them on upgrade. It runs unattended on every
 * site and deletes files, so it must only touch sermon exports.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Integration;

/**
 * @covers ::cp_library_migrate_1_8_0
 * @covers ::cp_library_remove_legacy_export_files
 * @covers ::cp_library_is_sermon_export
 */
class Migration180Test extends TestCase {

	/** @var string */
	private $month_dir;

	/** @var string[] */
	private $created = array();

	const EXPORT_HEADER = "id,originID,permalink,status,slug,thumb,title\n1,10,https://example.test/sermon,draft,sermon,,Draft sermon\n";

	public function set_up() {
		parent::set_up();

		$uploads         = wp_upload_dir( null, false );
		$this->month_dir = trailingslashit( $uploads['basedir'] ) . '2019/03';
		wp_mkdir_p( $this->month_dir );
	}

	public function tear_down() {
		foreach ( $this->created as $path ) {
			if ( file_exists( $path ) ) {
				unlink( $path );
			}
		}

		parent::tear_down();
	}

	private function write( $name, $contents ) {
		$path = trailingslashit( $this->month_dir ) . $name;
		file_put_contents( $path, $contents );
		$this->created[] = $path;

		return $path;
	}

	private function migrate( $from ) {
		$migrations = get_option( 'cp_library_migrations', array() );
		unset( $migrations['1.8.0'] );
		update_option( 'cp_library_migrations', $migrations );

		cp_library_migrate_1_8_0( $from, '1.8.0' );
	}

	public function test_exports_under_any_label_are_removed() {
		$current = $this->write( 'Sermons_2019-03-10.csv', self::EXPORT_HEADER );
		$renamed = $this->write( 'Messages_2019-03-17.csv', self::EXPORT_HEADER );

		$this->migrate( '1.7.0' );

		$this->assertFileDoesNotExist( $current );
		$this->assertFileDoesNotExist( $renamed );
	}

	public function test_other_csv_files_are_kept() {
		$dated_other  = $this->write( 'Attendance_2019-03-10.csv', "date,count\n2019-03-10,120\n" );
		$undated      = $this->write( 'sermons-backup.csv', self::EXPORT_HEADER );
		$empty        = $this->write( 'Empty_2019-03-10.csv', '' );

		$this->migrate( '1.7.0' );

		$this->assertFileExists( $dated_other );
		$this->assertFileExists( $undated, 'Only files named like an export are removed.' );
		$this->assertFileExists( $empty );
	}

	public function test_does_not_run_when_already_on_1_8_0() {
		$export = $this->write( 'Sermons_2019-03-10.csv', self::EXPORT_HEADER );

		$this->migrate( '1.8.0' );

		$this->assertFileExists( $export );
	}

	public function test_runs_once() {
		$this->migrate( '1.7.0' );
		$export = $this->write( 'Sermons_2019-03-24.csv', self::EXPORT_HEADER );

		cp_library_migrate_1_8_0( '1.7.0', '1.8.0' );

		$this->assertFileExists( $export );
		$this->assertTrue( cp_library_did_migration( '1.8.0' ) );
	}
}
