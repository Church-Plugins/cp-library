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
 * @covers ::cp_library_legacy_export_headers
 */
class Migration180Test extends TestCase {

	/** @var string */
	private $month_dir;

	/** @var string[] */
	private $created = array();

	/**
	 * Full header row written by the export in 1.5.0–1.7.0, including 1.7.0.
	 */
	const HEADER_170 = "Title,Description,Transcript,Series,Date,Passage,Location,Service Type,Speaker,Topics,Season,Scripture,Thumbnail,Video,Audio,Downloads\n";

	/**
	 * Full header row written by the export in 1.4.0–1.4.10.
	 */
	const HEADER_140 = "Title,Description,Series,Date,Passage,Location,Service Type,Speaker,Topics,Season,Scripture,Thumbnail,Video,Audio,Downloads\n";

	/**
	 * Full header row written by the export in 1.2.1–1.3.2.
	 */
	const HEADER_121 = "Title,Description,Series,Date,Passage,Location,Service Type,Speaker,Topics,Season,Scripture,Thumbnail,Video,Audio\n";

	const EXPORT_HEADER = self::HEADER_170 . "row\n";

	/**
	 * 1.7.0 header plus one more column. A prefix match would treat this as an export.
	 */
	const HEADER_170_WITH_EXTRA = "Title,Description,Transcript,Series,Date,Passage,Location,Service Type,Speaker,Topics,Season,Scripture,Thumbnail,Video,Audio,Downloads,Extra\n";

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
		return $this->write_at( $this->month_dir, $name, $contents );
	}

	private function write_at( $directory, $name, $contents ) {
		wp_mkdir_p( $directory );
		$path = trailingslashit( $directory ) . $name;
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

	/**
	 * Files named `{label}_YYYY-MM-DD.csv` with the 1.7.0 header are removed
	 * from each folder the cleanup scans. Other files in those folders stay.
	 */
	public function test_real_170_header_is_removed_from_each_scanned_folder() {
		$uploads = wp_upload_dir( null, false );
		$admin   = CP_LIBRARY_PLUGIN_DIR . 'includes/Admin';

		$root  = $this->write_at( $uploads['basedir'], 'Messages_2019-04-01.csv', self::HEADER_170 );
		$month = $this->write_at( $this->month_dir, 'Messages_2019-03-17.csv', self::HEADER_170 );
		$tools = $this->write_at( $admin, 'Messages_2019-05-09.csv', self::HEADER_170 );

		$kept_root  = $this->write_at( $uploads['basedir'], 'sermons-backup.csv', self::HEADER_170 );
		$kept_month = $this->write_at( $this->month_dir, 'Attendance_2019-03-10.csv', "date,count\n2019-03-10,12\n" );
		$kept_admin = $this->write_at( $admin, 'Messages_2019-05-09.txt', self::HEADER_170 );

		$deleted = cp_library_remove_legacy_export_files();

		$this->assertContains( $root, $deleted );
		$this->assertContains( $month, $deleted );
		$this->assertContains( $tools, $deleted );
		$this->assertFileDoesNotExist( $root );
		$this->assertFileDoesNotExist( $month );
		$this->assertFileDoesNotExist( $tools );
		$this->assertFileExists( $kept_root );
		$this->assertFileExists( $kept_month );
		$this->assertFileExists( $kept_admin );
	}

	/**
	 * A dated file whose header only starts like an export header is kept.
	 */
	public function test_header_that_only_starts_like_an_export_is_kept() {
		$shorter = $this->write( 'Messages_2019-06-01.csv', "Title,Description,Notes\nSunday,Welcome,1\n" );
		$longer  = $this->write( 'Messages_2019-06-02.csv', self::HEADER_170_WITH_EXTRA );

		cp_library_remove_legacy_export_files();

		$this->assertFileExists( $shorter );
		$this->assertFileExists( $longer );
	}

	/**
	 * Exact header rows from earlier released exports are removed too.
	 */
	public function test_earlier_released_export_headers_are_removed() {
		$v121 = $this->write( 'Messages_2018-01-02.csv', self::HEADER_121 );
		$v140 = $this->write( 'Sermons_2018-06-07.csv', self::HEADER_140 );

		cp_library_remove_legacy_export_files();

		$this->assertFileDoesNotExist( $v121 );
		$this->assertFileDoesNotExist( $v140 );
	}
}
