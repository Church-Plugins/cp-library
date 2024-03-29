<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * CP Sermons block initialization class
 *
 * @package CP_Library/Setup/Blocks
 */

namespace CP_Library\Setup\Blocks;

use CP_Library\Admin\Settings;

/**
 * Setup plugin initialization for CPTs
 */
class Init {

	/**
	 * Single class instance
	 *
	 * @var Init
	 */
	protected static $_instance;

	/**
	 * Only make one instance of Init
	 *
	 * @return Init
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof Init ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor
	 *
	 * Run includes and actions on instantiation
	 */
	protected function __construct() {
		$this->includes();
		$this->actions();
	}

	/**
	 * Plugin init includes
	 *
	 * @return void
	 */
	protected function includes() {
		new ItemDate();
		new ItemDescription();
		new ItemGraphic();
		new ItemTitle();
		new Pagination();
		new Query();
		new SermonActions();
		new SermonScripture();
		new SermonSeries();
		new SermonSpeaker();
		new SermonTemplate();
		new SermonTopics();
		new ShortcodeTemplate();
	}

	/**
	 * Plugin init actions
	 *
	 * @return void
	 */
	protected function actions() {
		add_action( 'init', array( $this, 'register_block_patterns' ) );
		add_filter( 'block_categories_all', array( $this, 'block_categories' ) );
	}

	/**
	 * Adds a custom block category to be used by custom Gutenberg blocks
	 *
	 * @param array $categories the default block categories.
	 * @return array the updated block categories.
	 */
	public function block_categories( $categories ) {

		$categories[] = array(
			'slug'  => 'cp-library',
			'title' => cp_library()->get_plugin_name(),
		);

		$categories[] = array(
			'slug'  => 'cp-library-queries',
			'title' => cp_library()->get_plugin_name() . ' Queries',
		);

		return $categories;
	}

	/**
	 * Register block patterns
	 */
	public function register_block_patterns() {

		register_block_pattern_category( 'cpl_item', array(
			'label' => cp_library()->setup->post_types->item->plural_label
		) );

		register_block_pattern_category( 'cpl_item_type', array(
			'label' => cp_library()->setup->post_types->item_type->plural_label
		) );

		$patterns_dir = CP_LIBRARY_PLUGIN_DIR . 'block-patterns/';

		$files = glob( $patterns_dir . '*.php' );

		foreach ( $files as $file ) {
			$registered = register_block_pattern(
				'cp-library/' . basename( $file, '.php' ),
				require $file
			);
		}
	}
}
