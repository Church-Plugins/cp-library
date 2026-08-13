<?php

namespace CP_Library\Setup;

use CP_Library\Admin\Dashboard;
use CP_Library\Admin\Settings;
use CP_Library\Setup\Blocks\Block;

/**
 * Setup plugin initialization
 */
class Init {

	/**
	 * @var Init
	 */
	protected static $_instance;

	/**
	 * @var Tables\Init
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public $tables;

	/**
	 * @var Podcast
	 * @since 1.0.4
	 */
	public $podcast;

	/**
	 * @var Variations
	 * @since 1.1.0
	 */
	public $variations;

	/**
	 * @var PostTypes\Init;
	 */
	public $post_types;

	/**
	 * @var Taxonomies\Init;
	 */
	public $taxonomies;

	/**
	 * @var Blocks\Init;
	 */
	public $blocks;
	
	/**
	 * @var Visibility;
	 */
	public $visibility;

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
	 */
	protected function __construct() {
		$this->includes();
		$this->actions();
	}

	/**
	 * Admin init includes
	 *
	 * @return void
	 */
	protected function includes() {
		Shortcode::get_instance();
		$this->podcast    = Podcast::get_instance();
		$this->variations = Variations::get_instance();
		$this->tables     = Tables\Init::get_instance();
		$this->post_types = PostTypes\Init::get_instance();
		$this->taxonomies = Taxonomies\Init::get_instance();
		$this->blocks     = Blocks\Init::get_instance();
		$this->visibility = Visibility::get_instance();
	}

	protected function actions() {
		add_action( 'admin_menu', function () {
			global $submenu;
			$menu_type = cp_library()->get_admin_menu_slug();
			$menu_item = 'edit.php?post_type=' . $menu_type;

			$dash_menu = [];
			$top_menu  = [];
			$tax_menu  = [];
			$cpt_menu  = [];
			$tool_menu = [];

			if ( empty( $submenu[ $menu_item ] ) ) {
				return;
			}

			foreach ( $submenu[ $menu_item ] as $item ) {
				if ( Dashboard::get_menu_link() === $item[2] ) {
					$dash_menu[] = $item;
				} elseif ( $item[2] === $menu_item || false !== strpos( $item[2], 'post-new.php' ) ) {
					$top_menu[] = $item;
				} elseif ( false !== strpos( $item[2], 'edit-tags.php?taxonomy=' ) ) {
					$tax_menu[] = $item;
				} elseif ( false !== strpos( $item[2], 'edit.php' ) && false === strpos( $item[2], 'cpl_template' ) ) {
					$cpt_menu[] = $item;
				} else {
					$tool_menu[] = $item;
				}
			}

			// Sit the dashboard directly above Settings. Anchoring to the Settings
			// entry rather than a fixed offset keeps it in place regardless of the
			// order the tool pages register in — CMB2 attaches Settings later than
			// the rest, on `init`. Deliberately not first: WordPress points a
			// top-level menu at its first submenu item, so leading with the
			// dashboard would take over the CP Sermons menu link itself.
			if ( ! empty( $dash_menu ) ) {
				$settings_index = null;

				foreach ( $tool_menu as $index => $item ) {
					if ( 'cpl_main_options' === $item[2] ) {
						$settings_index = $index;
						break;
					}
				}

				if ( null === $settings_index ) {
					$tool_menu = array_merge( $dash_menu, $tool_menu );
				} else {
					array_splice( $tool_menu, $settings_index, 0, $dash_menu );
				}
			}

			$submenu[ $menu_item ] = array_values( array_merge( $top_menu, $cpt_menu, $tax_menu, $tool_menu ) );
		}, 9999 );
	}

	/** Actions ***************************************************/

}
