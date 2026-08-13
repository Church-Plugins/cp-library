<?php
/**
 * Finding sermons that are missing something.
 *
 * @package CP_Library
 * @since 1.7.0
 */

namespace CP_Library\Admin;

use CP_Library\Models\Speaker;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Locates sermons missing media, a speaker, a series, or a transcript.
 *
 * Serves two callers from one definition: a filter on the sermon list table,
 * and the counts behind the dashboard's "Needs attention" card. Both are built
 * from the same SQL fragment per type, so a count can never disagree with the
 * list it links to.
 *
 * Reads the `cpl_item_meta` custom table rather than postmeta. Media URLs are
 * written to both (see ChurchPlugins\Setup\PostTypes\PostType::update_meta_value)
 * but the two drift — importers calling update_post_meta() bypass the override
 * entirely. On one library of 8,601 sermons the custom table held 8,739
 * non-empty audio_url rows against postmeta's 8,603. The sermon list column
 * reads the custom table, so this must too, or the dashboard would contradict
 * the screen it sends people to.
 *
 * @since 1.7.0
 */
class MissingContent {

	/**
	 * The query var carrying the active filter.
	 *
	 * @var string
	 */
	const QUERY_VAR = 'cpl_missing';

	/**
	 * Where the cached counts live.
	 *
	 * @var string
	 */
	const TRANSIENT = 'cpl_missing_counts';

	/**
	 * Token replaced with the post ID column in each type's SQL.
	 *
	 * @var string
	 */
	const POST_ID_TOKEN = '{POST_ID}';

	/**
	 * The single instance of the class.
	 *
	 * @var MissingContent
	 */
	protected static $_instance; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Only make one instance of MissingContent.
	 *
	 * @return MissingContent
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof MissingContent ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor.
	 */
	protected function __construct() {
		add_action( 'restrict_manage_posts', array( $this, 'render_filter' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_query' ) );

		add_action( 'save_post', array( $this, 'flush_counts' ), 10, 2 );
		add_action( 'deleted_post', array( $this, 'flush_counts' ) );
	}

	/**
	 * The sermon post type.
	 *
	 * @return string
	 * @since 1.7.0
	 */
	protected function get_post_type() {
		return cp_library()->setup->post_types->item->post_type;
	}

	/**
	 * The filter types, each with a label and the SQL that identifies it.
	 *
	 * Every fragment is a correlated NOT EXISTS keyed on the post ID, so it can
	 * be dropped into a COUNT over wp_posts or appended to the list table's
	 * WHERE clause unchanged.
	 *
	 * @return array
	 * @since 1.7.0
	 */
	protected function get_types() {
		global $wpdb;

		$item  = $wpdb->prefix . 'cpl_item';
		$imeta = $wpdb->prefix . 'cpl_item_meta';
		$smeta = $wpdb->prefix . 'cp_source_meta';
		$id    = self::POST_ID_TOKEN;

		$speaker_type = absint( $this->get_speaker_type_id() );

		$media_where   = "cpl_m.`key` IN ( 'audio_url', 'video_url' ) AND cpl_m.value <> ''";
		$speaker_where = "cpl_s.`key` = 'source_item'
			AND cpl_s.source_type_id = {$speaker_type}
			AND cpl_s.source_id IS NOT NULL AND cpl_s.source_id <> 0";

		/*
		 * Media and speakers can live on a variation rather than on the sermon
		 * itself: when a sermon has variations the parent never receives those
		 * fields, they are written to the child (Models\Item::save_variations()).
		 * Matching only the parent reports a sermon that plays perfectly well as
		 * having no media.
		 *
		 * Deliberately two NOT EXISTS rather than one with
		 * `( ID = x OR post_parent = x )`. That OR spans two columns, so nothing
		 * can serve it and the planner scans wp_posts once per candidate row —
		 * measured at 79 seconds across 8,829 sermons against 250ms for this
		 * form, which lets each subquery use its own index (cpl_item.origin_id
		 * and wp_posts.post_parent).
		 */
		$types = array(
			'media'      => array(
				'label' => __( 'Missing audio and video', 'cp-library' ),
				'sql'   => "( NOT EXISTS (
					SELECT 1 FROM {$item} cpl_i
					INNER JOIN {$imeta} cpl_m ON cpl_m.item_id = cpl_i.id
					WHERE cpl_i.origin_id = {$id} AND {$media_where}
				) AND NOT EXISTS (
					SELECT 1 FROM {$wpdb->posts} cpl_p
					INNER JOIN {$item} cpl_i ON cpl_i.origin_id = cpl_p.ID
					INNER JOIN {$imeta} cpl_m ON cpl_m.item_id = cpl_i.id
					WHERE cpl_p.post_parent = {$id}
					AND cpl_p.post_status NOT IN ( 'trash', 'auto-draft' )
					AND {$media_where}
				) )",
			),
			'speaker'    => array(
				/* translators: %s: the singular speaker label, e.g. "Speaker". */
				'label' => sprintf( __( 'No %s', 'cp-library' ), cp_library()->setup->post_types->speaker->single_label ),
				'sql'   => "( NOT EXISTS (
					SELECT 1 FROM {$item} cpl_i
					INNER JOIN {$smeta} cpl_s ON cpl_s.item_id = cpl_i.id
					WHERE cpl_i.origin_id = {$id} AND {$speaker_where}
				) AND NOT EXISTS (
					SELECT 1 FROM {$wpdb->posts} cpl_p
					INNER JOIN {$item} cpl_i ON cpl_i.origin_id = cpl_p.ID
					INNER JOIN {$smeta} cpl_s ON cpl_s.item_id = cpl_i.id
					WHERE cpl_p.post_parent = {$id}
					AND cpl_p.post_status NOT IN ( 'trash', 'auto-draft' )
					AND {$speaker_where}
				) )",
			),
			'series'     => array(
				/* translators: %s: the singular series label, e.g. "Series". */
				'label' => sprintf( __( 'Not in a %s', 'cp-library' ), cp_library()->setup->post_types->item_type->single_label ),
				'sql'   => "NOT EXISTS (
					SELECT 1 FROM {$item} cpl_i
					INNER JOIN {$imeta} cpl_m ON cpl_m.item_id = cpl_i.id
					WHERE cpl_i.origin_id = {$id}
					AND cpl_m.`key` = 'item_type'
					AND cpl_m.item_type_id IS NOT NULL AND cpl_m.item_type_id <> 0
				)",
			),
			// A sermon can have working audio, play fine on the site, and still be
			// absent from the podcast: the feed only includes posts carrying an
			// `enclosure` (Setup\Podcast::feed_query()), which is written solely
			// by core's do_enclose() and silently writes nothing when its HTTP
			// request to the audio URL fails. On one library 7,362 of 8,349
			// published sermons with audio were missing from the feed, with
			// nothing anywhere in the admin saying so.
			'podcast'    => array(
				'label' => __( 'Has audio but is not in the podcast', 'cp-library' ),
				'sql'   => "( EXISTS (
					SELECT 1 FROM {$item} cpl_i
					INNER JOIN {$imeta} cpl_m ON cpl_m.item_id = cpl_i.id
					WHERE cpl_i.origin_id = {$id}
					AND cpl_m.`key` = 'audio_url' AND cpl_m.value <> ''
				) AND NOT EXISTS (
					SELECT 1 FROM {$wpdb->postmeta} cpl_pm
					WHERE cpl_pm.post_id = {$id}
					AND cpl_pm.meta_key = 'enclosure'
					AND cpl_pm.meta_value <> ''
				) )",
			),
			'transcript' => array(
				'label' => __( 'No transcript', 'cp-library' ),
				'sql'   => "NOT EXISTS (
					SELECT 1 FROM {$wpdb->postmeta} cpl_pm
					WHERE cpl_pm.post_id = {$id}
					AND cpl_pm.meta_key = 'transcript'
					AND cpl_pm.meta_value <> ''
				)",
			),
		);

		if ( ! cp_library()->setup->post_types->speaker_enabled() ) {
			unset( $types['speaker'] );
		}

		if ( ! cp_library()->setup->post_types->item_type_enabled() ) {
			unset( $types['series'] );
		}

		if ( ! cp_library()->setup->podcast->is_enabled() ) {
			unset( $types['podcast'] );
		}

		/**
		 * Filters the "missing content" types offered on the sermon list table
		 * and counted for the dashboard.
		 *
		 * Each entry needs a `label` and a `sql` fragment. The fragment must be a
		 * self-contained boolean expression using {POST_ID} where the sermon's
		 * post ID belongs.
		 *
		 * The fragment is concatenated into the sermon list table's WHERE clause
		 * and into the dashboard's COUNT queries, so it must already be safe:
		 * pass anything derived from a request through $wpdb->prepare() before
		 * returning it. Entries that are not an array with a non-empty string
		 * `label` and `sql` are dropped rather than reaching the database.
		 *
		 * @param array $types The registered types.
		 * @since 1.7.0
		 */
		$types = apply_filters( 'cpl_missing_content_types', $types );

		// A malformed entry here would fatal the sermon list table — the most
		// used screen in the plugin — so anything unusable is discarded.
		return array_filter(
			(array) $types,
			function ( $type ) {
				return is_array( $type )
					&& ! empty( $type['label'] ) && is_string( $type['label'] )
					&& ! empty( $type['sql'] ) && is_string( $type['sql'] );
			}
		);
	}

	/**
	 * A type's condition, bound to a post ID column.
	 *
	 * Lets other screens ask "is this particular sermon missing X" using the
	 * same definition as the counts and the list table filter, so the three can
	 * never drift apart.
	 *
	 * @param string $key    A type key.
	 * @param string $column The post ID column to key on.
	 * @return string Empty when the type is not registered.
	 * @since 1.7.0
	 */
	public function get_condition_sql( $key, $column ) {
		$types = $this->get_types();

		return isset( $types[ $key ] ) ? $this->prepare_sql( $types[ $key ]['sql'], $column ) : '';
	}

	/**
	 * The registered type keys and their labels.
	 *
	 * @return array
	 * @since 1.7.0
	 */
	public function get_labels() {
		return wp_list_pluck( $this->get_types(), 'label' );
	}

	/**
	 * The Speaker source type id.
	 *
	 * Speaker::get_type_id() inserts the type when it is absent, so this is
	 * routed through a static to keep a page view that renders both the filter
	 * and the counts from probing for it more than once.
	 *
	 * @return int
	 * @since 1.7.0
	 */
	protected function get_speaker_type_id() {
		static $type_id = null;

		if ( null === $type_id ) {
			$type_id = (int) Speaker::get_type_id();
		}

		return $type_id;
	}

	/**
	 * Whether the given screen is the sermon list table.
	 *
	 * @return bool
	 * @since 1.7.0
	 */
	protected function is_sermon_list() {
		global $pagenow;

		if ( 'edit.php' !== $pagenow ) {
			return false;
		}

		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return $this->get_post_type() === $post_type;
	}

	/**
	 * The active filter, if it is one we recognize.
	 *
	 * @return string Empty when no valid filter is active.
	 * @since 1.7.0
	 */
	protected function get_active_type() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list table filter.
		$type = isset( $_GET[ self::QUERY_VAR ] ) ? sanitize_key( wp_unslash( $_GET[ self::QUERY_VAR ] ) ) : '';

		return isset( $this->get_types()[ $type ] ) ? $type : '';
	}

	/**
	 * Render the dropdown above the sermon list table.
	 *
	 * @return void
	 * @since 1.7.0
	 */
	public function render_filter() {
		if ( ! $this->is_sermon_list() ) {
			return;
		}

		$active = $this->get_active_type();
		?>
		<label class="screen-reader-text" for="<?php echo esc_attr( self::QUERY_VAR ); ?>">
			<?php esc_html_e( 'Filter by missing content', 'cp-library' ); ?>
		</label>
		<select name="<?php echo esc_attr( self::QUERY_VAR ); ?>" id="<?php echo esc_attr( self::QUERY_VAR ); ?>">
			<option value="">
				<?php
				/* translators: %s: the plural sermon label, e.g. "Sermons". */
				echo esc_html( sprintf( __( 'All %s', 'cp-library' ), cp_library()->setup->post_types->item->plural_label ) );
				?>
			</option>
			<?php foreach ( $this->get_types() as $key => $type ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $active, $key ); ?>>
					<?php echo esc_html( $type['label'] ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Apply the active filter to the sermon list query.
	 *
	 * @param \WP_Query $query The query.
	 * @return void
	 * @since 1.7.0
	 */
	public function filter_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || ! $this->is_sermon_list() ) {
			return;
		}

		$type = $this->get_active_type();

		if ( ! $type ) {
			return;
		}

		// Carried on the query rather than captured here so the closure survives
		// any other code that rebuilds the main query.
		$query->set( self::QUERY_VAR, $type );

		add_filter( 'posts_where', array( $this, 'filter_where' ), 10, 2 );
	}

	/**
	 * Append the active type's condition to the list table's WHERE clause.
	 *
	 * @param string    $where The WHERE clause.
	 * @param \WP_Query $query The query.
	 * @return string
	 * @since 1.7.0
	 */
	public function filter_where( $where, $query ) {
		global $wpdb;

		$type  = $query->get( self::QUERY_VAR );
		$types = $this->get_types();

		if ( ! $type || ! isset( $types[ $type ] ) ) {
			return $where;
		}

		return $where . ' AND ' . $this->prepare_sql( $types[ $type ]['sql'], "{$wpdb->posts}.ID" );
	}

	/**
	 * Bind a type's SQL fragment to a post ID column.
	 *
	 * @param string $sql    The fragment, containing {POST_ID}.
	 * @param string $column The column to key on.
	 * @return string
	 * @since 1.7.0
	 */
	protected function prepare_sql( $sql, $column ) {
		return str_replace( self::POST_ID_TOKEN, $column, $sql );
	}

	/**
	 * The URL of the sermon list, filtered to a type.
	 *
	 * @param string $type A type key.
	 * @return string
	 * @since 1.7.0
	 */
	public function get_filter_url( $type ) {
		return add_query_arg(
			array(
				'post_type'     => $this->get_post_type(),
				self::QUERY_VAR => $type,
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * How many sermons are missing each thing, plus the total in scope.
	 *
	 * Cached: these are four correlated subqueries over the whole library,
	 * around 55ms each on 8,600 sermons, and the dashboard would otherwise pay
	 * that on every view.
	 *
	 * @param bool $force Recompute rather than reading the cache.
	 * @return array {
	 *     @type int   $total  Sermons in scope.
	 *     @type array $counts Type key => count.
	 * }
	 * @since 1.7.0
	 */
	public function get_counts( $force = false ) {
		$cached = $force ? false : get_transient( self::TRANSIENT );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		// Without a lock, every admin who lands on the dashboard after the cache
		// expires pays the full recount at once. The first one through does the
		// work; everyone else gets an empty result and the card sits this view
		// out rather than piling on.
		if ( ! $force && ! wp_cache_add( self::TRANSIENT . '_lock', 1, '', MINUTE_IN_SECONDS ) ) {
			return array(
				'total'  => 0,
				'counts' => array(),
			);
		}

		global $wpdb;

		// Matches the list table, which hides variations unless asked otherwise
		// (see Setup\PostTypes\Item::hide_child_items).
		$scope = $wpdb->prepare(
			"FROM {$wpdb->posts} p
			 WHERE p.post_type = %s
			 AND p.post_status NOT IN ( 'trash', 'auto-draft' )
			 AND p.post_parent = 0",
			$this->get_post_type()
		);

		$result = array(
			'total'  => (int) $wpdb->get_var( "SELECT COUNT(*) {$scope}" ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			'counts' => array(),
		);

		foreach ( $this->get_types() as $key => $type ) {
			$sql = "SELECT COUNT(*) {$scope} AND " . $this->prepare_sql( $type['sql'], 'p.ID' );

			$result['counts'][ $key ] = (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		set_transient( self::TRANSIENT, $result, 15 * MINUTE_IN_SECONDS );

		return $result;
	}

	/**
	 * Drop the cached counts when a sermon changes.
	 *
	 * @param int      $post_id The post id.
	 * @param \WP_Post $post    The post, when the caller supplies one.
	 * @return void
	 * @since 1.7.0
	 */
	public function flush_counts( $post_id, $post = null ) {
		$post_type = $post instanceof \WP_Post ? $post->post_type : get_post_type( $post_id );

		if ( $this->get_post_type() !== $post_type ) {
			return;
		}

		// Deliberately not a recount here. A bulk import fires this thousands of
		// times, and recomputing on the next page view would put four correlated
		// scans on the render path. Queue one refresh instead.
		Dashboard\Rollup::get_instance()->schedule_debounced();
	}

	/**
	 * The types worth showing as problems, with their counts.
	 *
	 * A type only earns a place on the dashboard when it reads as an exception
	 * rather than a convention. Measured on a real library: 15 sermons with no
	 * media (0.2%) is a work queue; 3,871 with no series (45%) and 8,554 with no
	 * transcript (99%) are simply how that church runs, and listing them under
	 * "needs attention" is how a dashboard trains people to ignore it. The list
	 * table filter still offers every type — "show me sermons with no series" is
	 * a fair question even when the answer is half the library.
	 *
	 * @return array Type key => array( label, count, url ).
	 * @since 1.7.0
	 */
	public function get_problems() {
		$data  = $this->get_counts();
		$types = $this->get_types();

		/**
		 * Filters the share of the library above which a missing-content type
		 * stops being reported as a problem.
		 *
		 * @param float $threshold A fraction between 0 and 1. Default 0.25.
		 * @since 1.7.0
		 */
		$threshold = (float) apply_filters( 'cpl_missing_content_threshold', 0.25 );

		/**
		 * Filters the types that are reported regardless of how common they are.
		 *
		 * The threshold exists to stop a house style being reported as a defect —
		 * 99% of a library having no transcript is a choice, not a fault. But
		 * some things are broken at any proportion: a sermon with no media shows
		 * an empty player, and one missing from the podcast never reaches a
		 * subscriber. Suppressing those for being too common hides exactly the
		 * problems worth acting on.
		 *
		 * @param array $always The type keys exempt from the threshold.
		 * @since 1.7.0
		 */
		$always = (array) apply_filters( 'cpl_missing_content_always_report', array( 'media', 'podcast' ) );

		$problems = array();

		foreach ( $data['counts'] as $key => $count ) {
			if ( ! $count || ! isset( $types[ $key ] ) ) {
				continue;
			}

			if ( ! in_array( $key, $always, true ) && $data['total'] && ( $count / $data['total'] ) > $threshold ) {
				continue;
			}

			$problems[ $key ] = array(
				'label' => $types[ $key ]['label'],
				'count' => $count,
				'url'   => $this->get_filter_url( $key ),
			);
		}

		return $problems;
	}
}
