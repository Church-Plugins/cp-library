<?php
/**
 * CP Library facet management
 *
 * @since 1.4.3
 * @package CP_Library
 */

namespace CP_Library\Facets;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ChurchPlugins\Helpers;
use CP_Library\Admin\Settings;
use CP_Library\Models\Item;
use CP_Library\Models\ItemType;

/**
 * Facet initialization class
 *
 * @since 1.4.3
 */
class Init {
	/**
	 * Singleton instance
	 *
	 * @var Init
	 */
	protected static $instance;

	/**
	 * Get the class instance
	 *
	 * @return Init
	 */
	public static function get_instance() {
		if ( ! self::$instance instanceof Init ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * The class constructor
	 */
	protected function __construct() {
		add_action( 'wp_ajax_nopriv_cpl_dropdown_facet', array( $this, 'render_dropdown_filter' ) );
		add_action( 'wp_ajax_cpl_dropdown_facet', array( $this, 'render_dropdown_filter' ) );
	}

	/**
	 * Checks if a query is facetable.
	 *
	 * @param \WP_Query $query The query to check.
	 * @return bool
	 */
	public function is_facetable( $query ) {

		/**
		 * Allow overwriting whether the query is facetable.
		 *
		 * @hook cpl_query_is_facetable
		 * @param bool      $is_facetable Whether the query is facetable.
		 * @param \WP_Query $query        The query to check.
		 * @return bool Whether the query should be facetable.
		 */
		if ( apply_filters( 'cpl_query_is_facetable', false, $query ) ) {
			return true;
		}

		if ( ! $query->is_main_query() ) {
			// allow setting a flag to specify the query as facetable.
			return (bool) $query->get( 'cpl_is_facetable', false );
		}

		return true;
	}

	/**
	 * Render a dropdown filter
	 *
	 * @todo Each facet type should be moved to its own class.
	 * @since 1.4.3
	 * @return void
	 */
	public function render_dropdown_filter() {
		// phpcs:disable WordPress.Security.NonceVerification
		$facet_type = Helpers::get_param( $_POST, 'facet_type', false );
		$selected   = Helpers::get_param( $_POST, 'selected', array() );
		$query_vars = Helpers::get_param( $_POST, 'query_vars', array() );
		// phpcs:enable WordPress.Security.NonceVerification

		if ( ! $facet_type || ! is_array( $selected ) ) {
			wp_die();
		}

		$items = array();

		$query_vars['no_found_rows']          = true;
		$query_vars['posts_per_page']         = 9999;
		$query_vars['fields']                 = 'ids';
		$query_vars['update_post_meta_cache'] = false;
		$query_vars['update_post_term_cache'] = false;

		$terms = $query_vars[ $facet_type ] ?? array();

		if ( ( $query_vars['taxonomy'] ?? false ) === $facet_type ) {
			unset( $query_vars['taxonomy'] );
		}
		if ( in_array( ( $query_vars['term'] ?? false ), $terms, true ) ) {
			unset( $query_vars['term'] );
		}

		unset( $query_vars[ $facet_type ] );
		unset( $query_vars['paged'] );

		// different default for scripture
		$default_order_by = 'cpl_scripture' === $facet_type ? 'name' : 'sermon_count';
		$order_by         = Settings::get_advanced( 'sort_' . $facet_type, $default_order_by );

		$args = array(
			'post__in'   => [],
			'order_by'   => $order_by,
			'threshold'  => (int) Settings::get_advanced( 'filter_count_threshold', 3 ),
			'facet_type' => $facet_type,
			'post_type'  => $query_vars['post_type'],
		);

		$last_updated = false;
		$cache_group  = false;

		if ( Item::get_prop( 'post_type' ) === $args['post_type'] ) {
			$last_updated = Item::get_last_changed();
			$cache_group  = Item::get_prop( 'cache_group' );
		} elseif ( ItemType::get_prop( 'post_type' ) === $args['post_type'] ) {
			$last_updated = ItemType::get_last_changed();
			$cache_group  = ItemType::get_prop( 'cache_group' );
		}

		if ( $last_updated && $cache_group ) {
			$hash_key = md5( serialize( $args ) . $last_updated );
			$items    = wp_cache_get( $hash_key, $cache_group );
		}

		if ( false === $items ) {
			$query = new \WP_Query( $query_vars );

			$args['post__in'] = $query->posts;

			if ( 'sermon_count' === $args['order_by'] ) {
				$args['order_by'] = 'count';
			}

			wp_reset_postdata();

			switch ( $facet_type ) {
				case 'speaker':
				case 'service_type':
					$items = $this->get_sources( $args );
					break;
				case 'cpl_scripture':
				case 'cpl_topic':
				case 'cpl_season':
					$items = $this->get_terms( $args );
					break;
			}

			if ( empty( $items ) ) {
				wp_die();
			}

			if ( $hash_key ) {
				wp_cache_set( $hash_key, $items, $cache_group, WEEK_IN_SECONDS );
			}
		}

		?>
		<?php foreach ( $items as $item ) : ?>
			<label class="cp-has-checkmark">
				<input type="checkbox" <?php checked( in_array( $item->value, $selected, true ) ); ?> name="<?php echo esc_attr( $facet_type ); ?>[]" value="<?php echo esc_attr( $item->value ); ?>"/>
				<span class="cp-checkmark"></span>
				<span class="cp-filter-label"><?php echo esc_html( $item->title ); ?> <sup class="cp-filter-count">(<?php echo esc_html( $item->count ); ?>)</sup></span>
			</label>
		<?php endforeach; ?>
		<?php
		wp_die();
	}

	/**
	 * Get sources (speakers or service types)
	 *
	 * @param array $args The arguments for the query.
	 * @return array
	 * @throws \ChurchPlugins\Exception If the arguments are invalid.
	 */
	public function get_sources( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'order_by'   => 'count',
				'threshold'  => 3,
				'facet_type' => '',
				'post__in'   => array(),
				'post_type' => cp_library()->setup->post_types->item->post_type,
			)
		);

		if ( ! in_array( $args['facet_type'], array( 'speaker', 'service_type' ) ) ) {
			throw new \ChurchPlugins\Exception( 'Invalid facet type' );
		}

		$order_by = ( 'count' === $args['order_by'] ) ? 'count DESC' : 'title ASC';

		if ( ! empty( $args['post__in'] ) ) {
			$args['post__in'] = 'sermon.origin_id IN (' . implode( ',', array_map( 'absint', $args['post__in'] ) ) . ')';
		} else {
			$args['post__in'] = '1 = 1';
		}

		$sql = 'SELECT
			source.id AS value,
			source.title AS title,
			COUNT(sermon.id) AS count
		FROM
			%1$s AS source
		LEFT JOIN
			%2$s AS meta ON meta.source_id = source.id
		INNER JOIN
			%3$s AS type ON meta.source_type_id = type.id AND type.title = "%4$s"
		LEFT JOIN
			%5$s AS sermon ON meta.item_id = sermon.id AND ' . $args['post__in'] . '
		GROUP BY
			source.id
		HAVING
			count >= %7$d
		ORDER BY
			%8$s;';

		global $wpdb;

		$sql = $wpdb->prepare(
			$sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prefix . 'cp_source',
			$wpdb->prefix . 'cp_source_meta',
			$wpdb->prefix . 'cp_source_type',
			$args['facet_type'],
			$wpdb->prefix . 'cpl_item',
			$args['post__in'],
			$args['threshold'],
			$order_by
		);

		$speakers = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! $speakers ) {
			$speakers = array();
		}

		return $speakers;
	}


	/**
	 * Get terms
	 *
	 * @param array $args The arguments for getting terms.
	 * @return array
	 * @throws \ChurchPlugins\Exception If an invalid facet type is provided.
	 */
	public function get_terms( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'order_by'   => 'sermon_count',
				'threshold'  => 3,
				'facet_type' => '',
				'post__in'   => array(),
				'post_type'  => cp_library()->setup->post_types->item->post_type,
			)
		);

		if ( ! in_array( $args['facet_type'], cp_library()->setup->taxonomies->get_taxonomies(), true ) ) {
			throw new \ChurchPlugins\Exception( 'Invalid facet type' );
		}

		$order_by = ( 'count' === $args['order_by'] ) ? 'count DESC' : 'title ASC';

		if ( ! empty( $args['post__in'] ) ) {
			$args['post__in'] = 'p.ID IN (' . implode( ',', array_map( 'absint', $args['post__in'] ) ) . ')';
		} else {
			$args['post__in'] = '1 = 1';
		}

		global $wpdb;

		$query = "SELECT
			t.name AS title,
			t.term_id AS id,
			t.slug AS value,
			COUNT(p.ID) AS count
		FROM
			{$wpdb->terms} AS t
		LEFT JOIN
			{$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
		LEFT JOIN
			{$wpdb->term_relationships} AS tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
		LEFT JOIN
			{$wpdb->posts} AS p ON tr.object_id = p.ID AND {$args['post__in']}
		WHERE
			tt.taxonomy = '%s'
		AND
			p.post_type = '%s'
		AND
			p.post_status = 'publish'
		GROUP BY
			t.term_id
		HAVING
			count >= %d
		ORDER BY
			{$order_by}
		";

		$query = $wpdb->prepare(
			$query, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$args['facet_type'],
			$args['post_type'],
			$args['threshold']
		);

		$output = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( 'cpl_scripture' === $args['facet_type'] && 'name' === $args['order_by'] ) {
			usort( $output, 'CP_Library\API\Ajax::sort_scripture' );
		}

		return $output ? $output : array();
	}

	/**
	 * Sort terms by scripture order
	 *
	 * @since  1.3.0
	 *
	 * @param \stdClass $a The first term to compare.
	 * @param \stdClass $b The second term to compare.
	 *
	 * @return int|string
	 * @author Tanner Moushey, 10/20/23
	 */
	public static function sort_scripture( $a, $b ) {
		$book_order = array_values( cp_library()->setup->taxonomies->scripture->get_terms() );
		$index_a    = array_search( $a->title, $book_order );
		$index_b    = array_search( $b->title, $book_order );

		if ( false === $index_a || false === $index_b ) {
			return 0;
		}

		return $index_a - $index_b;
	}
}
