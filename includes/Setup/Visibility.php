<?php

namespace CP_Library\Setup;

use CP_Library\Admin\Settings;
use CP_Library\Models\Item;
use CP_Library\Models\ItemType;
use CP_Library\Models\ServiceType;

/**
 * Handles visibility controls for CP Library entities
 *
 * @since 1.6.0
 */
class Visibility {

	/**
	 * @var Visibility
	 */
	protected static $_instance;

	/**
	 * @var string The taxonomy name for visibility
	 */
	protected $taxonomy = 'cpl_visibility';

	/**
	 * @var string The term slug for public entities
	 */
	protected $public_term = 'public';

	/**
	 * @var string The term slug for hidden entities
	 */
	protected $hidden_term = 'hidden';

	/**
	 * Only make one instance of Visibility
	 *
	 * @return Visibility
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof Visibility ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor - add actions and filters
	 */
	protected function __construct() {
		// Bail if functionality is disabled via filter
		if ( apply_filters( 'cpl_disable_visibility_feature', false ) ) {
			return;
		}

		add_action( 'init', [ $this, 'register_taxonomy' ], 9 );
		add_action( 'pre_get_posts', [ $this, 'filter_query' ], 500 );

		// handle shortcodes
		add_filter( 'pre_do_shortcode_tag', [ $this, 'maybe_filter_shortcode' ], 10, 2 );
		add_filter( 'do_shortcode_tag', [ $this, 'remove_filter' ], 10, 2 );

		// Hook into entity types for UI
		add_action( 'cmb2_admin_init', [ $this, 'add_metaboxes' ] );

		// JavaScript for admin
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );

		// Save actions. Run after CMB2
		add_action( 'save_post', [ $this, 'maybe_update_visibility' ], 100 );

		// One-time admin notice for the 1.6.0 → 1.6.2 visibility flip.
		add_action( 'admin_init', [ $this, 'maybe_dismiss_flip_notice' ] );
		add_action( 'admin_notices', [ $this, 'maybe_render_flip_notice' ] );
	}

	public function maybe_filter_shortcode( $value, $tag ) {
		if ( str_contains( $tag, 'cp' ) ) {
			add_filter( 'cpl_bypass_visibility_filtering', [ $this, '_return_false' ] );
		}

		return $value;
	}

	public function remove_filter( $output ) {
		remove_filter( 'cpl_bypass_visibility_filtering', [ $this, '_return_false' ] );
		return $output;
	}

	public function _return_false() {
		return false;
	}

	/**
	 * Register the visibility taxonomy
	 */
	public function register_taxonomy() {
		$post_types = apply_filters( 'cpl_visibility_post_types', [
			'cpl_item',
			'cpl_item_type',
			'cpl_service_type'
		] );

		$args = apply_filters( 'cpl_visibility_taxonomy_args', [
			'hierarchical'       => false,
			'public'             => false,
			'show_ui'            => false,
			'show_in_menu'       => false,
			'show_in_nav_menus'  => false,
			'show_in_rest'       => true,
			'show_in_quick_edit' => false,
			'show_admin_column'  => false,
			'query_var'          => false,
			'rewrite'            => false,
			'meta_box_cb'        => false,
		] );

		register_taxonomy( $this->taxonomy, $post_types, $args );

		// Ensure terms exist
		if ( ! term_exists( $this->public_term, $this->taxonomy ) ) {
			wp_insert_term( $this->public_term, $this->taxonomy );
		}

		if ( ! term_exists( $this->hidden_term, $this->taxonomy ) ) {
			wp_insert_term( $this->hidden_term, $this->taxonomy );
		}
	}

	/**
	 * Add visibility metaboxes to entity types
	 */
	public function add_metaboxes() {
		// Only proceed if not disabled
		if ( apply_filters( 'cpl_disable_visibility_metaboxes', false ) ) {
			return;
		}

		$this->add_service_type_metabox();
		$this->add_item_type_metabox();
		$this->add_item_metabox();
	}

	/**
	 * Add metabox to Service Types
	 */
	protected function add_service_type_metabox() {
		$cmb = new_cmb2_box( [
			'id'           => 'cpl_service_type_visibility',
			'title'        => __( 'Visibility Settings', 'cp-library' ),
			'object_types' => [ 'cpl_service_type' ],
			'context'      => 'side',
			'priority'     => 'low',
		] );

		$cmb->add_field( [
			'name'        => __( 'Exclude from Main List', 'cp-library' ),
			'desc'        => __( 'When checked, sermons with this service type will not appear in the main sermon list. They will still appear in service type archives.', 'cp-library' ),
			'id'          => 'exclude_from_main_list',
			'type'        => 'checkbox',
			'default'     => false,
			'after_field' => '<p class="cmb2-metabox-description">' . __( 'This setting affects all sermons associated with this service type.', 'cp-library' ) . '</p>',
		] );
	}

	/**
	 * Add metabox to Series (Item Types)
	 */
	protected function add_item_type_metabox() {
		$cmb = new_cmb2_box( [
			'id'           => 'cpl_item_type_visibility',
			'title'        => __( 'Visibility Settings', 'cp-library' ),
			'object_types' => [ 'cpl_item_type' ],
			'context'      => 'side',
			'priority'     => 'low',
		] );

		$cmb->add_field( [
			'name'        => __( 'Exclude from Main List', 'cp-library' ),
			'desc'        => __( 'When checked, sermons in this series will not appear in the main sermon list. They will still appear in series archives.', 'cp-library' ),
			'id'          => 'exclude_from_main_list',
			'type'        => 'checkbox',
			'default'     => false,
			'after_field' => '<p class="cmb2-metabox-description">' . __( 'This setting affects all sermons in this series.', 'cp-library' ) . '</p>',
		] );
	}

	/**
	 * Add metabox to Sermons (Items)
	 */
	protected function add_item_metabox() {
		$cmb = new_cmb2_box( [
			'id'           => 'cpl_item_visibility',
			'title'        => __( 'Visibility Settings', 'cp-library' ),
			'object_types' => [ 'cpl_item' ],
			'context'      => 'side',
			'priority'     => 'low',
		] );

		$cmb->add_field( [
			'name'       => __( 'Exclude from Main List', 'cp-library' ),
			'desc'       => __( 'When checked, this sermon will not appear in the main sermon list. It will still appear on its single page and in taxonomy archives.', 'cp-library' ),
			'id'         => 'exclude_from_main_list',
			'type'       => 'checkbox',
			'default'    => false,
			'attributes' => [
				'data-conditional-id'    => 'cpl_visibility_inherited',
				'data-conditional-value' => 'false',
			],
		] );

		$cmb->add_field( [
			'id'      => 'cpl_visibility_inherited',
			'type'    => 'hidden',
			'default' => 'false',
		] );

		$cmb->add_field( [
			'id'         => 'cpl_visibility_notice',
			'type'       => 'title',
			'before_row' => '<div id="cpl-visibility-notice" style="display:none;">',
			'after_row'  => '</div>',
		] );
	}

	/**
	 * Enqueue JavaScript for admin UI
	 */
	public function enqueue_admin_scripts( $hook ) {
		$screen = get_current_screen();

		if ( ! isset( $screen->post_type ) || $screen->post_type !== 'cpl_item' ) {
			return;
		}

		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ] ) ) {
			return;
		}

		wp_enqueue_script(
			'cpl-visibility-admin',
			CP_LIBRARY_PLUGIN_URL . 'assets/js/admin-visibility.js',
			[ 'jquery' ],
			CP_LIBRARY_PLUGIN_VERSION,
			true
		);

		wp_localize_script( 'cpl-visibility-admin', 'cplVisibility', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'cpl_visibility_nonce' ),
			'strings' => [
				'inherited_series'       => __( 'This sermon is hidden because it belongs to a hidden Series.', 'cp-library' ),
				'inherited_service_type' => __( 'This sermon is hidden because it belongs to a hidden Service Type.', 'cp-library' ),
				'inherited_both'         => __( 'This sermon is hidden because it belongs to a hidden Series and Service Type.', 'cp-library' ),
			],
		] );
	}

	/**
	 * Filter the main query to respect visibility
	 */
	public function filter_query( $query ) {

		// Only apply on the frontend main query
		if ( apply_filters( 'cpl_bypass_visibility_filtering', is_admin() || ! $query->is_main_query(), $query ) ) {
			return;
		}

		$post_type = $query->get( 'post_type' );

		// Handle sermon listings
		if ( $post_type == 'cpl_item' && ! $query->is_singular() ) {
			$this->filter_sermon_query( $query );

			return;
		}

		// Handle series listings
		if ( $post_type == 'cpl_item_type' && ! $query->is_singular() ) {
			$this->filter_series_query( $query );

			return;
		}
	}

	/**
	 * Filter sermon queries to respect visibility
	 */
	protected function filter_sermon_query( $query ) {
		// If we're on a taxonomy archive or explicit service type page, don't filter visibility
		if ( isset( $_GET['service-type'] ) || is_tax() ) {
			return;
		}

		// Add tax query to include public or unset visibility
		$tax_query = $query->get( 'tax_query' );
		if ( ! is_array( $tax_query ) ) {
			$tax_query = [];
		}

		$visibility_query = [
			'relation' => 'OR',
			[
				'taxonomy' => $this->taxonomy,
				'field'    => 'slug',
				'terms'    => $this->public_term,
				'operator' => 'IN',
			],
			[
				'taxonomy' => $this->taxonomy,
				'field'    => 'slug',
				'operator' => 'NOT EXISTS',
			],
		];

		// Allow developers to modify the visibility tax query
		$visibility_query = apply_filters( 'cpl_visibility_tax_query', $visibility_query, $query );

		if ( ! empty( $tax_query ) ) {
			$tax_query = [
				'relation' => 'AND',
				$visibility_query,
				$tax_query,
			];
		} else {
			$tax_query = $visibility_query;
		}

		$query->set( 'tax_query', $tax_query );
	}

	/**
	 * Filter series queries to respect visibility
	 */
	protected function filter_series_query( $query ) {
		// If we're on a taxonomy archive, don't filter visibility
		if ( is_tax() ) {
			return;
		}

		// Add tax query to include public or unset visibility
		$tax_query = $query->get( 'tax_query' );
		if ( ! is_array( $tax_query ) ) {
			$tax_query = [];
		}

		$visibility_query = [
			'relation' => 'OR',
			[
				'taxonomy' => $this->taxonomy,
				'field'    => 'slug',
				'terms'    => $this->public_term,
				'operator' => 'IN',
			],
			[
				'taxonomy' => $this->taxonomy,
				'field'    => 'slug',
				'operator' => 'NOT EXISTS',
			],
		];

		// Allow developers to modify the series visibility tax query
		$visibility_query = apply_filters( 'cpl_visibility_series_tax_query', $visibility_query, $query );

		if ( ! empty( $tax_query ) ) {
			$tax_query = [
				'relation' => 'AND',
				$visibility_query,
				$tax_query,
			];
		} else {
			$tax_query = $visibility_query;
		}

		$query->set( 'tax_query', $tax_query );
	}

	/**
	 * Check if an item should be visible based on its parents
	 *
	 * @param int $post_id The post ID to check
	 *
	 * @return bool Whether the item should be visible
	 */
	public function should_be_visible( $post_id ) {
		$post_type = get_post_type( $post_id );

		if ( $post_type !== 'cpl_item' ) {
			return true;
		}

		try {
			$item = Item::get_instance_from_origin( $post_id );

			// Check series visibility
			$item_types = $item->get_types();
			foreach ( $item_types as $item_type_id ) {
				$item_type = ItemType::get_instance( $item_type_id );
				if ( get_post_meta( $item_type->origin_id, 'exclude_from_main_list', true ) ) {
					return false;
				}
			}

			// Check service type visibility
			$service_types = $item->get_service_types();
			foreach ( $service_types as $service_type_id ) {
				$service_type = ServiceType::get_instance( $service_type_id );
				if ( get_post_meta( $service_type->origin_id, 'exclude_from_main_list', true ) ) {
					return false;
				}
			}

			return true;
		} catch ( \Exception $e ) {
			return true;
		}
	}

	/**
	 * Get the reason why an item is not visible
	 *
	 * @param int $post_id The post ID to check
	 *
	 * @return array Associative array with keys 'series' and 'service_type', each boolean
	 */
	public function get_visibility_inheritance( $post_id ) {
		$result = [
			'series'       => false,
			'service_type' => false,
		];

		$post_type = get_post_type( $post_id );

		if ( $post_type !== 'cpl_item' ) {
			return $result;
		}

		try {
			$item = Item::get_instance_from_origin( $post_id );

			// Check series visibility
			$item_types = $item->get_types();
			foreach ( $item_types as $item_type_id ) {
				$item_type = ItemType::get_instance( $item_type_id );
				if ( get_post_meta( $item_type->origin_id, 'exclude_from_main_list', true ) ) {
					$result['series'] = true;
					break;
				}
			}

			// Check service type visibility
			$service_types = $item->get_service_types();
			foreach ( $service_types as $service_type_id ) {
				$service_type = ServiceType::get_instance( $service_type_id );
				if ( get_post_meta( $service_type->origin_id, 'exclude_from_main_list', true ) ) {
					$result['service_type'] = true;
					break;
				}
			}

			return $result;
		} catch ( \Exception $e ) {
			return $result;
		}
	}

	/**
	 * Set visibility for a post
	 *
	 * @param int  $post_id    The post ID
	 * @param bool $is_visible Whether the post should be visible
	 */
	public function set_visibility( $post_id, $is_visible ) {
		// Remove existing terms
		wp_remove_object_terms( $post_id, [ $this->public_term, $this->hidden_term ], $this->taxonomy );

		// Add appropriate term
		$term = $is_visible ? $this->public_term : $this->hidden_term;
		wp_set_object_terms( $post_id, $term, $this->taxonomy );

		// Allow developers to hook into visibility changes
		do_action( 'cpl_visibility_changed', $post_id, $is_visible );
	}

	/**
	 * Check if the visibility should be updated when saving a post
	 *
	 * @param $post_id
	 *
	 * @since  1.6.0
	 *
	 * @author Tanner Moushey, 4/22/25
	 */
	public function maybe_update_visibility( $post_id ) {
		// Skip if not on admin screen or doing autosave
		if ( ! is_admin() || defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		switch( get_post_type( $post_id ) ) {
			case 'cpl_item':
				$this->update_item_visibility( $post_id );
				break;
			case 'cpl_item_type':
				$this->update_item_type_visibility( $post_id );
				break;
			case 'cpl_service_type':
				$this->update_service_type_visibility( $post_id );
				break;
			default:
				break;
		}
	}

	/**
	 * Update sermon visibility when saved
	 *
	 * @param int $post_id The post ID
	 */
	public function update_item_visibility( $post_id ) {
		if ( ! $this->should_be_visible( $post_id ) ) {
			// A parent series or service type forces this hidden.
			$this->set_visibility( $post_id, false );
			return;
		}

		// When the metabox was submitted, normalize storage to a single
		// canonical key. Without this, unchecking the box on a sermon that
		// still carries pre-1.6.2 legacy meta would leave it hidden because
		// CMB2 deletes the new key on uncheck and the helper would fall
		// through to the legacy key.
		if ( isset( $_POST['cpl_visibility_inherited'] ) ) {
			$exclude = ! empty( $_POST['exclude_from_main_list'] );
			update_post_meta( $post_id, 'exclude_from_main_list', $exclude ? '1' : '0' );
			delete_post_meta( $post_id, 'show_in_main_list' );
		}

		$this->set_visibility( $post_id, $this->get_user_visibility_preference( $post_id ) );
	}

	/**
	 * Update series visibility when saved and propagate to sermons
	 *
	 * @param int $post_id The post ID
	 */
	public function update_item_type_visibility( $post_id ) {
		$exclude = $this->is_excluded_from_main_list( $post_id );

		$this->set_visibility( $post_id, ! $exclude );

		if ( apply_filters( 'cpl_visibility_propagate_from_series', true, $post_id, $exclude ) ) {
			$this->propagate_item_type_visibility( $post_id, ! $exclude );
		}
	}

	/**
	 * Update service type visibility when saved and propagate to sermons
	 *
	 * @param int $post_id The post ID
	 */
	public function update_service_type_visibility( $post_id ) {
		$exclude = $this->is_excluded_from_main_list( $post_id );

		$this->set_visibility( $post_id, ! $exclude );

		if ( apply_filters( 'cpl_visibility_propagate_from_service_type', true, $post_id, $exclude ) ) {
			$this->propagate_service_type_visibility( $post_id, ! $exclude );
		}
	}

	/**
	 * Propagate series visibility to all sermons in the series
	 *
	 * @param int  $item_type_id The series ID
	 * @param bool $is_visible   Visibility to set
	 */
	protected function propagate_item_type_visibility( $item_type_id, $is_visible ) {
		try {
			$item_type = ItemType::get_instance_from_origin( $item_type_id );
			$items     = $item_type->get_items();

			foreach ( $items as $item ) {
				$item_id = $item->origin_id;
				// Only update if parent visibility would force the item to be hidden
				if ( ! $is_visible ) {
					$this->set_visibility( $item_id, false );
				} else {
					// If parent is visible, check other criteria
					$still_visible = $this->should_be_visible( $item_id );

					if ( $still_visible ) {
						// If no other parents force this to be hidden, revert to user preference
						$this->set_visibility( $item_id, $this->get_user_visibility_preference( $item_id ) );
					}
				}
			}
		} catch ( \Exception $e ) {
			// Log error but continue
			error_log( $e->getMessage() );
		}
	}

	/**
	 * Propagate service type visibility to all sermons with this service type
	 *
	 * @param int  $service_type_id The service type ID
	 * @param bool $is_visible      Visibility to set
	 */
	protected function propagate_service_type_visibility( $service_type_id, $is_visible ) {
		try {
			$service_type = ServiceType::get_instance_from_origin( $service_type_id );
			$items        = $service_type->get_all_items();

			foreach ( $items as $item_id ) {
				// Only update if parent visibility would force the item to be hidden
				if ( ! $is_visible ) {
					$this->set_visibility( $item_id, false );
				} else {
					// If parent is visible, check other criteria
					$still_visible = $this->should_be_visible( $item_id );

					if ( $still_visible ) {
						// If no other parents force this to be hidden, revert to user preference
						$this->set_visibility( $item_id, $this->get_user_visibility_preference( $item_id ) );
					}
				}
			}
		} catch ( \Exception $e ) {
			// Log error but continue
			error_log( $e->getMessage() );
		}
	}

	/**
	 * Get the user's stored visibility preference for a sermon.
	 *
	 * Uses metadata_exists() to distinguish "user explicitly set this" from
	 * "never set." That distinction matters for two reasons:
	 *  - Imports and other programmatic saves never set the meta, and must
	 *    default to visible.
	 *  - Under 1.6.0/1.6.1 an unchecked legacy "Show in Main List" stored
	 *    an empty string (meaning hidden) — we need to honor that until
	 *    the user runs the meta migration.
	 *
	 * @param int $item_id The sermon post ID.
	 *
	 * @return bool True if the user prefers this sermon visible.
	 */
	public function get_user_visibility_preference( $item_id ) {
		if ( metadata_exists( 'post', $item_id, 'exclude_from_main_list' ) ) {
			return ! get_post_meta( $item_id, 'exclude_from_main_list', true );
		}

		if ( metadata_exists( 'post', $item_id, 'show_in_main_list' ) ) {
			return (bool) get_post_meta( $item_id, 'show_in_main_list', true );
		}

		return true;
	}

	/**
	 * Whether a series or service type should be excluded from the main list.
	 *
	 * Same metadata_exists pattern as get_user_visibility_preference: a missing
	 * meta key means "no preference, default visible," so programmatic saves
	 * cannot accidentally un-hide an existing entity.
	 *
	 * @param int $post_id The series or service type post ID.
	 *
	 * @return bool True if the entity is excluded from the main list.
	 */
	protected function is_excluded_from_main_list( $post_id ) {
		if ( ! metadata_exists( 'post', $post_id, 'exclude_from_main_list' ) ) {
			return false;
		}

		return ! empty( get_post_meta( $post_id, 'exclude_from_main_list', true ) );
	}

	/**
	 * Render the one-time admin notice introducing the 1.6.2 visibility flip
	 * and pointing users to the recovery tools on the Tools page.
	 *
	 * @since 1.6.2
	 */
	public function maybe_render_flip_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( get_option( 'cpl_visibility_flip_notice_dismissed' ) ) {
			return;
		}

		// Only surface the notice where sermon admins are likely to act on
		// it: the sermons CPT screens (list/edit), the related taxonomies,
		// and the dashboard. Avoids running the legacy-meta query on every
		// unrelated admin page.
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}
		$item_post_type = cp_library()->setup->post_types->item->post_type;
		$relevant_screens = [ 'dashboard', 'plugins' ];
		$is_relevant = in_array( $screen->id, $relevant_screens, true )
			|| $screen->post_type === $item_post_type
			|| $screen->post_type === cp_library()->setup->post_types->item_type->post_type
			|| ( isset( $_GET['page'] ) && $_GET['page'] === 'cp-library-tools' );
		if ( ! $is_relevant ) {
			return;
		}

		if ( ! $this->has_legacy_visibility_meta() ) {
			return;
		}

		$tools_url = admin_url( 'edit.php?post_type=' . cp_library()->setup->post_types->item->post_type . '&page=cp-library-tools&tab=import_export' );
		$dismiss_url = wp_nonce_url(
			add_query_arg( 'cpl_dismiss', 'visibility_flip', admin_url() ),
			'cpl_dismiss_visibility_flip'
		);
		?>
		<div class="notice notice-info is-dismissible">
			<p>
				<strong><?php esc_html_e( 'CP Sermon Library:', 'cp-library' ); ?></strong>
				<?php esc_html_e( 'The per-sermon visibility checkbox was renamed to "Exclude from Main List" in 1.6.2 so that imported sermons default to visible.', 'cp-library' ); ?>
				<a href="<?php echo esc_url( $tools_url ); ?>"><?php esc_html_e( 'Open the Tools page to migrate legacy settings or reset hidden sermons.', 'cp-library' ); ?></a>
			</p>
			<p>
				<a href="<?php echo esc_url( $dismiss_url ); ?>"><?php esc_html_e( 'Dismiss this notice', 'cp-library' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Whether any sermon still carries the legacy `show_in_main_list` meta.
	 * Result is cached for a day; the migration tool clears the cache when
	 * it completes via {@see clear_legacy_meta_cache}.
	 *
	 * @since 1.6.2
	 *
	 * @return bool
	 */
	protected function has_legacy_visibility_meta() {
		$cached = get_transient( 'cpl_visibility_legacy_present' );
		if ( $cached !== false ) {
			return (bool) $cached;
		}

		global $wpdb;
		$exists = (bool) $wpdb->get_var(
			"SELECT 1 FROM {$wpdb->postmeta} WHERE meta_key = 'show_in_main_list' LIMIT 1"
		);

		set_transient( 'cpl_visibility_legacy_present', $exists ? 1 : 0, DAY_IN_SECONDS );

		return $exists;
	}

	/**
	 * Clear the legacy-meta cache. Called by the meta migration tool on
	 * completion so the admin notice updates immediately.
	 *
	 * @since 1.6.2
	 */
	public static function clear_legacy_meta_cache() {
		delete_transient( 'cpl_visibility_legacy_present' );
	}

	/**
	 * Dismiss the visibility flip notice when the user clicks the dismiss link.
	 *
	 * @since 1.6.2
	 */
	public function maybe_dismiss_flip_notice() {
		if ( empty( $_GET['cpl_dismiss'] ) || 'visibility_flip' !== $_GET['cpl_dismiss'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'cpl_dismiss_visibility_flip' );

		update_option( 'cpl_visibility_flip_notice_dismissed', 1 );

		wp_safe_redirect( remove_query_arg( [ 'cpl_dismiss', '_wpnonce' ] ) );
		exit;
	}
}
