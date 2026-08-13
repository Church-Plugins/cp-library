<?php
/**
 * The podcast card on the CP Sermons dashboard.
 *
 * @package CP_Library
 * @since 1.7.0
 */

namespace CP_Library\Admin\Dashboard;

use CP_Library\Admin\Settings\Podcast as PodcastSettings;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * The feed URL, and whether the feed will actually be accepted.
 *
 * Answers the two questions this feature generates: "what is my feed address"
 * and "why did Apple reject my feed". Both are answerable from options alone,
 * so the card costs no queries beyond the artwork lookup, which only runs when
 * artwork has been chosen.
 *
 * Named PodcastCard rather than Podcast because Setup\Podcast and
 * Admin\Settings\Podcast already exist and the three are easy to confuse.
 *
 * @since 1.7.0
 */
class PodcastCard {

	/**
	 * The smallest artwork Apple Podcasts accepts, per side.
	 *
	 * @var int
	 */
	const MIN_ARTWORK = 1400;

	/**
	 * The largest artwork Apple Podcasts accepts, per side.
	 *
	 * @var int
	 */
	const MAX_ARTWORK = 3000;

	/**
	 * The single instance of the class.
	 *
	 * @var PodcastCard
	 */
	protected static $_instance; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Only make one instance of PodcastCard.
	 *
	 * @return PodcastCard
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof PodcastCard ) {
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
		$cards['podcast'] = array(
			'title'     => __( 'Podcast', 'cp-library' ),
			'column'    => 'side',
			'priority'  => 20,
			'condition' => array( $this, 'is_enabled' ),
			'render'    => array( $this, 'render' ),
		);

		return $cards;
	}

	/**
	 * Whether the podcast feed is switched on.
	 *
	 * A single option read — this runs on every dashboard view.
	 *
	 * @return bool
	 * @since 1.7.0
	 */
	public function is_enabled() {
		return (bool) cp_library()->setup->podcast->is_enabled();
	}

	/**
	 * The feed requirements, and whether each is met.
	 *
	 * Only the tags Apple rejects a feed over. Everything else in the podcast
	 * settings either has a default (title, author, category, language) or is
	 * optional, and listing satisfied requirements would bury the two that are
	 * usually the problem.
	 *
	 * @return array Each entry has a label, a met bool, and an optional note.
	 * @since 1.7.0
	 */
	public function get_requirements() {
		$requirements = array(
			'image'    => array(
				'label' => __( 'Cover art', 'cp-library' ),
				'met'   => false,
				'note'  => '',
			),
			'email'    => array(
				'label' => __( 'Owner email', 'cp-library' ),
				'met'   => (bool) PodcastSettings::get( 'email', '' ),
				'note'  => '',
			),
			'category' => array(
				'label' => __( 'Category', 'cp-library' ),
				'met'   => (bool) PodcastSettings::get( 'category', '' ),
				'note'  => '',
			),
		);

		$artwork = $this->check_artwork();

		$requirements['image']['met']  = $artwork['met'];
		$requirements['image']['note'] = $artwork['note'];

		return $requirements;
	}

	/**
	 * Whether the cover art exists and is a size Apple will accept.
	 *
	 * Artwork that is merely present is not enough — art below 1400px is
	 * rejected outright, and that failure is invisible from the settings screen,
	 * which happily shows a 600px thumbnail as if it were fine.
	 *
	 * @return array {
	 *     @type bool   $met  Whether the artwork passes.
	 *     @type string $note What is wrong with it, when something is.
	 * }
	 * @since 1.7.0
	 */
	protected function check_artwork() {
		$image = PodcastSettings::get( 'image', '' );

		if ( ! $image ) {
			return array(
				'met'  => false,
				'note' => '',
			);
		}

		$id = attachment_url_to_postid( $image );

		if ( ! $id ) {
			// Artwork set to something outside the media library; its dimensions
			// cannot be checked from here, so take it on trust rather than
			// reporting a problem that may not exist.
			return array(
				'met'  => true,
				'note' => '',
			);
		}

		$meta = wp_get_attachment_metadata( $id );

		if ( empty( $meta['width'] ) || empty( $meta['height'] ) ) {
			return array(
				'met'  => true,
				'note' => '',
			);
		}

		$smallest = min( (int) $meta['width'], (int) $meta['height'] );
		$largest  = max( (int) $meta['width'], (int) $meta['height'] );

		if ( $smallest < self::MIN_ARTWORK || $largest > self::MAX_ARTWORK ) {
			return array(
				'met'  => false,
				'note' => sprintf(
					/* translators: 1: image width, 2: image height, 3: minimum size, 4: maximum size. */
					__( '%1$d×%2$d — must be between %3$d×%3$d and %4$d×%4$d', 'cp-library' ),
					(int) $meta['width'],
					(int) $meta['height'],
					self::MIN_ARTWORK,
					self::MAX_ARTWORK
				),
			);
		}

		return array(
			'met'  => true,
			'note' => '',
		);
	}

	/**
	 * Render the card body.
	 *
	 * @return void
	 * @since 1.7.0
	 */
	public function render() {
		$feed_url     = cp_library()->setup->podcast->get_feed_url();
		$requirements = $this->get_requirements();
		$outstanding  = array_filter( $requirements, function ( $requirement ) {
			return ! $requirement['met'];
		} );
		?>
		<p class="cpl-dashboard__feed">
			<a href="<?php echo esc_url( $feed_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $feed_url ); ?></a>
		</p>

		<?php if ( empty( $outstanding ) ) : ?>
			<p class="cpl-dashboard__all-clear"><?php esc_html_e( 'Ready to submit.', 'cp-library' ); ?></p>
		<?php else : ?>
			<ul class="cpl-dashboard__requirements">
				<?php foreach ( $outstanding as $requirement ) : ?>
					<li>
						<span class="dashicons dashicons-warning" aria-hidden="true"></span>
						<span>
							<?php echo esc_html( $requirement['label'] ); ?>
							<?php if ( $requirement['note'] ) : ?>
								<em class="cpl-dashboard__requirement-note"><?php echo esc_html( $requirement['note'] ); ?></em>
							<?php endif; ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
			<p>
				<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=cpl_podcast_options' ) ); ?>">
					<?php esc_html_e( 'Podcast settings', 'cp-library' ); ?>
				</a>
			</p>
		<?php endif; ?>
		<?php
	}
}
