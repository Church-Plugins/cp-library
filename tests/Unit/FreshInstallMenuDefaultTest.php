<?php
/**
 * Tests for the fresh-install guard on the 1.5.0 default-menu-item migration.
 *
 * 1.5.0 changed the default admin menu item from Series to Sermons, and shipped
 * a migration that pinned `default_menu_item` to 'item_type' so sites already
 * running on Series did not have their menu move under them.
 *
 * The migration gates on `version_compare( $old_version, '1.5.0', '<' )`, and
 * $old_version is `get_option( 'cpl_version', false )` — which is `false` on a
 * brand new site. version_compare() casts that false to '' and reads it as
 * older than everything, so the migration fired on fresh installs too and
 * pinned them to Series. The effect was that the Sermons default 1.5.0
 * introduced never actually reached anyone: every new site was written to
 * Series before the default was ever consulted.
 *
 * The distinction the guard draws is between *upgrading from a version that
 * defaulted to Series* (pin it, the menu must not move) and *a site that has no
 * prior version at all* (leave it unset, so the current default applies).
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * @covers ::cp_library_migrate_1_5_0
 */
class FreshInstallMenuDefaultTest extends TestCase {

	/**
	 * Options written by the migration, keyed by option name.
	 *
	 * @var array
	 */
	private $written = array();

	/**
	 * Existing option values the stubs read from.
	 *
	 * @var array
	 */
	private $options = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		require_once dirname( __DIR__, 2 ) . '/includes/migrations.php';

		$this->written = array();
		$this->options = array( 'cp_library_migrations' => array() );

		Functions\when( 'get_option' )->alias(
			function ( $key, $default = false ) {
				return array_key_exists( $key, $this->options ) ? $this->options[ $key ] : $default;
			}
		);

		Functions\when( 'update_option' )->alias(
			function ( $key, $value ) {
				$this->options[ $key ] = $value;
				$this->written[ $key ] = $value;

				return true;
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * The bug: a fresh install has no prior version, so it must not be pinned to
	 * Series. Before the guard, version_compare( false, '1.5.0', '<' ) let this
	 * through and wrote 'item_type'.
	 */
	public function test_fresh_install_is_not_pinned_to_series() {
		cp_library_migrate_1_5_0( false, '1.7.0' );

		$this->assertArrayNotHasKey(
			'cpl_advanced_options',
			$this->written,
			'A fresh install must leave default_menu_item unset so the Sermons default applies.'
		);
	}

	/**
	 * A fresh install still records the migration, so it cannot fire later once
	 * cpl_version has been populated.
	 */
	public function test_fresh_install_still_records_the_migration() {
		cp_library_migrate_1_5_0( false, '1.7.0' );

		$this->assertArrayHasKey( '1.5.0', $this->options['cp_library_migrations'] );
	}

	/**
	 * The behavior the migration exists for: a site coming from before 1.5.0 was
	 * running on the Series default and must stay there.
	 */
	public function test_upgrade_from_before_1_5_0_is_pinned_to_series() {
		cp_library_migrate_1_5_0( '1.4.10', '1.7.0' );

		$this->assertSame(
			'item_type',
			$this->options['cpl_advanced_options']['default_menu_item'],
			'An upgrade from before 1.5.0 must keep the Series menu it was already using.'
		);
	}

	/**
	 * An explicit choice already on record is never overwritten.
	 */
	public function test_existing_choice_is_left_alone() {
		$this->options['cpl_advanced_options'] = array( 'default_menu_item' => 'item' );

		cp_library_migrate_1_5_0( '1.4.10', '1.7.0' );

		$this->assertSame( 'item', $this->options['cpl_advanced_options']['default_menu_item'] );
	}

	/**
	 * Sites at or past 1.5.0 are out of scope entirely.
	 */
	public function test_upgrade_from_1_5_0_or_later_is_skipped() {
		cp_library_migrate_1_5_0( '1.6.3', '1.7.0' );

		$this->assertSame( array(), $this->written );
	}
}
