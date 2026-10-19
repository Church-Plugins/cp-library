<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName
/**
 * Reset all sermons to visible, re-applying inheritance.
 *
 * @package CP_Library
 * @since 1.6.2
 */

namespace CP_Library\Admin\Migrate;

use CP_Library\Setup\Visibility;

/**
 * SermonVisibilityReset migration class
 *
 * Recovery tool for sites already affected by the 1.6.0 import bug, where
 * importers and adapters silently assigned the `cpl_visibility:hidden` term
 * to every newly created sermon. Re-applies the `public` term to every
 * sermon, then re-runs the inheritance check so sermons that should still
 * be hidden (because their series or service type is excluded) remain so.
 *
 * @since 1.6.2
 */
class SermonVisibilityReset extends Migration {

	/**
	 * The single instance of the class.
	 *
	 * @var SermonVisibilityReset
	 */
	protected static $_instance; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * The plugin name to migrate from
	 *
	 * @var string
	 */
	public $name = 'Sermon Visibility Reset';

	/**
	 * The migration type identifier
	 *
	 * @var string
	 */
	public $type = 'visibility_reset';

	/**
	 * Only make one instance.
	 *
	 * @return SermonVisibilityReset
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof SermonVisibilityReset ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Class constructor
	 */
	protected function __construct() {
		parent::__construct();
	}

	protected function authorize_request() {
		$this->authorize_visibility_tool();
	}

	/**
	 * Count all published/private/draft sermons.
	 *
	 * @return int
	 */
	public function get_item_count() {
		global $wpdb;

		$post_type = cp_library()->setup->post_types->item->post_type;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status IN ('publish', 'private', 'draft', 'future')",
				$post_type
			)
		);

		return absint( $count );
	}

	/**
	 * Get IDs of all sermons.
	 *
	 * @return int[]
	 */
	public function get_migration_data() {
		global $wpdb;

		$post_type = cp_library()->setup->post_types->item->post_type;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status IN ('publish', 'private', 'draft', 'future')",
				$post_type
			)
		);

		return array_map( 'absint', $ids );
	}

	/**
	 * Reset visibility for a single sermon, re-applying parent inheritance.
	 *
	 * @param mixed $post The sermon post ID.
	 */
	public function migrate_item( $post ) {
		$post_id = absint( $post );

		if ( ! $post_id ) {
			return;
		}

		$visibility = Visibility::get_instance();

		// If a parent series or service type still forces this hidden, honor it.
		if ( ! $visibility->should_be_visible( $post_id ) ) {
			$visibility->set_visibility( $post_id, false );
			return;
		}

		// Otherwise reset to the user's stored preference (defaults to visible).
		$visibility->set_visibility( $post_id, $visibility->get_user_visibility_preference( $post_id ) );
	}
}
