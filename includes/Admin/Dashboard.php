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
	 * Render the dashboard.
	 *
	 * @return void
	 * @since 1.7.0
	 */
	public function page_callback() {
		?>
		<div class="wrap cpl-dashboard">
			<h1><?php esc_html_e( 'CP Sermons', 'cp-library' ); ?></h1>
			<hr class="wp-header-end">

			<?php
			/**
			 * Renders the contents of the CP Sermons dashboard.
			 *
			 * @since 1.7.0
			 */
			do_action( 'cpl_dashboard_content' );
			?>
		</div>
		<?php
	}
}
