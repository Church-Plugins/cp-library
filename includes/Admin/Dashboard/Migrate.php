<?php
/**
 * The import-from-another-plugin card on the CP Sermons dashboard.
 *
 * @package CP_Library
 * @since 1.7.0
 */

namespace CP_Library\Admin\Dashboard;

use CP_Library\Admin\Migrate\SetupWizard;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Offers the migration wizard to a site that still has sermons elsewhere.
 *
 * The wizard is good and it has exactly one door: Migrate\Init::on_activation()
 * redirects to it at the moment of activation, and that redirect is what writes
 * the option its menu entry is registered on. Activate by WP-CLI, or install CP
 * Sermons before the old plugin's content is there, or simply click away — and
 * the wizard is gone with no way back to it.
 *
 * The result is the worst thing this panel could do: tell someone to hand-add
 * their first sermon while several hundred of them sit in another plugin's
 * tables. So this card offers it, and takes priority over the setup checklist.
 *
 * The counts behind it are three COUNT(*) queries over wp_posts, so they ride
 * the daily rollup rather than running on every view.
 *
 * @since 1.7.0
 */
class Migrate {

	/**
	 * Where the discovered migrations are cached.
	 *
	 * @var string
	 */
	const TRANSIENT = 'cpl_available_migrations';

	/**
	 * The single instance of the class.
	 *
	 * @var Migrate
	 */
	protected static $_instance; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Only make one instance of Migrate.
	 *
	 * @return Migrate
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof Migrate ) {
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
		$cards['migrate'] = array(
			'title'     => __( 'Bring your sermons across', 'cp-library' ),
			'column'    => 'main',
			// Ahead of the setup checklist: importing is the answer to "add your
			// first sermon" for these sites.
			'priority'  => 6,
			'condition' => array( $this, 'has_migrations' ),
			'render'    => array( $this, 'render' ),
		);

		return $cards;
	}

	/**
	 * The migrations with content waiting, as name => count.
	 *
	 * @param bool $force Recompute rather than reading the cache.
	 * @return array
	 * @since 1.7.0
	 */
	public function get_available( $force = false ) {
		$cached = $force ? false : get_transient( self::TRANSIENT );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		// Refreshed by Rollup. Until it has run, say nothing rather than putting
		// three COUNT(*) queries on the render path of every dashboard view.
		if ( ! $force ) {
			return array();
		}

		$available = array();

		foreach ( SetupWizard::get_instance()->get_possible_migrations() as $migration ) {
			$available[ $migration->name ] = $migration->get_item_count();
		}

		set_transient( self::TRANSIENT, $available, DAY_IN_SECONDS );

		return $available;
	}

	/**
	 * Whether anything is waiting to be imported.
	 *
	 * @return bool
	 * @since 1.7.0
	 */
	public function has_migrations() {
		return (bool) $this->get_available();
	}

	/**
	 * Render the card body.
	 *
	 * @return void
	 * @since 1.7.0
	 */
	public function render() {
		$available = $this->get_available();
		$plural    = cp_library()->setup->post_types->item->plural_label;
		?>
		<ul class="cpl-dashboard__migrations">
			<?php foreach ( $available as $name => $count ) : ?>
				<li>
					<?php
					printf(
						/* translators: 1: number of sermons, 2: the plural sermon label, 3: the plugin they are in. */
						esc_html( _n( '%1$s %2$s in %3$s', '%1$s %2$s in %3$s', (int) $count, 'cp-library' ) ),
						'<strong>' . esc_html( number_format_i18n( $count ) ) . '</strong>',
						esc_html( strtolower( $plural ) ),
						esc_html( $name )
					);
					?>
				</li>
			<?php endforeach; ?>
		</ul>

		<p>
			<?php esc_html_e( 'CP Sermons can bring these across for you, with their titles, dates, speakers and media — rather than adding them again by hand.', 'cp-library' ); ?>
		</p>

		<p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . SetupWizard::$page_name ) ); ?>">
				<?php esc_html_e( 'Start the import', 'cp-library' ); ?>
			</a>
		</p>
		<?php
	}
}
