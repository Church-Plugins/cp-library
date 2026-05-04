<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName
/**
 * Convert legacy `show_in_main_list` sermon meta to `exclude_from_main_list`.
 *
 * @package CP_Library
 * @since 1.6.2
 */

namespace CP_Library\Admin\Migrate;

/**
 * VisibilityMetaMigration migration class
 *
 * In 1.6.0 the per-sermon visibility checkbox was a positive opt-in
 * (`show_in_main_list`, default checked). 1.6.2 inverts the convention
 * to `exclude_from_main_list` (default unchecked, matching the existing
 * Series and Service Type metaboxes) so that programmatic saves —
 * importers, adapters, REST, AJAX — default to visible.
 *
 * This migration walks every sermon that still has the legacy meta
 * key and rewrites it into the new key, preserving any explicit
 * "hide this sermon" intent the user set under the old system.
 *
 * @since 1.6.2
 */
class VisibilityMetaMigration extends Migration {

	/**
	 * The single instance of the class.
	 *
	 * @var VisibilityMetaMigration
	 */
	protected static $_instance; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * The plugin name to migrate from
	 *
	 * @var string
	 */
	public $name = 'Visibility Meta Migration';

	/**
	 * The migration type identifier
	 *
	 * @var string
	 */
	public $type = 'visibility_meta';

	/**
	 * Only make one instance.
	 *
	 * @return VisibilityMetaMigration
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof VisibilityMetaMigration ) {
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
	 * Count sermons that still have the legacy `show_in_main_list` meta.
	 *
	 * @return int
	 */
	public function get_item_count() {
		global $wpdb;

		$post_type = cp_library()->setup->post_types->item->post_type;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
				WHERE p.post_type = %s",
				'show_in_main_list',
				$post_type
			)
		);

		return absint( $count );
	}

	/**
	 * Get IDs of sermons with the legacy meta key.
	 *
	 * @return int[]
	 */
	public function get_migration_data() {
		global $wpdb;

		$post_type = cp_library()->setup->post_types->item->post_type;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
				WHERE p.post_type = %s",
				'show_in_main_list',
				$post_type
			)
		);

		return array_map( 'absint', $ids );
	}

	/**
	 * Convert legacy meta on a single sermon.
	 *
	 * Any truthy legacy value ('1', 'on', etc.) meant "show in main list."
	 * Any falsy value ('', '0', missing) meant "hide." Map accordingly to
	 * the new key, then delete the legacy meta.
	 *
	 * @param mixed $post The sermon post ID.
	 */
	public function migrate_item( $post ) {
		$post_id = absint( $post );

		if ( ! $post_id ) {
			return;
		}

		$legacy = get_post_meta( $post_id, 'show_in_main_list', true );

		if ( ! $legacy ) {
			update_post_meta( $post_id, 'exclude_from_main_list', '1' );
		} else {
			delete_post_meta( $post_id, 'exclude_from_main_list' );
		}

		delete_post_meta( $post_id, 'show_in_main_list' );

		// Invalidate the cached "legacy meta present" flag used by the
		// admin notice. Cheap to call per item; transient is deleted lazily.
		\CP_Library\Setup\Visibility::clear_legacy_meta_cache();
	}
}
