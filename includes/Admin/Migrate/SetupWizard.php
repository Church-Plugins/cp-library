<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName
/**
 * Class for displaying and running the migration wizard.
 *
 * @package CP_Library
 * @since 1.3.0
 */

namespace CP_Library\Admin\Migrate;

use CP_Library\Admin\Settings;
use CP_Library\Util\Convenience;

/**
 * Wizard class
 */
class SetupWizard {
	/**
	 * Sermon Manager
	 *
	 * @var SermonManager
	 */
	public $sermon_manager;

	/**
	 * Church Content
	 *
	 * @var ChurchContent
	 */
	public $church_content;

	/**
	 * The page name
	 *
	 * @var string
	 */
	public static $page_name = 'cp-library-setup-wizard';

	/**
	 * The single instance of the class.
	 *
	 * @var SetupWizard
	 */
	protected static $_instance; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Only make one instance of SetupWizard
	 *
	 * @return SetupWizard
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof SetupWizard ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Class constructor
	 */
	protected function __construct() {
		$this->includes();

		$wizard_initialized = get_option( 'cp-library-setup-wizard-initialized', false );

		if ( $wizard_initialized ) {
			$this->actions();
		}
	}

	/**
	 * SetupWizard actions
	 *
	 * @return void
	 */
	protected function actions() {
		add_action( 'admin_menu', array( $this, 'menu_item' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );
	}

	/**
	 * SetupWizard includes
	 *
	 * @return void
	 */
	protected function includes() {
		$this->sermon_manager = SermonManager::get_instance();
		$this->church_content = ChurchContent::get_instance();
	}

	/**
	 * Display page content
	 */
	public function menu_item() {
		$post_type = Convenience::get_primary_post_type();

		add_submenu_page(
			"edit.php?post_type=$post_type",
			__( 'Migrate', 'cp-library' ),
			__( 'Migrate', 'cp-library' ),
			'manage_options',
			'cp-library-setup-wizard',
			array( $this, 'page_content' )
		);
	}

	/**
	 * Display page content
	 */
	public function page_content() {
		$migrations = array();

		foreach ( $this->get_possible_migrations() as $migration ) {
			$migrations[] = array(
				'name'  => $migration->name,
				'type'  => $migration->type,
				'count' => $migration->get_item_count(),
			);
		}

		?>
		<div class="wrap"></div>
			<div class="postbox">
				<div class="inside">
					<h1>CP Library Migration Wizard</h1>
					<p>Thank you for choosing CP Library! It looks like you have content created with another sermon managing plugin. Would you like to run an automatic migration?</p>
					<div id="cpl-migration-root" data-details="<?php echo esc_attr( wp_json_encode( $migrations ) ); ?>"></div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Launches the setup wizard
	 */
	public function launch() {
		add_option( 'cp-library-setup-wizard-initialized', time() );
		header( 'Location: ' . admin_url( 'admin.php?page=cp-library-setup-wizard' ) );
		exit;
	}

	/**
	 * Clean up any wizard options. Runs when plugin is deactivated.
	 */
	public function cleanup() {
		delete_option( 'cp-library-setup-wizard-initialized' );
	}

	/**
	 * Enqueue scripts
	 */
	public function scripts() {
		$screen          = get_current_screen();
		$post_type       = Convenience::get_primary_post_type();
		$screen_check_id = "{$post_type}_page_" . self::$page_name;

		if ( $screen->id === $screen_check_id ) {
			\CP_Library\Init::get_instance()->enqueue->enqueue( 'app', 'migration', array( 'js_dep' => array( 'jquery' ) ) );
		}
	}

	/**
	 * Get array of migration classes
	 *
	 * @return array
	 */
	public function get_migrations() {
		return array(
			$this->sermon_manager,
			$this->church_content,
		);
	}

	/**
	 * Check if migration exists
	 *
	 * @return bool
	 */
	public function migration_exists() {
		foreach ( $this->get_migrations() as $migration ) {
			if ( $migration->get_item_count() > 0 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get possible migrations
	 *
	 * @return Migration[]
	 */
	public function get_possible_migrations() {
		$migrations = array();

		foreach ( $this->get_migrations() as $migration ) {
			if ( $migration->get_item_count() > 0 ) {
				$migrations[] = $migration;
			}
		}

		return $migrations;
	}

	/**
	 * Get migration by type
	 *
	 * @param string $type The migration type.
	 * @return Migration
	 * @throws \ChurchPlugins\Exception If invalid migration type.
	 */
	public function get_migration_by_type( $type ) {
		foreach ( $this->get_migrations() as $migration ) {
			if ( $migration->type === $type ) {
				return $migration;
			}
		}
		throw new \ChurchPlugins\Exception( 'Invalid migration type' );
	}

	/**
	 * Start migration.
	 *
	 * @param string $type The migration type.
	 */
	public function start_migration( $type ) {
		$migration = $this->get_migration_by_type( $type );

		$items = $migration->get_migration_data();

		foreach ( $items as $item ) {
			$migration->migrate_item( $item );
		}
	}
}
