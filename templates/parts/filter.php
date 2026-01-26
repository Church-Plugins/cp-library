<?php
use ChurchPlugins\Helpers;
use CP_Library\Admin\Settings;

// Render the filter form using the filter system
$post_type = get_query_var('post_type') ?: 'cpl_item';
$filter_manager = CP_Library\Filters\FilterManager::get_filter_manager($post_type);

if (!$filter_manager) {
    return;
}

// Get disabled filters based on post type
$disabled_filters = [];

if ($post_type === 'cpl_item') {
    $disabled_filters = Settings::get_item_disabled_filters();
} elseif ($post_type === 'cpl_item_type') {
    $disabled_filters = Settings::get_item_type_disabled_filters();
}

// Set up default arguments
$args = [
    'context' => 'archive',
    'context_args' => [],
    'show_search' => true,
    'container_class' => '',
    'disabled_filters' => $disabled_filters
];

echo $filter_manager->render_filter_form($args);
