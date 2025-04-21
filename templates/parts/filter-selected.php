<?php
// Set up default arguments
$args = [
    'context' => 'archive',
    'context_args' => []
];

// Render the selected filters using the filter system
$post_type = get_query_var('post_type') ?: 'cpl_item';
$filter_manager = CP_Library\Filters\FilterManager::get_filter_manager($post_type);

if ($filter_manager) {
    echo $filter_manager->render_selected_filters($args);
}
