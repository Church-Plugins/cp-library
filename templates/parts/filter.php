<?php
use ChurchPlugins\Helpers;
use CP_Library\Admin\Settings;

// Set up default arguments
$args = [
    'context' => 'archive',
    'context_args' => [],
    'show_search' => true,
    'container_class' => '',
    'disabled_filters' => Settings::get_advanced('disable_filters', [])
];

// Render the filter form using the filter system
$post_type = get_query_var('post_type') ?: 'cpl_item';
$filter_manager = CP_Library\Filters\FilterManager::get_filter_manager($post_type);

if ($filter_manager) {
    echo $filter_manager->render_filter_form($args);
}
