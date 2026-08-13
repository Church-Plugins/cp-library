<?php
/**
 * The "Needs attention" card on the CP Sermons dashboard.
 *
 * @package CP_Library
 * @since 1.7.0
 */

namespace CP_Library\Admin\Dashboard;

use CP_Library\Admin\MissingContent;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Sermons that are missing something, as a work queue.
 *
 * Every row links to the sermon list filtered to exactly those sermons, so the
 * card hands over a list to work through rather than a number to worry about.
 *
 * @since 1.7.0
 */
class Attention {

	/**
	 * The single instance of the class.
	 *
	 * @var Attention
	 */
	protected static $_instance; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Only make one instance of Attention.
	 *
	 * @return Attention
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof Attention ) {
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
		$cards['attention'] = array(
			'title'     => __( 'Housekeeping', 'cp-library' ),
			'column'    => 'main',
			// Last in the main column, deliberately. This is the one card that
			// reports work outstanding, and a panel people open to find out what
			// the plugin can do should not lead with a list of what is wrong.
			'priority'  => 90,
			'condition' => array( $this, 'has_problems' ),
			'render'    => array( $this, 'render' ),
		);

		return $cards;
	}

	/**
	 * Whether there is anything worth reporting.
	 *
	 * The counts are cached, so this is an option read on all but the first view
	 * after a sermon changes.
	 *
	 * @return bool
	 * @since 1.7.0
	 */
	public function has_problems() {
		return (bool) $this->get_problems();
	}

	/**
	 * The problems worth reporting, resolved once per request.
	 *
	 * Both the card's condition and its render need this, and without memoizing
	 * a cold cache would be paid for twice on the same page view.
	 *
	 * @return array
	 * @since 1.7.0
	 */
	protected function get_problems() {
		static $problems = null;

		if ( null === $problems ) {
			$problems = MissingContent::get_instance()->get_problems();
		}

		return $problems;
	}

	/**
	 * Render the card body.
	 *
	 * @return void
	 * @since 1.7.0
	 */
	public function render() {
		$problems = $this->get_problems();

		if ( empty( $problems ) ) {
			return;
		}
		?>
		<ul class="cpl-dashboard__attention">
			<?php foreach ( $problems as $key => $problem ) : ?>
				<li>
					<a href="<?php echo esc_url( $problem['url'] ); ?>">
						<span class="cpl-dashboard__attention-count"><?php echo esc_html( number_format_i18n( $problem['count'] ) ); ?></span>
						<span class="cpl-dashboard__attention-label"><?php echo esc_html( $problem['label'] ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}
}
