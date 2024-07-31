<?php

namespace CP_Library\Setup;

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
	}

	protected function actions() {
		add_action( 'admin_menu', function () {
			global $submenu;
			$menu_type = cp_library()->get_admin_menu_slug();
			$menu_item = 'edit.php?post_type=' . $menu_type;

			$top_menu  = [];
			$tax_menu  = [];
			$cpt_menu  = [];
			$tool_menu = [];

			if ( empty( $submenu[ $menu_item ] ) ) {
				return;
			}

			foreach ( $submenu[ $menu_item ] as $item ) {
				if ( $item[2] === $menu_item || false !== strpos( $item[2], 'post-new.php' ) ) {
					$top_menu[] = $item;
				} elseif ( false !== strpos( $item[2], 'edit-tags.php?taxonomy=' ) ) {
					$tax_menu[] = $item;
				} elseif ( false !== strpos( $item[2], 'edit.php' ) && false === strpos( $item[2], 'cpl_template' ) ) {
					$cpt_menu[] = $item;
				} else {
					$tool_menu[] = $item;
				}
			}

			$submenu[ $menu_item ] = array_values( array_merge( $top_menu, $cpt_menu, $tax_menu, $tool_menu ) );
		}, 9999 );
	}

	/** Actions ***************************************************/

}
