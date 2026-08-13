<?php
/**
 * Announcements and feature spotlights on the CP Sermons dashboard.
 *
 * @package CP_Library
 * @since 1.7.0
 */

namespace CP_Library\Admin\Dashboard;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * The place to tell people about a feature.
 *
 * Covers two jobs with one mechanism. An *announcement* is authored against a
 * release — "new in 1.7". A *spotlight* is authored against the state of the
 * site — "you have 886 videos and 47 transcripts". The second is what surfaces
 * features that shipped years ago and nobody ever found, which is the harder
 * and more valuable half.
 *
 * Dismissal is per user, unlike the setup checklist's. Whether a library has
 * been set up is a fact about the site; whether someone has read an
 * announcement is a fact about that person, and a second admin should still
 * get to see it.
 *
 * Spotlights are capped so the panel stays an invitation rather than a list of
 * demands, and every one can be waved off permanently.
 *
 * @since 1.7.0
 */
class Notices {

	/**
	 * User meta key holding the ids this user has dismissed.
	 *
	 * @var string
	 */
	const DISMISSED_META = 'cpl_dismissed_notices';

	/**
	 * The dismiss action, used for the nonce and the request check.
	 *
	 * @var string
	 */
	const DISMISS_ACTION = 'cpl_dismiss_notice';

	/**
	 * How many spotlights to show at once.
	 *
	 * @var int
	 */
	const MAX_SPOTLIGHTS = 2;

	/**
	 * The single instance of the class.
	 *
	 * @var Notices
	 */
	protected static $_instance; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Only make one instance of Notices.
	 *
	 * @return Notices
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof Notices ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor.
	 */
	protected function __construct() {
		add_filter( 'cpl_dashboard_cards', array( $this, 'register_cards' ) );
		add_action( 'admin_init', array( $this, 'maybe_dismiss' ) );
	}

	/**
	 * Register the announcement and spotlight cards.
	 *
	 * Both are headless — an announcement wrapped in a postbox titled
	 * "Announcements" reads like chrome; the notice is the content.
	 *
	 * @param array $cards The registered cards.
	 * @return array
	 * @since 1.7.0
	 */
	public function register_cards( $cards ) {
		$cards['announcements'] = array(
			'column'    => 'main',
			'priority'  => 5,
			'condition' => function () {
				return (bool) $this->get_visible( 'announcement' );
			},
			'render'    => function () {
				$this->render_notices( $this->get_visible( 'announcement' ) );
			},
		);

		$cards['spotlight'] = array(
			'title'     => __( 'Make more of your library', 'cp-library' ),
			'column'    => 'main',
			'priority'  => 15,
			'condition' => function () {
				return (bool) $this->get_visible( 'spotlight' );
			},
			'render'    => function () {
				$this->render_notices( array_slice( $this->get_visible( 'spotlight' ), 0, self::MAX_SPOTLIGHTS ) );
			},
		);

		return $cards;
	}

	/**
	 * Every registered notice, before filtering for this user.
	 *
	 * @return array
	 * @since 1.7.0
	 */
	protected function get_notices() {
		/**
		 * Filters the notices offered on the CP Sermons dashboard.
		 *
		 * Each notice is an array of:
		 *  - id        string   Unique and stable. Dismissal is recorded against
		 *                       it, so changing it re-shows the notice.
		 *  - kind      string   'announcement' (tied to a release) or 'spotlight'
		 *                       (tied to the state of the site).
		 *  - title     string   One line.
		 *  - body      string   A sentence or two of plain text.
		 *  - actions   array    Zero or more [ label, url, primary ] links.
		 *  - condition callable Optional. Return false to withhold the notice.
		 *
		 * Conditions run on every view of the dashboard, so they must be option
		 * reads or cached — see Spotlight::get_usage() for the cached shape.
		 *
		 * @param array $notices The registered notices.
		 * @since 1.7.0
		 */
		return (array) apply_filters( 'cpl_dashboard_notices', array() );
	}

	/**
	 * The notices of a kind that this user has not dismissed.
	 *
	 * @param string $kind 'announcement' or 'spotlight'.
	 * @return array
	 * @since 1.7.0
	 */
	public function get_visible( $kind ) {
		static $cache = array();

		if ( isset( $cache[ $kind ] ) ) {
			return $cache[ $kind ];
		}

		$dismissed = $this->get_dismissed();
		$visible   = array();

		foreach ( $this->get_notices() as $key => $notice ) {
			$notice = wp_parse_args(
				$notice,
				array(
					'id'        => is_string( $key ) ? $key : '',
					'kind'      => 'announcement',
					'title'     => '',
					'body'      => '',
					'actions'   => array(),
					'condition' => null,
				)
			);

			if ( $kind !== $notice['kind'] || ! $notice['id'] || ! $notice['title'] ) {
				continue;
			}

			if ( in_array( $notice['id'], $dismissed, true ) ) {
				continue;
			}

			if ( is_callable( $notice['condition'] ) && ! call_user_func( $notice['condition'], $notice ) ) {
				continue;
			}

			$visible[] = $notice;
		}

		$cache[ $kind ] = $visible;

		return $visible;
	}

	/**
	 * The notice ids this user has dismissed.
	 *
	 * @return array
	 * @since 1.7.0
	 */
	protected function get_dismissed() {
		$dismissed = get_user_meta( get_current_user_id(), self::DISMISSED_META, true );

		return is_array( $dismissed ) ? $dismissed : array();
	}

	/**
	 * The URL that dismisses a notice.
	 *
	 * @param string $id The notice id.
	 * @return string
	 * @since 1.7.0
	 */
	protected function get_dismiss_url( $id ) {
		return wp_nonce_url(
			add_query_arg( self::DISMISS_ACTION, rawurlencode( $id ), \CP_Library\Admin\Dashboard::get_url() ),
			self::DISMISS_ACTION . $id
		);
	}

	/**
	 * Handle a dismissal.
	 *
	 * @return void
	 * @since 1.7.0
	 */
	public function maybe_dismiss() {
		if ( empty( $_GET[ self::DISMISS_ACTION ] ) ) {
			return;
		}

		$id = sanitize_text_field( wp_unslash( $_GET[ self::DISMISS_ACTION ] ) );

		check_admin_referer( self::DISMISS_ACTION . $id );

		$user_id = get_current_user_id();

		if ( $user_id ) {
			$dismissed = $this->get_dismissed();

			if ( ! in_array( $id, $dismissed, true ) ) {
				$dismissed[] = $id;
				update_user_meta( $user_id, self::DISMISSED_META, $dismissed );
			}
		}

		wp_safe_redirect( \CP_Library\Admin\Dashboard::get_url() );
		exit;
	}

	/**
	 * Render a list of notices.
	 *
	 * @param array $notices Normalized notices.
	 * @return void
	 * @since 1.7.0
	 */
	protected function render_notices( $notices ) {
		foreach ( $notices as $notice ) :
			?>
			<div class="cpl-notice cpl-notice--<?php echo esc_attr( $notice['kind'] ); ?>">
				<div class="cpl-notice__body">
					<h3 class="cpl-notice__title"><?php echo esc_html( $notice['title'] ); ?></h3>
					<?php if ( $notice['body'] ) : ?>
						<p><?php echo esc_html( $notice['body'] ); ?></p>
					<?php endif; ?>

					<?php if ( ! empty( $notice['actions'] ) ) : ?>
						<p class="cpl-notice__actions">
							<?php foreach ( $notice['actions'] as $action ) : ?>
								<a
									class="button <?php echo empty( $action['primary'] ) ? '' : 'button-primary'; ?>"
									href="<?php echo esc_url( $action['url'] ); ?>"
									<?php echo empty( $action['external'] ) ? '' : 'target="_blank" rel="noopener noreferrer"'; ?>
								>
									<?php echo esc_html( $action['label'] ); ?>
								</a>
							<?php endforeach; ?>

							<a class="cpl-notice__dismiss" href="<?php echo esc_url( $this->get_dismiss_url( $notice['id'] ) ); ?>">
								<?php
								echo 'spotlight' === $notice['kind']
									? esc_html__( 'Not now', 'cp-library' )
									: esc_html__( 'Dismiss', 'cp-library' );
								?>
							</a>
						</p>
					<?php endif; ?>
				</div>
			</div>
			<?php
		endforeach;
	}
}
