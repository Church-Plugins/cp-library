<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace CP_Library\API;

use ChurchPlugins\Helpers;
use CP_Library\Admin\Settings;

/**
 * CP Library AJAX methods.
 */
class AJAX {

	/**
	 * The class instance
	 *
	 * @var AJAX
	 */
	protected static $instance;

	/**
	 * The class constructor
	 */
	protected function __construct() {
		$this->includes();
		$this->actions();
	}

	/**
	 * Get the class instance
	 *
	 * @return AJAX
	 */
	public static function get_instance() {
		if ( ! self::$instance instanceof AJAX ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register actions
	 */
	protected function actions() {
		add_action( 'wp_ajax_nopriv_cpl_dropdown_facet', array( $this, 'render_dropdown_filter' ) );
		add_action( 'wp_ajax_cpl_dropdown_facet', array( $this, 'render_dropdown_filter' ) );
	}

	/**
	 * Include files
	 */
	protected function includes() {}



	/**
	 * Render a dropdown filter
	 *
	 * @return void
	 */
	public function render_dropdown_filter() {
		// phpcs:disable WordPress.Security.NonceVerification
		$facet_type = Helpers::get_param( $_POST, 'facet_type', false );
		$selected   = Helpers::get_param( $_POST, 'selected', array() );
		$label      = Helpers::get_param( $_POST, 'label', '' );
		// phpcs:enable WordPress.Security.NonceVerification

		if ( ! $facet_type || ! is_array( $selected ) ) {
			wp_die();
		}

		$items = array();

		switch ( $facet_type ) {
			case 'speaker':
			case 'service_type':
				$items = $this->get_sources( $facet_type );
				break;
			case 'cpl_scripture':
			case 'cpl_topic':
			case 'cpl_season':
				$items = $this->get_terms( $facet_type );
				break;
		}

		if ( empty( $items ) ) {
			wp_die();
		}

		?>
		<div class="cpl-filter--<?php echo esc_attr( $facet_type ); ?> cpl-filter--has-dropdown">
			<a href="#" class="cpl-filter--dropdown-button cpl-button is-light"><?php echo esc_html( $label ); ?></a>
			<div class="cpl-filter--dropdown">
				<?php foreach ( $items as $item ) : ?>
					<label>
						<input type="checkbox" <?php checked( in_array( $item->value, $selected, true ) ); ?> name="<?php echo esc_attr( $facet_type ); ?>[]" value="<?php echo esc_attr( $item->value ); ?>"/> <?php echo esc_html( $item->title ); ?> (<?php echo esc_html( $item->count ); ?>)
					</label>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		wp_die();
	}

	/**
	 * Get sources (speakers or service types)
	 *
	 * @param string $type The type of source to get.
	 * @return array
	 */
	public function get_sources( $type ) {
		$order_by = Settings::get_advanced( 'sort_speaker', 'count' );

		if ( 'count' === $order_by ) {
			$order_by = 'count DESC';
		} else {
			$order_by = 'title ASC';
		}

		$sql = 'SELECT
			speaker.id AS value,
			speaker.title AS title,
			COUNT(sermon.id) AS count
		FROM 
			%1$s AS speaker
		LEFT JOIN 
			%2$s AS meta ON meta.source_id = speaker.id
		INNER JOIN
			%3$s AS type ON meta.source_type_id = type.id AND type.title = "%4$s"
		LEFT JOIN 
			%5$s AS sermon ON meta.item_id = sermon.id
		GROUP BY
			speaker.id
		HAVING
			count >= %6$d
		ORDER BY
			%7$s';

		global $wpdb;

		// TODO: Table names should not be hardcoded.
		$sql = sprintf(
			$sql,
			'wp_cp_source',
			'wp_cp_source_meta',
			'wp_cp_source_type',
			$type,
			'wp_cpl_item',
			(int) Settings::get_advanced( 'filter_count_threshold', 3 ),
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
	 * @param string $taxonomy The taxonomy to get terms for.
	 * @return array
	 */
	public function get_terms( $taxonomy ) {
		$order_by = Settings::get_advanced( "sort_{$taxonomy}", 'count' );

		if ( 'count' === $order_by ) {
			$order_by = 'count DESC';
		} else {
			$order_by = 'title ASC';
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
			{$wpdb->posts} AS p ON tr.object_id = p.ID
		WHERE
			tt.taxonomy = %s
		AND
			p.post_type = %s
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
			$taxonomy,
			cp_library()->setup->post_types->item->post_type,
			(int) Settings::get_advanced( 'filter_count_threshold', 3 )
		);

		$output = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return $output ? $output : array();
	}
}
