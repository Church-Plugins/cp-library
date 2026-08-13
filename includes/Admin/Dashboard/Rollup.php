<?php
/**
 * The scheduled refresh behind the dashboard's cached figures.
 *
 * @package CP_Library
 * @since 1.7.0
 */

namespace CP_Library\Admin\Dashboard;

use CP_Library\Admin\MissingContent;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Keeps the dashboard's counts warm on a schedule instead of on a page view.
 *
 * Three cards need figures that cost real query time — the housekeeping counts,
 * the feature-usage numbers behind the spotlights, and the listening totals.
 * Every card's `condition` runs before anything paints, so a cold cache made a
 * single page view execute the lot synchronously: four correlated NOT EXISTS
 * scans of wp_posts, three unindexed scans including a leading-wildcard LIKE,
 * and three aggregates over the log table.
 *
 * So they are refreshed here, daily, and the cards render whatever is stored.
 * A card with nothing stored yet shows nothing rather than blocking the page to
 * compute it.
 *
 * @since 1.7.0
 */
class Rollup {

	/**
	 * The daily refresh hook.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'cpl_dashboard_rollup';

	/**
	 * The hook that refreshes counts shortly after content changes.
	 *
	 * @var string
	 */
	const DEBOUNCE_HOOK = 'cpl_dashboard_rollup_debounced';

	/**
	 * How long to wait after a sermon changes before recounting.
	 *
	 * Long enough that a bulk import or a batch of edits schedules one refresh
	 * rather than one per post.
	 *
	 * @var int
	 */
	const DEBOUNCE = 5 * MINUTE_IN_SECONDS;

	/**
	 * The single instance of the class.
	 *
	 * @var Rollup
	 */
	protected static $_instance; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Only make one instance of Rollup.
	 *
	 * @return Rollup
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof Rollup ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor.
	 */
	protected function __construct() {
		add_action( self::CRON_HOOK, array( $this, 'run' ) );
		add_action( self::DEBOUNCE_HOOK, array( $this, 'run' ) );
		add_action( 'admin_init', array( $this, 'schedule' ) );

		// Leaving cron events behind on deactivation is both a directory-review
		// failure and a real cost — on a 500-site network that is 500 orphaned
		// events still querying the log table daily.
		add_action( 'cpl_deactivation', array( $this, 'unschedule' ) );
	}

	/**
	 * Make sure the daily refresh is scheduled.
	 *
	 * @return void
	 * @since 1.7.0
	 */
	public function schedule() {
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}

		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
	}

	/**
	 * Clear the scheduled refreshes.
	 *
	 * @return void
	 * @since 1.7.0
	 */
	public function unschedule() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		wp_clear_scheduled_hook( self::DEBOUNCE_HOOK );
		wp_clear_scheduled_hook( AnalyticsSnapshot::CRON_HOOK );
	}

	/**
	 * Queue a refresh a few minutes out, if one is not already queued.
	 *
	 * @return void
	 * @since 1.7.0
	 */
	public function schedule_debounced() {
		if ( wp_next_scheduled( self::DEBOUNCE_HOOK ) ) {
			return;
		}

		wp_schedule_single_event( time() + self::DEBOUNCE, self::DEBOUNCE_HOOK );
	}

	/**
	 * Recompute everything the dashboard caches.
	 *
	 * @return void
	 * @since 1.7.0
	 */
	public function run() {
		MissingContent::get_instance()->get_counts( true );
		Spotlight::get_instance()->get_usage( true );
		AnalyticsSnapshot::get_instance()->rollup();
	}
}
