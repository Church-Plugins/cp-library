<?php

namespace CP_Library\Admin\Analytics;

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
  
	protected static $_instance;

  public static $date_format = 'Y-m-d H:i:s';  

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


  public function analytics_menu() {
    $post_type = cp_library()->setup->post_types->item_type_enabled() ? cp_library()->setup->post_types->item_type->post_type : cp_library()->setup->post_types->item->post_type;

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

  public function page_callback() {
    echo '<div id="cpl-analytics"></div>';

    include __DIR__ . '/page.php';
  }

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

    $items = $this->get_items_since( $date, $page );


    wp_send_json( $items );

    ?>
    <table class='cpl-analytics-posts'>
      <tr>
        <th></th>
        <th>Title</th>
        <th>Views</th>
        <th>Avg duration</th>
        <th>Engaged Plays</th>
      </tr>
      <?php foreach( $items as $item ): ?>
        <?php 
          $views = (int) $item->views;
          $engaged = (int) $item->engaged_views;
          $view_duration = (int) $item->view_duration;
          
          $engaged_plays = $views < 1 ? 0 : floor( $engaged / $views * 100 );
          // $avg_duration =  $views < 1 ? 0 : floor( $view_duration / $views );
        ?>
        <?php  ?>
        <tr class='cpl-analytics-sermon'>
          <td class='cpl-analytics-sermon--thumbnail'></td>
          <td class='cpl-analytics-sermon--title'><?php echo esc_html( $item->title ) ?></td>
          <td class='cpl-analytics-sermon--plays'><?php echo esc_html( $item->views ) ?></td>
          <td class='cpl-analytics-sermon--avd'><?php echo gmdate('H:i:s', $view_duration ) ?></td>
          <td class='cpl-analytics-sermon--engagement'><?php echo $engaged_plays ?>%</td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php

    exit;
  }

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

  public function get_total_views( $date ) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'cp_log';

    $query = $this->query_by_sql( "SELECT COUNT(*) FROM $table_name WHERE (action = 'audio_view' OR action = 'video_view') AND created > '%s'", $date);

    $views = $wpdb->get_var( $query );

    return $views;
  }

  public function get_action_count_since($date, $action) {
    global $wpdb;

    $sql = "SELECT COUNT(log.id) FROM wp_cp_log as log WHERE log.action = %s AND created > %s";

    return $wpdb->get_var( $wpdb->prepare( $sql, $action, $date ) );
  }

  public function get_total_engaged_views_since($date) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'cp_log';

    $query = $this->query_by_sql( "SELECT COUNT(*) FROM $table_name WHERE (action = 'engaged_audio_view' OR action = 'engaged_video_view') AND created > '%s'", $date);

    $views = $wpdb->get_var( $query );

    return $views;
  }

  public function get_average_watch_time_since( $date ) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'cp_log';

    $query = $this->query_by_sql( "SELECT AVG(CAST(log.data as UNSIGNED)) FROM $table_name as log WHERE action = 'view_duration' AND created > '%s'", $date);

    $views = $wpdb->get_var( $query );

    return $views;
  }

  public function get_num_pages( $date ) {
    global $wpdb;

    $sql = "SELECT COUNT(DISTINCT item.id)
            FROM wp_cpl_item as item
            WHERE item.updated > '%s'";

    $total_rows = $wpdb->get_var( $wpdb->prepare( $sql, $date ) );

    $total_pages = ceil( $total_rows / self::$per_page );

    return $total_pages;
  }

  public function get_items_since( $date, $page ) {
    global $wpdb;

    $per_page = self::$per_page;
  
    $start = $page * self::$per_page;

    $sql = "SELECT 
              item.*,
              SUM(CASE WHEN (log.action = 'audio_view' OR log.action = 'video_view') THEN 1 ELSE 0 END) as views,
              SUM(CASE WHEN (log.action = 'engaged_audio_view' OR log.action = 'engaged_video_view') THEN 1 ELSE 0 END) as engaged_views,
              AVG(CASE WHEN log.action = 'view_duration' THEN CAST(log.data AS SIGNED) ELSE NULL END) as view_duration
            FROM
              wp_cpl_item as item
            LEFT JOIN
              wp_cp_log as log ON item.id = log.object_id
            WHERE
              item.updated > '%s'
            GROUP BY
              item.id
            ORDER BY
              views DESC
            LIMIT $per_page
            OFFSET $start;";

    $items = $wpdb->get_results( $wpdb->prepare( $sql, $date ) );

    return $items;
  }

  public function query_by_sql( $sql, ...$args ) {
    global $wpdb;

    return $wpdb->prepare( $sql, ...$args );
  }

  public static function get_time( $time ) {
    return date( self::$date_format, strtotime( $time ) );
  }
}