<?php
// Set up default arguments
$args = [
    'context' => 'archive',
    'context_args' => []
];

// On archives, use the new selected filters from the Filters class
echo cp_library()->filters->render_selected_filters($args);
