<?php
/**
 * Feature spotlights, chosen from how the library is actually used.
 *
 * @package CP_Library
 * @since 1.7.0
 */

namespace CP_Library\Admin\Dashboard;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Surfaces capabilities this library is not using yet.
 *
 * The plugin has a lot of features that are only discoverable if you already
 * know they exist. A generic tip list ages into wallpaper; a prompt built from
 * the site's own numbers does not. "You have 886 sermons on YouTube and 47
 * transcripts" is a reason to click. "CP Sermons supports transcripts" is not.
 *
 * Deliberately framed as opportunity, never as fault. These are things worth
 * doing, not things left undone — a missing transcript is not a defect, and the
 * moment this reads like a chore list it becomes the diagnostic panel it is
 * meant to replace. Nothing here is ever a count of what is "wrong".
 *
 * @since 1.7.0
 */
class Spotlight {

	/**
	 * Where the usage figures are cached.
	 *
	 * @var string
	 */
	const TRANSIENT = 'cpl_feature_usage';

	/**
	 * The single instance of the class.
	 *
	 * @var Spotlight
	 */
	protected static $_instance; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Only make one instance of Spotlight.
	 *
	 * @return Spotlight
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof Spotlight ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor.
	 */
	protected function __construct() {
		add_filter( 'cpl_dashboard_notices', array( $this, 'register_notices' ) );
	}

	/**
	 * How much each feature is being used.
	 *
	 * Cached for a day. These figures decide whether a suggestion is worth
	 * making, and the answer does not change hour to hour — a site that has
	 * never used timestamps will still not have used them this afternoon.
	 *
	 * @param bool $force Recompute rather than reading the cache.
	 * @return array
	 * @since 1.7.0
	 */
	public function get_usage( $force = false ) {
		$cached = $force ? false : get_transient( self::TRANSIENT );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		// Refreshed by Rollup on a schedule. Until it has run there are no
		// figures, and a suggestion is not worth three unindexed scans — one of
		// them a leading-wildcard LIKE — on the render path of a page view.
		if ( ! $force ) {
			return array(
				'hosted_videos' => 0,
				'transcripts'   => 0,
				'timestamps'    => 0,
			);
		}

		global $wpdb;

		$item_meta = $wpdb->prefix . 'cpl_item_meta';

		$usage = array(
			// Videos hosted somewhere a transcript can be pulled from.
			'hosted_videos' => (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$item_meta}
				 WHERE `key` = 'video_url'
				 AND ( value LIKE '%youtu%' OR value LIKE '%vimeo%' )"
			),
			'transcripts'   => (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->postmeta}
				 WHERE meta_key = 'transcript' AND meta_value <> ''"
			),
			'timestamps'    => (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->postmeta}
				 WHERE meta_key = 'message_timestamp' AND meta_value <> ''"
			),
		);

		set_transient( self::TRANSIENT, $usage, DAY_IN_SECONDS );

		return $usage;
	}

	/**
	 * Register the spotlights.
	 *
	 * @param array $notices The registered notices.
	 * @return array
	 * @since 1.7.0
	 */
	public function register_notices( $notices ) {
		$notices['spotlight-transcripts'] = array(
			'kind'      => 'spotlight',
			'title'     => __( 'Turn your videos into transcripts', 'cp-library' ),
			'body'      => __( 'CP Sermons can pull the transcript straight from a YouTube video, so every sermon becomes searchable — and readable for anyone who would rather not listen.', 'cp-library' ),
			'condition' => array( $this, 'wants_transcripts' ),
			'actions'   => array(
				array(
					'label'    => __( 'How transcripts work', 'cp-library' ),
					'url'      => Help::doc_url( 'timestamps-and-transcripts-cp-library' ),
					'external' => true,
					'primary'  => true,
				),
			),
		);

		$notices['spotlight-timestamps'] = array(
			'kind'      => 'spotlight',
			'title'     => __( 'Let people jump to the sermon', 'cp-library' ),
			'body'      => __( 'Add a timestamp to skip the announcements and the music, so the play button starts where the message does. Try it on this week\'s — you do not have to go back through the archive.', 'cp-library' ),
			'condition' => array( $this, 'wants_timestamps' ),
			'actions'   => array(
				array(
					'label'    => __( 'How timestamps work', 'cp-library' ),
					'url'      => Help::doc_url( 'timestamps-and-transcripts-cp-library' ),
					'external' => true,
					'primary'  => true,
				),
			),
		);

		$notices['spotlight-variations'] = array(
			'kind'      => 'spotlight',
			'title'     => __( 'One sermon, several services', 'cp-library' ),
			'body'      => __( 'You have service types set up. Variations let a single sermon carry a different video, speaker and timestamp for each one, instead of duplicating it.', 'cp-library' ),
			'condition' => array( $this, 'wants_variations' ),
			'actions'   => array(
				array(
					'label'    => __( 'How variations work', 'cp-library' ),
					'url'      => Help::doc_url( 'sermon-variations-cp-library' ),
					'external' => true,
					'primary'  => true,
				),
				array(
					'label' => __( 'Settings', 'cp-library' ),
					'url'   => admin_url( 'admin.php?page=cpl_advanced_options' ),
				),
			),
		);

		return $notices;
	}

	/**
	 * Worth suggesting transcripts?
	 *
	 * Only when there is a body of hosted video to work from and transcripts
	 * are the exception rather than the habit.
	 *
	 * @return bool
	 * @since 1.7.0
	 */
	public function wants_transcripts() {
		$usage = $this->get_usage();

		if ( $usage['hosted_videos'] < 10 ) {
			return false;
		}

		return $usage['transcripts'] < ( $usage['hosted_videos'] / 4 );
	}

	/**
	 * Worth suggesting timestamps?
	 *
	 * @return bool
	 * @since 1.7.0
	 */
	public function wants_timestamps() {
		$usage = $this->get_usage();

		return $usage['hosted_videos'] >= 10 && $usage['timestamps'] < 5;
	}

	/**
	 * Worth suggesting variations?
	 *
	 * Only to sites already running several service types — a single-service
	 * church has nothing to vary, and telling them about it is noise.
	 *
	 * @return bool
	 * @since 1.7.0
	 */
	public function wants_variations() {
		if ( ! cp_library()->setup->post_types->service_type_enabled() ) {
			return false;
		}

		if ( cp_library()->setup->variations->is_enabled() ) {
			return false;
		}

		$service_types = wp_count_posts( cp_library()->setup->post_types->service_type->post_type );

		return ! empty( $service_types->publish ) && $service_types->publish > 1;
	}
}
