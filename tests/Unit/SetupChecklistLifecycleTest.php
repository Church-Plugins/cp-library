<?php
/**
 * Tests for when the setup checklist shows itself and when it goes away.
 *
 * A setup checklist is only useful while a site is being set up, and the way
 * these cards rot is by outliving that. This one owns its own lifecycle rather
 * than relying on a "new site" mode someone has to remember to switch off: it
 * appears while any step is outstanding, and removes itself the moment the last
 * one passes or the site dismisses it.
 *
 * The dismissal is deliberately a site option rather than user meta. Whether a
 * sermon library has been set up is a fact about the site — a second admin
 * arriving later should not be told to add their first sermon to a library of
 * eight thousand.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Library\Admin\Dashboard\Setup;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Supplies fixed steps so the lifecycle can be exercised without WordPress.
 */
class StubbedSetup extends Setup {

	/** @var array */
	public $steps = array();

	public function __construct() {
		// Deliberately does not call parent::__construct() — that registers hooks.
	}

	protected function get_steps() {
		return $this->steps;
	}
}

/**
 * @covers \CP_Library\Admin\Dashboard\Setup::should_show
 */
class SetupChecklistLifecycleTest extends TestCase {

	/** @var StubbedSetup */
	private $setup;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->setup = ( new ReflectionClass( StubbedSetup::class ) )->newInstanceWithoutConstructor();

		// Not dismissed unless a test says so.
		Functions\when( 'get_option' )->justReturn( false );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @param array $done Step key => done bool.
	 * @return array
	 */
	private function steps( array $done ) {
		$steps = array();

		foreach ( $done as $key => $is_done ) {
			$steps[ $key ] = array( 'label' => $key, 'done' => $is_done, 'url' => '' );
		}

		return $steps;
	}

	/**
	 * While anything is outstanding, the card has something to say.
	 */
	public function test_shows_while_a_step_is_outstanding() {
		$this->setup->steps = $this->steps( array( 'sermon' => true, 'podcast' => false ) );

		$this->assertTrue( $this->setup->should_show() );
	}

	/**
	 * The card removes itself on completion — no mode to switch, nobody to
	 * remember to switch it.
	 */
	public function test_hides_once_every_step_passes() {
		$this->setup->steps = $this->steps( array( 'sermon' => true, 'podcast' => true ) );

		$this->assertFalse( $this->setup->should_show() );
	}

	/**
	 * Dismissal wins over outstanding steps — a church that will never publish a
	 * podcast should not be nagged about one forever.
	 */
	public function test_dismissal_hides_an_incomplete_checklist() {
		Functions\when( 'get_option' )->justReturn( 1771027200 );

		$this->setup->steps = $this->steps( array( 'sermon' => true, 'podcast' => false ) );

		$this->assertFalse( $this->setup->should_show() );
	}

	/**
	 * Dismissal is checked before the steps are built, so a dismissed checklist
	 * costs nothing on every subsequent page view.
	 */
	public function test_dismissal_short_circuits_before_building_steps() {
		Functions\when( 'get_option' )->justReturn( 1771027200 );

		$setup = new class() extends Setup {
			public $built = false;

			public function __construct() {}

			protected function get_steps() {
				$this->built = true;

				return array();
			}
		};

		$setup->should_show();

		$this->assertFalse( $setup->built, 'A dismissed checklist must not evaluate its steps.' );
	}

	/**
	 * An empty step list is complete, not outstanding — a site with every
	 * optional feature switched off must not see an empty card.
	 */
	public function test_no_steps_means_nothing_to_show() {
		$this->setup->steps = array();

		$this->assertFalse( $this->setup->should_show() );
	}
}
