<?php
/**
 * The recent sermons card on the CP Sermons dashboard.
 *
 * @package CP_Library
 * @since 1.7.0
 */

namespace CP_Library\Admin\Dashboard;

use CP_Library\Admin\MissingContent;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * The last few sermons, and whether each one is actually finished.
 *
 * This is the card that makes the panel worth opening on a Monday. Housekeeping
 * reports that 242 sermons across the library have no media — a number that is
 * mostly archive debt, reads the same every week, and cannot tell you whether
 * *yesterday's* sermon is one of them. That is the only version of the question
 * anyone acts on.
 *
 * Drafts and scheduled posts are included deliberately. "Saved it on Sunday
 * night and never hit publish" is the most common real failure in the job and
 * nothing in this plugin surfaces it.
 *
 * Readiness is evaluated with the same SQL the list table filter and the
 * housekeeping counts use (MissingContent::get_condition_sql()), so a sermon
 * flagged here appears in that filter and vice versa. It is one query for the
 * whole card — deliberately not Item::get_instance_from_origin(), which creates
 * rows as a side effect.
 *
 * @since 1.7.0
 */
class ThisWeek {

	/**
	 * How many sermons to show.
	 *
	 * @var int
	 */
	const COUNT = 5;

	/**
	 * The single instance of the class.
	 *
	 * @var ThisWeek
	 */
	protected static $_instance; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Only make one instance of ThisWeek.
	 *
	 * @return ThisWeek
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof ThisWeek ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor.
	 */
	protected function __construct() {
		add_filter( 'cpl_dashboard_cards', array( $this, 'register_card' ) );
	}

	/**
	 * Register the card.
	 *
	 * @param array $cards The registered cards.
	 * @return array
	 * @since 1.7.0
	 */
	public function register_card( $cards ) {
		$cards['this-week'] = array(
			/* translators: %s: the plural sermon label, e.g. "Sermons". */
			'title'     => sprintf( __( 'Latest %s', 'cp-library' ), cp_library()->setup->post_types->item->plural_label ),
			'column'    => 'main',
			// Above the discovery cards: on a library that is already running,
			// this is the reason to open the page.
			'priority'  => 8,
			'condition' => array( $this, 'has_sermons' ),
			'render'    => array( $this, 'render' ),
		);

		return $cards;
	}

	/**
	 * Whether there is anything to show.
	 *
	 * wp_count_posts() is cached by core in the `counts` group.
	 *
	 * @return bool
	 * @since 1.7.0
	 */
	public function has_sermons() {
		$counts = (array) wp_count_posts( cp_library()->setup->post_types->item->post_type );

		foreach ( array( 'publish', 'future', 'draft', 'pending', 'private' ) as $status ) {
			if ( ! empty( $counts[ $status ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The checks shown against each sermon.
	 *
	 * Only the ones that mean a listener gets a worse experience. "Not in a
	 * series" and "no transcript" are conventions rather than faults, and
	 * flagging them per sermon would turn this into the scolding list the panel
	 * is built to avoid.
	 *
	 * @return array Type key => short label used when the check fails.
	 * @since 1.7.0
	 */
	protected function get_checks() {
		$checks = array(
			'media'   => __( 'no audio or video', 'cp-library' ),
			'speaker' => __( 'no speaker', 'cp-library' ),
			'podcast' => __( 'not in the podcast', 'cp-library' ),
		);

		$registered = MissingContent::get_instance()->get_labels();

		// Speakers and the podcast can both be switched off.
		return array_intersect_key( $checks, $registered );
	}

	/**
	 * The most recent sermons, with their outstanding checks.
	 *
	 * @return array
	 * @since 1.7.0
	 */
	protected function get_sermons() {
		global $wpdb;

		$missing = MissingContent::get_instance();
		$columns = array();

		foreach ( array_keys( $this->get_checks() ) as $key ) {
			$sql = $missing->get_condition_sql( $key, 'p.ID' );

			if ( $sql ) {
				$columns[] = "( {$sql} ) AS `check_{$key}`";
			}
		}

		// A sermon can be published and complete yet absent from the archive,
		// because visibility is inherited from its series or service type
		// (Setup\Visibility). Nothing on the edit screen or the list table says
		// so, and the only way to find out today is to load the archive and
		// scroll.
		$item     = $wpdb->prefix . 'cpl_item';
		$imeta    = $wpdb->prefix . 'cpl_item_meta';
		$itype    = $wpdb->prefix . 'cpl_item_type';
		$columns[] = "(
			EXISTS (
				SELECT 1 FROM {$wpdb->postmeta} cpl_x
				WHERE cpl_x.post_id = p.ID AND cpl_x.meta_key = 'exclude_from_main_list' AND cpl_x.meta_value <> ''
			) OR EXISTS (
				SELECT 1 FROM {$item} cpl_i
				INNER JOIN {$imeta} cpl_m ON cpl_m.item_id = cpl_i.id AND cpl_m.`key` = 'item_type'
				INNER JOIN {$itype} cpl_t ON cpl_t.id = cpl_m.item_type_id
				INNER JOIN {$wpdb->postmeta} cpl_tx ON cpl_tx.post_id = cpl_t.origin_id
					AND cpl_tx.meta_key = 'exclude_from_main_list' AND cpl_tx.meta_value <> ''
				WHERE cpl_i.origin_id = p.ID
			)
		) AS `check_hidden`";

		$ids = $this->get_recent_ids();

		if ( ! $ids ) {
			return array();
		}

		$select = $columns ? ', ' . implode( ', ', $columns ) : '';
		$in     = implode( ', ', array_map( 'absint', $ids ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $select is built from registered conditions, $in from absint().
		$sql = "SELECT p.ID, p.post_title, p.post_status, p.post_date {$select}
			 FROM {$wpdb->posts} p
			 WHERE p.ID IN ( {$in} )
			 ORDER BY p.post_date DESC";

		return (array) $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * The ids of the most recent sermons, published or not.
	 *
	 * Deliberately two queries rather than one `post_status IN ( ... )`.
	 * wp_posts' type_status_date index is (post_type, post_status, post_date),
	 * so a range on post_status stops post_date being usable for ordering and
	 * MySQL filesorts the whole post type — measured at 13.4ms across 8,800
	 * sermons, growing with the library, on the one query this card runs on
	 * every page view. Split into an equality on each status it is 0.14ms, and
	 * the published half needs no sort at all.
	 *
	 * @return array
	 * @since 1.7.0
	 */
	protected function get_recent_ids() {
		global $wpdb;

		$post_type = cp_library()->setup->post_types->item->post_type;
		$rows      = array();

		foreach ( array( array( 'publish' ), array( 'future', 'draft', 'pending', 'private' ) ) as $statuses ) {
			$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );

			$rows = array_merge(
				$rows,
				(array) $wpdb->get_results(
					$wpdb->prepare(
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are generated above.
						"SELECT ID, post_date FROM {$wpdb->posts}
						 WHERE post_type = %s AND post_status IN ( {$placeholders} ) AND post_parent = 0
						 ORDER BY post_date DESC LIMIT %d",
						array_merge( array( $post_type ), $statuses, array( self::COUNT ) )
					)
				)
			);
		}

		usort(
			$rows,
			function ( $a, $b ) {
				return strcmp( $b->post_date, $a->post_date );
			}
		);

		return wp_list_pluck( array_slice( $rows, 0, self::COUNT ), 'ID' );
	}

	/**
	 * The notes to show against one sermon.
	 *
	 * @param object $sermon A row from get_sermons().
	 * @return array
	 * @since 1.7.0
	 */
	protected function get_notes( $sermon ) {
		$notes = array();

		if ( 'draft' === $sermon->post_status || 'pending' === $sermon->post_status ) {
			$notes[] = __( 'not published yet', 'cp-library' );
		}

		if ( 'future' === $sermon->post_status ) {
			$notes[] = __( 'scheduled', 'cp-library' );
		}

		foreach ( $this->get_checks() as $key => $label ) {
			$field = 'check_' . $key;

			// The podcast only matters once a sermon is public.
			if ( 'podcast' === $key && 'publish' !== $sermon->post_status ) {
				continue;
			}

			if ( ! empty( $sermon->$field ) ) {
				$notes[] = $label;
			}
		}

		if ( ! empty( $sermon->check_hidden ) && 'publish' === $sermon->post_status ) {
			$notes[] = __( 'hidden from the archive', 'cp-library' );
		}

		return $notes;
	}

	/**
	 * Render the card body.
	 *
	 * @return void
	 * @since 1.7.0
	 */
	public function render() {
		$sermons = $this->get_sermons();

		if ( ! $sermons ) {
			return;
		}
		?>
		<ul class="cpl-dashboard__recent">
			<?php foreach ( $sermons as $sermon ) : ?>
				<?php $notes = $this->get_notes( $sermon ); ?>
				<li class="<?php echo $notes ? 'has-notes' : ''; ?>">
					<a class="cpl-dashboard__recent-title" href="<?php echo esc_url( get_edit_post_link( $sermon->ID ) ); ?>">
						<?php echo esc_html( get_the_title( $sermon->ID ) ); ?>
					</a>

					<span class="cpl-dashboard__recent-meta">
						<?php echo esc_html( get_the_date( '', $sermon->ID ) ); ?>
						<?php if ( $notes ) : ?>
							<span class="cpl-dashboard__recent-notes">
								<span class="screen-reader-text"><?php esc_html_e( 'Needs attention:', 'cp-library' ); ?></span>
								<?php echo esc_html( wp_sprintf( '%l', $notes ) ); ?>
							</span>
						<?php endif; ?>
					</span>

					<?php if ( 'publish' === $sermon->post_status ) : ?>
						<a class="cpl-dashboard__recent-view" href="<?php echo esc_url( get_permalink( $sermon->ID ) ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'View', 'cp-library' ); ?>
							<span class="screen-reader-text"><?php echo esc_html( get_the_title( $sermon->ID ) ); ?></span>
						</a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}
}
