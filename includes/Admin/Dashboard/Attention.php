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
			'title'    => __( 'Needs attention', 'cp-library' ),
			'column'   => 'main',
			'priority' => 20,
			'render'   => array( $this, 'render' ),
		);

		return $cards;
	}

	/**
	 * Render the card body.
	 *
	 * @return void
	 * @since 1.7.0
	 */
	public function render() {
		$problems = MissingContent::get_instance()->get_problems();

		if ( empty( $problems ) ) {
			// Rendered rather than hidden: an absent card reads as "not checked",
			// which is not the same as "nothing to fix".
			printf(
				'<p class="cpl-dashboard__all-clear">%s</p>',
				esc_html__( 'Everything looks good — no sermons are missing media, a speaker, or a series.', 'cp-library' )
			);

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
