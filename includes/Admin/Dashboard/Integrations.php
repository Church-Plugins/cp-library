<?php
/**
 * The integrations card on the CP Sermons dashboard.
 *
 * @package CP_Library
 * @since 1.7.0
 */

namespace CP_Library\Admin\Dashboard;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Whether each connected sermon source is actually working.
 *
 * A sync that has been failing for a fortnight looks identical, from the admin,
 * to one that simply has nothing new to fetch — sermons just stop appearing.
 * This surfaces the difference. Option reads and a cron lookup only; no queries.
 *
 * @since 1.7.0
 */
class Integrations {

	/**
	 * How long a sync can go without running before it is worth mentioning.
	 *
	 * Comfortably longer than the slowest built-in schedule (daily) so a healthy
	 * feed is never flagged for being between runs.
	 *
	 * @var int
	 */
	const STALE_AFTER = 2 * DAY_IN_SECONDS;

	/**
	 * How long a source can go unchecked before it is worth mentioning.
	 *
	 * @return int
	 * @since 1.7.0
	 */
	public static function get_stale_after() {
		/**
		 * Filters how long a source may go unchecked before being called stale.
		 *
		 * @param int $seconds Default two days.
		 * @since 1.7.0
		 */
		return absint( apply_filters( 'cpl_integration_stale_after', self::STALE_AFTER ) );
	}

	/**
	 * The single instance of the class.
	 *
	 * @var Integrations
	 */
	protected static $_instance; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Only make one instance of Integrations.
	 *
	 * @return Integrations
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof Integrations ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor.
	 */
	protected function __construct() {
		add_filter( 'cpl_dashboard_cards', array( $this, 'register_card' ) );
	}

	/**
	 * Register the card.
	 *
	 * @param array $cards The registered cards.
	 * @return array
	 * @since 1.7.0
	 */
	public function register_card( $cards ) {
		$cards['integrations'] = array(
			'title'     => __( 'Automatic imports', 'cp-library' ),
			'column'    => 'side',
			'priority'  => 30,
			'condition' => array( $this, 'has_enabled_adapters' ),
			'render'    => array( $this, 'render' ),
		);

		return $cards;
	}

	/**
	 * The adapters that are switched on.
	 *
	 * @return array
	 * @since 1.7.0
	 */
	protected function get_enabled_adapters() {
		$enabled = array();

		foreach ( (array) cp_library()->adapters->get_adapters() as $adapter ) {
			if ( $adapter->is_enabled() ) {
				$enabled[] = $adapter;
			}
		}

		return $enabled;
	}

	/**
	 * Whether any adapter is switched on.
	 *
	 * @return bool
	 * @since 1.7.0
	 */
	public function has_enabled_adapters() {
		return (bool) $this->get_enabled_adapters();
	}

	/**
	 * The state of one adapter.
	 *
	 * @param \CP_Library\Adapters\Adapter $adapter The adapter.
	 * @return array
	 * @since 1.7.0
	 */
	protected function get_status( $adapter ) {
		$last_sync  = $adapter->get_last_sync();
		$last_error = $adapter->get_last_error();
		$next       = wp_next_scheduled( $adapter->_cron_hook );

		if ( $last_error ) {
			return array(
				'state'  => 'error',
				/* translators: %s: the error reported by the sermon source. */
				'detail' => sprintf( __( 'Something went wrong last time it checked: %s', 'cp-library' ), $last_error['message'] ),
				'next'   => $next,
			);
		}

		if ( ! $last_sync ) {
			// Never recorded a check. On a feed connected before 1.7.0 this only
			// means it has not run since upgrading, so it is reported as unknown
			// rather than broken.
			return array(
				'state'  => 'unknown',
				'detail' => __( 'Has not run yet — it will at the next scheduled time.', 'cp-library' ),
				'next'   => $next,
			);
		}

		if ( ( time() - $last_sync ) > self::get_stale_after() ) {
			return array(
				'state'  => 'stale',
				'detail' => sprintf(
					/* translators: %s: human readable time difference, e.g. "3 weeks". */
					__( 'Has not checked for %s — longer than expected.', 'cp-library' ),
					human_time_diff( $last_sync )
				),
				'next'   => $next,
			);
		}

		return array(
			'state'  => 'ok',
			'detail' => sprintf(
				/* translators: %s: human readable time difference, e.g. "20 minutes". */
				__( 'Working — last checked %s ago.', 'cp-library' ),
				human_time_diff( $last_sync )
			),
			'next'   => $next,
		);
	}

	/**
	 * Render the card body.
	 *
	 * @return void
	 * @since 1.7.0
	 */
	public function render() {
		?>
		<ul class="cpl-dashboard__integrations">
			<?php foreach ( $this->get_enabled_adapters() as $adapter ) : ?>
				<?php $status = $this->get_status( $adapter ); ?>
				<li class="is-<?php echo esc_attr( $status['state'] ); ?>">
					<span class="cpl-dashboard__integration-name">
						<?php /* The state is otherwise only a text colour. */ ?>
						<span class="screen-reader-text">
							<?php
							$states = array(
								'ok'      => __( 'Working:', 'cp-library' ),
								'stale'   => __( 'Needs attention:', 'cp-library' ),
								'error'   => __( 'Problem:', 'cp-library' ),
								'unknown' => __( 'Not yet run:', 'cp-library' ),
							);
							echo esc_html( isset( $states[ $status['state'] ] ) ? $states[ $status['state'] ] : '' );
							?>
						</span>
						<?php echo esc_html( $adapter->display_name ); ?>
					</span>
					<span class="cpl-dashboard__integration-detail"><?php echo esc_html( $status['detail'] ); ?></span>
					<?php if ( $status['next'] ) : ?>
						<span class="cpl-dashboard__integration-next">
							<?php
							printf(
								/* translators: %s: human readable time difference, e.g. "40 minutes". */
								esc_html__( 'Checks again in about %s', 'cp-library' ),
								esc_html( human_time_diff( time(), $status['next'] ) )
							);
							?>
						</span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}
}
