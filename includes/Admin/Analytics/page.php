<script>window.inlineEditPosts = () => {}</script>
<?php 
/*
<h1>Hello, Analytics</h1>


<?php

add_action( 'wp_ajax_cpl-analytics-load-posts', 'cp_load_posts' );

add_action( 'wp_ajax_nopriv_cpl-analytics-load-posts', 'cp_load_posts' ); 


function cp_load_posts() {
  global $wpdb;

  if( isset( $_POST['page'] ) ) {
    $page = (int) sanitize_text_field( $_POST['page'] );
    $cur_page = $page;
    $page -= 1;

    $per_page = 10;

    $prev_btn = true;
    $next_btn = true;

    $start = $page * $per_page;

    $table_name = $wpdb->prefix . 'cpl_item';

    $all_messages = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM " . $table_name . " ORDER BY updated DESC LIMIT %d, %d", $start, $per_page ) );

    echo "Hello, AJAX";
  }

  else {
    echo "Hello, nopage ajax!";
  }

  exit;
}

// global $wpdb;

// $table_name = $wpdb->prefix . 'cp_log';

// $min_date = date( 'Y-m-d H:i:s', strtotime('-7 days') );

// $query = "SELECT COUNT(*)
// FROM $table_name
// WHERE action = 'view'
// AND created > '$min_date'";

// $prepared_query = $wpdb->prepare( $query, 'view' );

// $results = $wpdb->get_var( $prepared_query );

?>


?>

<script>
  jQuery(($) => {
    const $timeframe = $('#cpl-analytics-timeframe')

    function cpl_analytics_reload_posts(){
      var data = {
          page: 1,
          timeframe: $timeframe.val(),
          action: "cpl-analytics-load-items"
      };

      $.ajax({
        url: ajaxurl,
        method: 'POST',
        data: data,
        success: (data) => {
          $('#cpl-post-data').html(data)
        },
        error: console.error
      })

      $.ajax({
        url: ajaxurl,
        method: 'POST',
        data: { 
          timeframe: $timeframe.val(),
          action: "cpl-analytics-get-overview",
         },
        success: (data) => {
          updateInfo(data)
          updatePagination(data.pages)
        },
        error: console.warn
      })
    }

    function updateInfo(data) {
      console.log(data)
      $('#cpl-total-video-views').html(data.video_views)
      $('#cpl-total-audio-views').html(data.audio_views)
      $('#cpl-average-watch-time').html(data.average_duration)
      $('#cpl-total-engaged-views').html(data.engaged_views)
    }

    function updatePagination() {

    }

    $timeframe.on('change', () => {
      cpl_analytics_reload_posts()
    })

    cpl_analytics_reload_posts()
  })

  
</script>    
<div class='cpl-analytics'>
  <div class='cpl-analytics--actions'>
    <select id='cpl-analytics-timeframe' class='cpl-analytics-timeframe'>
      <option value='7'>Past 7 days</option>
      <option value='30'>Past month</option>
      <option value='365'>Past year</option>
    </select>

    <div class='cpl-analytics--overview'>
      <div class='cpl-analytics--overview--data'>
        <span class='material-icons'>smart_display</span>
        <span class='cpl-analytics--total-views' id='cpl-total-video-views'></span>
      </div>
      <span class='cpl-analytics--overview--title'>Video plays</span>
      <span>&gt; 30s</span>
    </div>

    <div class='cpl-analytics--overview'>
      <div class='cpl-analytics--overview--data'>
        <span class='material-icons'>volume_up</span>
        <span class='cpl-analytics--total-views' id='cpl-total-audio-views'></span>
      </div>
      <span class='cpl-analytics--overview--title'>Audio plays</span>
      <span>&gt; 30s</span>
    </div>

    <div class='cpl-analytics--overview'>
      <div class='cpl-analytics--overview--data'>
        <span class="material-icons">visibility</span>
        <span class='cpl-analytics--total-views' id='cpl-average-watch-time'></span>
      </div>
      <span class='cpl-analytics--overview--title'>Avg watch time</span>
    </div>

    <div class='cpl-analytics--overview'>
      <div class='cpl-analytics--overview--data'>
        <span class="material-icons">check</span>
        <span class='cpl-analytics--total-views' id='cpl-total-engaged-views'></span>
      </div>
      <span class='cpl-analytics--overview--title'>Engaged plays</span>
      <span>Watched 70% or more</span>
    </div>

  </div>
  <div id='cpl-post-data'></div>

  <div class='cpl-analytics-pagination'>
    <div id='cpl-pagination'></div>
  </div>
</div>

*/