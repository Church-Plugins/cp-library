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

// On archives, use the new filter form from the Filters class
echo cp_library()->filters->render_filter_form($args);
