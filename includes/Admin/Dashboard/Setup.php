<?php
/**
 * The setup checklist on the CP Sermons dashboard.
 *
 * @package CP_Library
 * @since 1.7.0
 */

namespace CP_Library\Admin\Dashboard;

use CP_Library\Admin\Settings;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * The one-time decisions worth making early, as a checklist.
 *
 * Every step has an unambiguous done-state — a post exists, an option is set —
 * so the card can never claim work that was already finished. Steps for
 * features the site has switched off are not offered at all.
 *
 * The card removes itself once every step passes, or when dismissed. Dismissal
 * is stored site-wide rather than per user: whether a library has been set up
 * is a fact about the site, not about who is looking at it.
 *
 * @since 1.7.0
 */
class Setup {

	/**
	 * Option recording that the checklist was dismissed.
	 *
	 * @var string
	 */
	const DISMISSED_OPTION = 'cpl_dashboard_setup_dismissed';

	/**
	 * The dismiss action, used for the nonce and the request check.
	 *
	 * @var string
	 */
	const DISMISS_ACTION = 'cpl_dismiss_setup';

	/**
	 * The single instance of the class.
	 *
	 * @var Setup
	 */
	protected static $_instance; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Only make one instance of Setup.
	 *
	 * @return Setup
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof Setup ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor.
	 */
	protected function __construct() {
		add_filter( 'cpl_dashboard_cards', array( $this, 'register_card' ) );
		add_action( 'admin_init', array( $this, 'maybe_dismiss' ) );
	}

	/**
	 * Register the card.
	 *
	 * @param array $cards The registered cards.
	 * @return array
	 * @since 1.7.0
	 */
	public function register_card( $cards ) {
		$cards['setup'] = array(
			'title'     => __( 'Finish setting up', 'cp-library' ),
			'column'    => 'main',
			'priority'  => 10,
			'condition' => array( $this, 'should_show' ),
			'render'    => array( $this, 'render' ),
		);

		return $cards;
	}

	/**
	 * Whether the checklist still has anything to say.
	 *
	 * Cheap by construction: post counts come from wp_count_posts(), which core
	 * caches in the `counts` object-cache group, and the rest are option reads.
	 *
	 * @return bool
	 * @since 1.7.0
	 */
	public function should_show() {
		if ( get_option( self::DISMISSED_OPTION ) ) {
			return false;
		}

		foreach ( $this->get_steps() as $step ) {
			if ( ! $step['done'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The checklist steps.
	 *
	 * @return array Each step has a label, a done bool, and a url to act on it.
	 * @since 1.7.0
	 */
	protected function get_steps() {
		$post_types = cp_library()->setup->post_types;
		$item       = $post_types->item;

		$steps = array(
			'sermon' => array(
				/* translators: %s: the singular sermon label. */
				'label' => sprintf( __( 'Add your first %s', 'cp-library' ), strtolower( $item->single_label ) ),
				'done'  => $this->has_posts( $item->post_type ),
				'url'   => admin_url( 'post-new.php?post_type=' . $item->post_type ),
			),
		);

		if ( $post_types->item_type_enabled() ) {
			$steps['series'] = array(
				/* translators: %s: the singular series label. */
				'label' => sprintf( __( 'Create a %s', 'cp-library' ), strtolower( $post_types->item_type->single_label ) ),
				'done'  => $this->has_posts( $post_types->item_type->post_type ),
				'url'   => admin_url( 'post-new.php?post_type=' . $post_types->item_type->post_type ),
			);
		}

		if ( $post_types->speaker_enabled() ) {
			$steps['speaker'] = array(
				/* translators: %s: the singular speaker label. */
				'label' => sprintf( __( 'Add a %s', 'cp-library' ), strtolower( $post_types->speaker->single_label ) ),
				'done'  => $this->has_posts( $post_types->speaker->post_type ),
				'url'   => admin_url( 'post-new.php?post_type=' . $post_types->speaker->post_type ),
			);
		}

		$steps['thumbnail'] = array(
			'label' => __( 'Set a default image', 'cp-library' ),
			'done'  => (bool) Settings::get( 'default_thumbnail', '', 'cpl_main_options' ),
			'url'   => admin_url( 'admin.php?page=cpl_main_options' ),
		);

		$steps['podcast'] = array(
			'label' => __( 'Turn on your podcast feed', 'cp-library' ),
			'done'  => cp_library()->setup->podcast->is_enabled(),
			'url'   => admin_url( 'admin.php?page=cpl_advanced_options' ),
		);

		/**
		 * Filters the CP Sermons setup checklist.
		 *
		 * Each step needs a `label`, a `done` bool, and a `url` to act on it.
		 * Keep `done` to option reads or cached counts — the checklist is
		 * evaluated on every view of the dashboard.
		 *
		 * @param array $steps The checklist steps.
		 * @since 1.7.0
		 */
		return apply_filters( 'cpl_dashboard_setup_steps', $steps );
	}

	/**
	 * Whether a post type has anything in it yet.
	 *
	 * Counts drafts as well as published: a sermon saved but not yet public
	 * still means the step was done.
	 *
	 * @param string $post_type The post type.
	 * @return bool
	 * @since 1.7.0
	 */
	protected function has_posts( $post_type ) {
		$counts = (array) wp_count_posts( $post_type );

		foreach ( array( 'publish', 'future', 'draft', 'pending', 'private' ) as $status ) {
			if ( ! empty( $counts[ $status ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The URL that dismisses the checklist.
	 *
	 * @return string
	 * @since 1.7.0
	 */
	protected function get_dismiss_url() {
		return wp_nonce_url(
			add_query_arg( self::DISMISS_ACTION, 1, \CP_Library\Admin\Dashboard::get_url() ),
			self::DISMISS_ACTION
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

		if ( ! current_user_can( \CP_Library\Admin\Dashboard::get_capability() ) ) {
			return;
		}

		check_admin_referer( self::DISMISS_ACTION );

		update_option( self::DISMISSED_OPTION, time() );

		wp_safe_redirect( \CP_Library\Admin\Dashboard::get_url() );
		exit;
	}

	/**
	 * Render the card body.
	 *
	 * @return void
	 * @since 1.7.0
	 */
	public function render() {
		$steps = $this->get_steps();
		$total = count( $steps );
		$done  = count( array_filter( wp_list_pluck( $steps, 'done' ) ) );
		?>
		<p class="cpl-dashboard__progress-label">
			<?php
			printf(
				/* translators: 1: steps completed, 2: total steps. */
				esc_html__( '%1$s of %2$s complete', 'cp-library' ),
				esc_html( number_format_i18n( $done ) ),
				esc_html( number_format_i18n( $total ) )
			);
			?>
		</p>

		<div class="cpl-progressbar-wrapper">
			<div class="cpl-progressbar" style="width: <?php echo esc_attr( $total ? round( $done / $total * 100 ) : 0 ); ?>%"></div>
		</div>

		<ul class="cpl-dashboard__steps">
			<?php foreach ( $steps as $key => $step ) : ?>
				<li class="<?php echo $step['done'] ? 'is-done' : ''; ?>">
					<span class="cpl-dashboard__step-mark dashicons <?php echo $step['done'] ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>" aria-hidden="true"></span>
					<span class="cpl-dashboard__step-label"><?php echo esc_html( $step['label'] ); ?></span>
					<?php if ( ! $step['done'] ) : ?>
						<a class="button button-small" href="<?php echo esc_url( $step['url'] ); ?>">
							<?php esc_html_e( 'Set up', 'cp-library' ); ?>
						</a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>

		<p class="cpl-dashboard__dismiss">
			<a href="<?php echo esc_url( $this->get_dismiss_url() ); ?>">
				<?php esc_html_e( 'Dismiss this checklist', 'cp-library' ); ?>
			</a>
		</p>
		<?php
	}
}
