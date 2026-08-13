<?php
/**
 * The analytics snapshot on the CP Sermons dashboard.
 *
 * @package CP_Library
 * @since 1.7.0
 */

namespace CP_Library\Admin\Dashboard;

use CP_Library\Admin\Analytics\Init as Analytics;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Last-30-day listening figures, computed on a schedule rather than on view.
 *
 * The log table is append-only and unbounded — it grows with every play, for
 * the life of the site — so these totals are rolled up by a daily cron into a
 * single option and the card only ever reads that. Rendering them live would
 * put three aggregate scans on the critical path of a page people open all day.
 *
 * @since 1.7.0
 */
class AnalyticsSnapshot {

	/**
	 * Where the rolled-up figures live.
	 *
	 * @var string
	 */
	const OPTION = 'cpl_analytics_snapshot';

	/**
	 * The rollup cron hook.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'cpl_analytics_rollup';

	/**
	 * The window the snapshot covers, in days.
	 *
	 * @var int
	 */
	const WINDOW = 30;

	/**
	 * Below this many plays the card stays hidden.
	 *
	 * A site that has just switched the plugin on would otherwise be shown
	 * "0 plays · 0 engaged · 0:00", which reads as broken software rather than
	 * as a library nobody has listened to yet.
	 *
	 * @var int
	 */
	const MIN_PLAYS = 10;

	/**
	 * The single instance of the class.
	 *
	 * @var AnalyticsSnapshot
	 */
	protected static $_instance; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Only make one instance of AnalyticsSnapshot.
	 *
	 * @return AnalyticsSnapshot
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof AnalyticsSnapshot ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor.
	 */
	protected function __construct() {
		add_filter( 'cpl_dashboard_cards', array( $this, 'register_card' ) );

		// Kept so an existing schedule, or anything hooking it, still works —
		// but Rollup owns the scheduling, so this no longer registers a second
		// daily event recomputing the same three figures.
		add_action( self::CRON_HOOK, array( $this, 'rollup' ) );
	}

	/**
	 * The window this covers, in days.
	 *
	 * @return int
	 * @since 1.7.0
	 */
	public static function get_window() {
		/**
		 * Filters the number of days the dashboard snapshot covers.
		 *
		 * @param int $days Default 30.
		 * @since 1.7.0
		 */
		return absint( apply_filters( 'cpl_analytics_snapshot_window', self::WINDOW ) );
	}

	/**
	 * The log actions that count as a play.
	 *
	 * The player logs `play`, while the wrapper logs `audio_view`/`video_view`.
	 * They are near-disjoint in practice — one library recorded 4,510 of the
	 * first against 48 of the other two combined — so the union is a better
	 * answer than either alone. Note that the Analytics screen counts only the
	 * latter pair, and therefore reports a small fraction of real plays.
	 *
	 * @return array
	 * @since 1.7.0
	 */
	public static function get_play_actions() {
		/**
		 * Filters the log actions counted as a play.
		 *
		 * @param array $actions The action names.
		 * @since 1.7.0
		 */
		return (array) apply_filters(
			'cpl_analytics_play_actions',
			array( 'play', 'audio_view', 'video_view' )
		);
	}

	/**
	 * Register the card.
	 *
	 * @param array $cards The registered cards.
	 * @return array
	 * @since 1.7.0
	 */
	public function register_card( $cards ) {
		$cards['analytics'] = array(
			'title'     => __( 'Last 30 days', 'cp-library' ),
			'column'    => 'main',
			// Below the discovery cards: useful, but not why someone opens this.
			'priority'  => 50,
			'condition' => array( $this, 'has_data' ),
			'render'    => array( $this, 'render' ),
		);

		return $cards;
	}

	/**
	 * Whether there is enough activity to be worth showing.
	 *
	 * A single option read.
	 *
	 * @return bool
	 * @since 1.7.0
	 */
	public function has_data() {
		$snapshot = get_option( self::OPTION );

		return is_array( $snapshot ) && ! empty( $snapshot['plays'] ) && $snapshot['plays'] >= self::MIN_PLAYS;
	}

	/**
	 * Recompute the snapshot.
	 *
	 * Every query here filters `action` and `created`, which is what the
	 * composite index on the log table exists to serve.
	 *
	 * @return array The stored snapshot.
	 * @since 1.7.0
	 */
	public function rollup() {
		global $wpdb;

		$table   = $wpdb->prefix . 'cp_log';
		$since   = Analytics::get_time( self::get_window() . ' days ago' );
		$actions = self::get_play_actions();

		$plays = 0;

		// A filter that returns nothing would otherwise build `action IN ( )`,
		// which is a syntax error rather than an empty result.
		if ( $actions ) {
			$placeholders = implode( ', ', array_fill( 0, count( $actions ), '%s' ) );

			$plays = (int) $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are generated above.
					"SELECT COUNT(*) FROM {$table} WHERE action IN ( {$placeholders} ) AND created > %s",
					array_merge( $actions, array( $since ) )
				)
			);
		}

		$engaged = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE action IN ( 'engaged_audio_view', 'engaged_video_view' ) AND created > %s",
				$since
			)
		);

		// JSON_EXTRACT needs MySQL 5.7 / MariaDB 10.2. Init::maybe_setup() still
		// accommodates 5.5, and this now runs daily on every site rather than
		// only when someone opens the Analytics screen — so those installs would
		// otherwise log a SQL error every day for a stat they can never show.
		$duration = null;

		if ( version_compare( $wpdb->db_version(), '5.7', '>=' ) ) {
			$duration = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT AVG( JSON_EXTRACT( data, '$.watch_duration' ) ) FROM {$table} WHERE action = 'view_duration' AND created > %s",
					$since
				)
			);
		}

		$snapshot = array(
			'plays'        => $plays,
			'engaged'      => $engaged,
			'avg_duration' => (int) round( (float) $duration ),
			'computed_at'  => time(),
		);

		update_option( self::OPTION, $snapshot, false );

		return $snapshot;
	}

	/**
	 * Format a duration in seconds as m:ss or h:mm:ss.
	 *
	 * @param int $seconds The duration.
	 * @return string
	 * @since 1.7.0
	 */
	protected function format_duration( $seconds ) {
		$seconds = max( 0, (int) $seconds );

		if ( $seconds >= HOUR_IN_SECONDS ) {
			return sprintf( '%d:%02d:%02d', intdiv( $seconds, 3600 ), intdiv( $seconds % 3600, 60 ), $seconds % 60 );
		}

		return sprintf( '%d:%02d', intdiv( $seconds, 60 ), $seconds % 60 );
	}

	/**
	 * Render the card body.
	 *
	 * @return void
	 * @since 1.7.0
	 */
	public function render() {
		$snapshot = get_option( self::OPTION );

		if ( ! is_array( $snapshot ) ) {
			return;
		}

		$snapshot = wp_parse_args(
			$snapshot,
			array(
				'plays'        => 0,
				'engaged'      => 0,
				'avg_duration' => 0,
				'computed_at'  => 0,
			)
		);

		// Zero stats are omitted rather than printed. `engaged` and
		// `avg_duration` come from different logging paths than `plays`, so a
		// site can genuinely have thousands of plays and no engagement rows —
		// and "1,284 plays / 0 engaged / 0:00" reads as broken software, which
		// is the whole reason this card hides itself on a quiet install.
		$stats = array();

		$stats[] = array(
			'value' => number_format_i18n( $snapshot['plays'] ),
			'label' => __( 'times played', 'cp-library' ),
		);

		if ( $snapshot['engaged'] ) {
			$stats[] = array(
				'value' => number_format_i18n( $snapshot['engaged'] ),
				'label' => __( 'listened past the start', 'cp-library' ),
			);
		}

		if ( $snapshot['avg_duration'] ) {
			$stats[] = array(
				'value' => $this->format_duration( $snapshot['avg_duration'] ),
				'label' => __( 'average time watched', 'cp-library' ),
			);
		}
		?>
		<div class="cpl-dashboard__stats">
			<?php foreach ( $stats as $stat ) : ?>
				<div class="cpl-dashboard__stat">
					<span class="cpl-dashboard__stat-value"><?php echo esc_html( $stat['value'] ); ?></span>
					<span class="cpl-dashboard__stat-label"><?php echo esc_html( $stat['label'] ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>

		<p class="cpl-dashboard__stat-footer">
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . cp_library()->setup->post_types->item->post_type . '&page=' . Analytics::$page_name ) ); ?>">
				<?php esc_html_e( 'Full analytics', 'cp-library' ); ?>
				<span aria-hidden="true">&rarr;</span>
			</a>
			<?php if ( ! empty( $snapshot['computed_at'] ) ) : ?>
				<span class="cpl-dashboard__stat-stamp">
					<?php
					printf(
						/* translators: %s: human readable time difference, e.g. "4 hours". */
						esc_html__( 'counted %s ago, refreshes daily', 'cp-library' ),
						esc_html( human_time_diff( $snapshot['computed_at'] ) )
					);
					?>
				</span>
			<?php endif; ?>
		</p>
		<?php
	}
}
