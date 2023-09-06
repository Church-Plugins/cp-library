<?php

namespace CP_Library\Admin\Analytics;

use CP_Library\Admin\Settings;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Admin-only plugin initialization
 */
class Init {

	/**
	 * @var Init
	 */
  public static $page_name = 'cpl-analytics';

  /**
   * The class instance
   */
	protected static $_instance;

  /**
   * The date format as it is stored in wp_cp_log table
   */
  public static $date_format = 'Y-m-d H:i:s';

  /**
   * The number of items to show per page
   */
  public static $per_page = 10;

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

	}

	/**
	 * Admin init actions
	 *
	 * @return void
	 */
	protected function actions() {
    add_action( 'admin_menu', [ $this, 'analytics_menu' ] );
    add_action( 'wp_ajax_cpl-analytics-load-items', [ $this, 'load_items' ] );
    add_action( 'wp_ajax_cpl-analytics-get-overview',   [ $this, 'get_overview' ] );
	}

  /**
   * Adds an Analytics sub-menu item to the admin menu
   */
  public function analytics_menu() {
	$post_type = Settings::get_advanced( 'default_menu_item', 'item_type' ) === 'item_type' ? cp_library()->setup->post_types->item_type->post_type : cp_library()->setup->post_types->item->post_type;

    $page = add_submenu_page(
      'edit.php?post_type=' . $post_type,
      __( 'CP Sermon Library Analytics', 'cp-library' ),
      __( 'Analytics', 'cp-library' ), 'manage_options', self::$page_name,
      [ $this,'page_callback']
    );

    do_action( 'cpl-load-analytics-page', $page );
  }

  public function enqueue_scripts() {

  }

  /**
   * Gets displayed on the Analytics page
   */
  public function page_callback() {
    echo '<div id="cpl-analytics"></div>';
  }

  /**
   * Sends sermon analytics data as JSON
   */
  public function load_items() {

    $timeframe = 28;

    if( isset( $_POST['timeframe'] ) ) {
      $timeframe = (int) $_POST['timeframe'];
    }

    $page = 0;
    if( isset( $_POST['page'] ) ) {
      $page = (int) $_POST['page'];
    }

    $date = self::get_time( "$timeframe days ago" );
    $items = $this->get_analytics_since( $date, $page );


    wp_send_json( $items );
  }

  /**
   * Sends a top level overview as JSON
   */
  public function get_overview() {
    $timeframe = 28;

    if( isset( $_POST['timeframe'] ) ) {
      $timeframe = (int) $_POST['timeframe'];
    }

    $date = self::get_time( "$timeframe days ago" );

    wp_send_json(array(
      'audio_views'      => $this->get_action_count_since( $date, 'audio_view' ),
      'video_views'      => $this->get_action_count_since( $date, 'video_view' ),
      'engaged_views'    => $this->get_total_engaged_views_since( $date ),
      'average_duration' => $this->get_average_watch_time_since( $date ),
      'pages'            => $this->get_num_pages( $date )
    ), 200);
  }

  /**
   * Gets the total number of views since a given date
   * @param date string
   * @return string|null
   */
  public function get_total_views( $date ) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'cp_log';

    $query = $this->query_by_sql( "SELECT COUNT(*) FROM $table_name WHERE (action = 'audio_view' OR action = 'video_view') AND created > '%s'", $date);

    $views = $wpdb->get_var( $query );

    return $views;
  }

  /**
   * Gets the total number of a given log action since a given date
   * @param date string
   * @param action string
   * @return string|null
   */
  public function get_action_count_since($date, $action) {
    global $wpdb;

    $sql = "SELECT COUNT(log.id) FROM {$wpdb->prefix}cp_log as log WHERE log.action = %s AND log.created > %s";

    return $wpdb->get_var( $wpdb->prepare( $sql, $action, $date ) );
  }

  /**
   * Gets the total number of engaged views since a given date
   * @param date string
   * @return string|null
   */
  public function get_total_engaged_views_since($date) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'cp_log';

    $query = $this->query_by_sql( "SELECT COUNT(*) FROM $table_name WHERE (action = 'engaged_audio_view' OR action = 'engaged_video_view') AND created > '%s'", $date);

    $views = $wpdb->get_var( $query );

    return $views;
  }

  /**
   * Gets the average watch time in seconds for all sermons since a given date
   * @param date string
   * @return string|null
   */
  public function get_average_watch_time_since( $date ) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'cp_log';

    $query = $this->query_by_sql( "SELECT AVG(JSON_EXTRACT(log.data, '$.watch_duration')) FROM $table_name as log WHERE action = 'view_duration' AND created > '%s'", $date);

    $views = $wpdb->get_var( $query );

    return $views;
  }

  /**
   * Gets the number of pages for a list of sermons created since a given date
   * @param date string
   * @return string|null
   */
  public function get_num_pages() {
    global $wpdb;

    $sql = "SELECT COUNT(DISTINCT item.id)
            FROM {$wpdb->prefix}cpl_item as item";

    $total_rows = $wpdb->get_var( $wpdb->prepare( $sql ) );

    $total_pages = ceil( $total_rows / self::$per_page );

    return $total_pages;
  }

  /**
   * Gets all sermon information since a given date
   * @param date string
   * @param page int
   * @return array|object|null
   */
  public function get_analytics_since( $date, $page ) {
    global $wpdb;

    $per_page = self::$per_page;

    $start = $page * self::$per_page;

    $sql = "SELECT
              item.*,
              SUM(CASE WHEN (log.action = 'audio_view' OR log.action = 'video_view') THEN 1 ELSE 0 END) as views,
              SUM(CASE WHEN (log.action = 'engaged_audio_view' OR log.action = 'engaged_video_view') THEN 1 ELSE 0 END) as engaged_views,
              AVG(CASE WHEN log.action = 'view_duration' THEN JSON_EXTRACT(log.data, '$.watch_duration') ELSE NULL END) as view_duration
            FROM
              wp_cpl_item as item
            LEFT JOIN
              wp_cp_log as log ON item.id = log.object_id AND log.created > '%s'
            GROUP BY
              item.id
            ORDER BY
              views DESC
            LIMIT $per_page
            OFFSET $start;";

    $items = $wpdb->get_results( $wpdb->prepare( $sql, $date ) );

    foreach( $items as $item ) {
      $item->thumbnail = get_the_post_thumbnail_url( $item->origin_id, 'thumbnail' );
    }

    return $items;
  }

  public function query_by_sql( $sql, ...$args ) {
    global $wpdb;

    return $wpdb->prepare( $sql, ...$args );
  }

  /**
   * Gets any date formatted for use in the wp_cp_item table
   * @param time mixed
   * @return string|false
   */
  public static function get_time( $time ) {
    return date( self::$date_format, strtotime( $time ) );
  }

}
