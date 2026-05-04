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
	 * Count sermons that need visibility-meta migration.
	 *
	 * Includes both legacy `show_in_main_list` carriers and pre-1.6.2 hidden
	 * sermons whose CMB2 field deleted the legacy meta on uncheck (they have
	 * a `cpl_visibility:hidden` term but no preference postmeta).
	 *
	 * @return int
	 */
	public function get_item_count() {
		global $wpdb;

		$count = $wpdb->get_var( $this->affected_sermons_sql( 'COUNT(DISTINCT p.ID)' ) );

		return absint( $count );
	}

	/**
	 * Get IDs of sermons that need visibility-meta migration.
	 *
	 * @return int[]
	 */
	public function get_migration_data() {
		global $wpdb;

		$ids = $wpdb->get_col( $this->affected_sermons_sql( 'DISTINCT p.ID' ) );

		return array_map( 'absint', $ids );
	}

	/**
	 * Build a prepared query against the set of sermons needing migration.
	 *
	 * Two cohorts are unioned via OR in the WHERE clause:
	 *  1. Sermons with a `show_in_main_list` postmeta row (legacy field).
	 *  2. Sermons in `cpl_visibility:hidden` that have neither legacy meta
	 *     nor the new `exclude_from_main_list` meta — pre-1.6.2 hidden
	 *     sermons that would otherwise silently flip to public on save.
	 *
	 * @param string $select_clause The SELECT list to project (e.g. 'DISTINCT p.ID').
	 *
	 * @return string Prepared SQL.
	 */
	protected function affected_sermons_sql( $select_clause ) {
		global $wpdb;

		$post_type = cp_library()->setup->post_types->item->post_type;

		return $wpdb->prepare(
			"SELECT {$select_clause}
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} legacy
			       ON legacy.post_id = p.ID AND legacy.meta_key = %s
			LEFT JOIN {$wpdb->postmeta} new_meta
			       ON new_meta.post_id = p.ID AND new_meta.meta_key = %s
			LEFT JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
			LEFT JOIN {$wpdb->term_taxonomy} tt
			       ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = %s
			LEFT JOIN {$wpdb->terms} t
			       ON t.term_id = tt.term_id AND t.slug = %s
			WHERE p.post_type = %s
			  AND (
			        legacy.meta_id IS NOT NULL
			     OR ( t.term_id IS NOT NULL AND new_meta.meta_id IS NULL )
			  )",
			'show_in_main_list',
			'exclude_from_main_list',
			'cpl_visibility',
			'hidden',
			$post_type
		);
	}

	/**
	 * Migrate a single sermon's visibility state to the new meta key.
	 *
	 * Two branches:
	 *  - If legacy `show_in_main_list` exists: any truthy value ('1', 'on')
	 *    meant "show in main list" → clear `exclude_from_main_list`. Any
	 *    falsy value ('', '0') meant "hide" → set `exclude_from_main_list`
	 *    to '1'. Then delete the legacy key.
	 *  - If no legacy meta but the sermon is currently in `cpl_visibility:hidden`
	 *    and has no `exclude_from_main_list` meta: pre-1.6.2 hidden sermon.
	 *    Backfill `exclude_from_main_list` = '1' to lock in the hidden state
	 *    so future saves do not flip it to public.
	 *
	 * @param mixed $post The sermon post ID.
	 */
	public function migrate_item( $post ) {
		$post_id = absint( $post );

		if ( ! $post_id ) {
			return;
		}

		if ( metadata_exists( 'post', $post_id, 'show_in_main_list' ) ) {
			$legacy = get_post_meta( $post_id, 'show_in_main_list', true );

			if ( ! $legacy ) {
				update_post_meta( $post_id, 'exclude_from_main_list', '1' );
			} else {
				delete_post_meta( $post_id, 'exclude_from_main_list' );
			}

			delete_post_meta( $post_id, 'show_in_main_list' );
		} elseif ( ! metadata_exists( 'post', $post_id, 'exclude_from_main_list' )
		           && has_term( 'hidden', 'cpl_visibility', $post_id ) ) {
			update_post_meta( $post_id, 'exclude_from_main_list', '1' );
		}

		// Invalidate the cached "legacy meta present" flag used by the
		// admin notice. Cheap to call per item; transient is deleted lazily.
		\CP_Library\Setup\Visibility::clear_legacy_meta_cache();
	}
}
