<?php
/**
 * The CP Sermons dashboard screen.
 *
 * @package CP_Library
 * @since 1.7.0
 */

namespace CP_Library\Admin;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * The CP Sermons dashboard.
 *
 * Registers as a submenu of the shared "Church Plugins" menu, alongside the
 * other Church Plugins products. The existing CP Sermons menu (the Sermons post
 * type and its submenus) is left untouched.
 *
 * @since 1.7.0
 */
class Dashboard {

	/**
	 * The dashboard page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'cpl_dashboard';

	/**
	 * The single instance of the class.
	 *
	 * @var Dashboard
	 */
	protected static $_instance; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * The page hook suffix returned by add_submenu_page().
	 *
	 * @var string|false
	 */
	protected $hook_suffix = false;

	/**
	 * Only make one instance of Dashboard.
	 *
	 * @return Dashboard
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof Dashboard ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor.
	 */
	protected function __construct() {
		// The shared parent menu only registers when a product declares support,
		// and it declares on `admin_menu` at priority 9 — so this has to run
		// before `admin_menu` fires. Guarded because the bundled ChurchPlugins
		// library only ships Admin\Menu as of 1.1.16.
		if ( ! class_exists( '\ChurchPlugins\Admin\Menu' ) ) {
			return;
		}

		\ChurchPlugins\Admin\Menu::add_support();

		Dashboard\Notices::get_instance();
		Dashboard\Spotlight::get_instance();
		Dashboard\Setup::get_instance();
		Dashboard\Placement::get_instance();
		Dashboard\SettingsLinks::get_instance();
		Dashboard\Attention::get_instance();
		Dashboard\AnalyticsSnapshot::get_instance();
		Dashboard\PodcastCard::get_instance();
		Dashboard\Integrations::get_instance();
		Dashboard\Help::get_instance();

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * The dashboard page slug.
	 *
	 * @return string
	 * @since 1.7.0
	 */
	public static function get_slug() {
		return self::PAGE_SLUG;
	}

	/**
	 * The dashboard URL.
	 *
	 * @return string
	 * @since 1.7.0
	 */
	public static function get_url() {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}

	/**
	 * The admin-relative link to the dashboard.
	 *
	 * Used as the menu slug for the CP Sermons menu entry. A slug containing
	 * '.php' makes WordPress render the item as a plain link rather than
	 * registering a second page, so the dashboard keeps a single callback.
	 *
	 * @return string
	 * @since 1.7.0
	 */
	public static function get_menu_link() {
		return 'admin.php?page=' . self::PAGE_SLUG;
	}

	/**
	 * The capability required to view the dashboard.
	 *
	 * Matches the capability the shared parent menu registers with, so the
	 * dashboard is visible to exactly the users who can see the menu it lives in.
	 *
	 * @return string
	 * @since 1.7.0
	 */
	public static function get_capability() {
		/**
		 * Filters the capability required to view the CP Sermons dashboard.
		 *
		 * @param string $capability The capability. Default 'manage_options'.
		 * @since 1.7.0
		 */
		return apply_filters( 'cpl_dashboard_capability', 'manage_options' );
	}

	/**
	 * The page hook suffix, once the menu has been registered.
	 *
	 * @return string|false
	 * @since 1.7.0
	 */
	public function get_hook_suffix() {
		return $this->hook_suffix;
	}

	/**
	 * Register the dashboard under the shared Church Plugins menu.
	 *
	 * @return void
	 * @since 1.7.0
	 */
	public function register_menu() {
		$this->hook_suffix = add_submenu_page(
			\ChurchPlugins\Admin\Menu::get_slug(),
			__( 'CP Sermons', 'cp-library' ),
			__( 'CP Sermons', 'cp-library' ),
			self::get_capability(),
			self::PAGE_SLUG,
			array( $this, 'page_callback' )
		);

		if ( $this->hook_suffix ) {
			add_action( "load-{$this->hook_suffix}", array( $this, 'enqueue' ) );
		}

		// Also surface it in the CP Sermons menu, where people already work.
		// Registered as a link rather than a page so there is only ever one
		// dashboard callback; Setup\Init sorts it to the top of that menu.
		add_submenu_page(
			'edit.php?post_type=' . \CP_Library\Util\Convenience::get_primary_post_type(),
			__( 'Dashboard', 'cp-library' ),
			__( 'Dashboard', 'cp-library' ),
			self::get_capability(),
			self::get_menu_link()
		);
	}

	/**
	 * Enqueue the dashboard's own assets.
	 *
	 * Hooked to `load-{$hook}` so it only runs on this screen. The admin
	 * stylesheet and main script already load here via Init::is_admin_page().
	 *
	 * @return void
	 * @since 1.7.0
	 */
	public function enqueue() {
		add_action(
			'admin_enqueue_scripts',
			function () {
				// Fourth argument is $is_style — this bundle is script only.
				cp_library()->enqueue_asset( 'admin-dashboard', array( 'wp-a11y', 'wp-i18n' ), false, false, true );
			}
		);
	}

	/**
	 * Render the dashboard.
	 *
	 * @return void
	 * @since 1.7.0
	 */
	public function page_callback() {
		$columns = $this->get_cards();

		// A column with nothing in it would otherwise hold open a third of the
		// page, so fall back to a single column until both sides have cards.
		$single = empty( $columns['main'] ) || empty( $columns['side'] );
		?>
		<div class="wrap cpl-dashboard">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'CP Sermons', 'cp-library' ); ?></h1>
			<?php $this->render_header_actions(); ?>
			<hr class="wp-header-end">

			<div class="cpl-dashboard__grid<?php echo $single ? ' cpl-dashboard__grid--single' : ''; ?>">
				<?php foreach ( $columns as $name => $cards ) : ?>
					<?php if ( empty( $cards ) ) { continue; } ?>
					<div class="cpl-dashboard__column cpl-dashboard__column--<?php echo esc_attr( $name ); ?>">
						<?php array_walk( $cards, array( $this, 'render_card' ) ); ?>
					</div>
				<?php endforeach; ?>
			</div>

			<?php
			/**
			 * Renders extra content at the foot of the CP Sermons dashboard.
			 *
			 * Prefer the `cpl_dashboard_cards` filter — this is an escape hatch
			 * for output that does not belong in a card.
			 *
			 * @since 1.7.0
			 */
			do_action( 'cpl_dashboard_content' );
			?>
		</div>
		<?php
	}

	/**
	 * Render the actions that sit beside the page title.
	 *
	 * @return void
	 * @since 1.7.0
	 */
	protected function render_header_actions() {
		$item = cp_library()->setup->post_types->item;
		$type = get_post_type_object( $item->post_type );

		if ( ! $type || ! current_user_can( $type->cap->create_posts ) ) {
			return;
		}

		printf(
			' <a href="%s" class="page-title-action">%s</a>',
			esc_url( admin_url( 'post-new.php?post_type=' . $item->post_type ) ),
			/* translators: %s: the singular sermon label, e.g. "Sermon" or "Message". */
			esc_html( sprintf( __( 'Add %s', 'cp-library' ), $item->single_label ) )
		);
	}

	/**
	 * The cards to render, grouped by column and ordered by priority.
	 *
	 * @return array {
	 *     @type array $main Cards for the wide column.
	 *     @type array $side Cards for the narrow column.
	 * }
	 * @since 1.7.0
	 */
	protected function get_cards() {
		/**
		 * Filters the cards shown on the CP Sermons dashboard.
		 *
		 * Each card is an array of:
		 *  - id         string   Unique slug. Defaults to the array key.
		 *  - title      string   Card heading. Omit for a headless card.
		 *  - column     string   'main' (wide) or 'side' (narrow). Default 'main'.
		 *  - priority   int      Sort order within the column. Default 10.
		 *  - capability string   Capability to view. Defaults to the page's.
		 *                        Pass '' to show to anyone who can see the page.
		 *  - condition  callable Optional. Return false to hide the card.
		 *  - render     callable Required. Echoes the card body.
		 *
		 * Note that `condition` runs on every view of this page, for every
		 * registered card, before anything is rendered. Keep it to option reads
		 * or cache its result — see Dashboard\AnalyticsSnapshot::rollup() for the
		 * shape to copy when a condition needs to hit the database.
		 *
		 * @param array $cards The registered cards.
		 * @since 1.7.0
		 */
		$cards = apply_filters( 'cpl_dashboard_cards', array() );

		$columns = array(
			'main' => array(),
			'side' => array(),
		);

		foreach ( (array) $cards as $key => $card ) {
			if ( ! is_array( $card ) ) {
				continue;
			}

			$card = wp_parse_args(
				$card,
				array(
					'id'         => is_string( $key ) ? $key : '',
					'title'      => '',
					'column'     => 'main',
					'priority'   => 10,
					'capability' => self::get_capability(),
					'condition'  => null,
					'render'     => null,
				)
			);

			if ( ! is_callable( $card['render'] ) ) {
				continue;
			}

			if ( ! empty( $card['capability'] ) && ! current_user_can( $card['capability'] ) ) {
				continue;
			}

			if ( is_callable( $card['condition'] ) && ! call_user_func( $card['condition'], $card ) ) {
				continue;
			}

			$column = isset( $columns[ $card['column'] ] ) ? $card['column'] : 'main';

			$columns[ $column ][] = $card;
		}

		foreach ( $columns as &$column ) {
			usort(
				$column,
				function ( $a, $b ) {
					return $a['priority'] <=> $b['priority'];
				}
			);
		}

		return $columns;
	}

	/**
	 * Render a single card.
	 *
	 * @param array $card A normalized card definition.
	 * @return void
	 * @since 1.7.0
	 */
	protected function render_card( $card ) {
		?>
		<div class="postbox cpl-dashboard__card" <?php echo $card['id'] ? 'id="cpl-card-' . esc_attr( $card['id'] ) . '"' : ''; ?>>
			<?php if ( $card['title'] ) : ?>
				<div class="postbox-header">
					<h2 class="hndle"><?php echo esc_html( $card['title'] ); ?></h2>
				</div>
			<?php endif; ?>
			<div class="inside">
				<?php call_user_func( $card['render'], $card ); ?>
			</div>
		</div>
		<?php
	}
}
