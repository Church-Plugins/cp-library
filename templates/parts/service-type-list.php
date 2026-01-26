<?php
/**
 * Template part for displaying service type in a list format
 */

use CP_Library\Controllers\ServiceType;

// Get service type ID from the URL
$service_type_id = isset($_GET['service-type']) ? $_GET['service-type'] : 0;
$service_type = null;

// Try to get the service type controller
try {
    $service_type = new ServiceType($service_type_id, false); // false to get by source ID directly
} catch (Exception $e) {
    // If we can't get it, show an error
    echo '<p>' . __('Service type not found.', 'cp-library') . '</p>';
    error_log($e->getMessage());
    return;
}

// Get service type data using the controller
$title = $service_type->get_title();
$permalink = add_query_arg('service-type', $service_type_id, get_post_type_archive_link(cp_library()->setup->post_types->item->post_type));
$description = $service_type->get_content();

// Display the template
?>
<article class="cpl-item-list cpl-service-type-list">
    <div class="cpl-item-list--content">
        <header class="cpl-item-list--header">
            <h2 class="cpl-item-list--title">
                <a href="<?php echo esc_url($permalink); ?>" rel="bookmark"><?php echo esc_html($title); ?></a>
            </h2>
        </header>

        <?php if (!empty($description)) : ?>
        <div class="cpl-item-list--description">
            <?php echo $description; ?>
        </div>
        <?php endif; ?>

        <div class="cpl-item-list--meta">
            <?php 
            // Get count of items with this service type
            try {
                $count = $service_type->get_items_count();
                printf(
                    _n(
                        '%s %s', 
                        '%s %s', 
                        $count, 
                        'cp-library'
                    ), 
                    number_format_i18n($count),
                    _n(
                        cp_library()->setup->post_types->item->single_label,
                        cp_library()->setup->post_types->item->plural_label,
                        $count,
                        'cp-library'
                    )
                );
            } catch (Exception $e) {
                error_log($e->getMessage());
            }
            ?>
        </div>
        
        <footer class="cpl-item-list--footer">
            <a href="<?php echo esc_url($permalink); ?>" class="cpl-item-list--more-link">
                <?php 
                printf(
                    __('View all %s in %s', 'cp-library'),
                    strtolower(cp_library()->setup->post_types->item->plural_label),
                    esc_html($title)
                ); 
                ?>
            </a>
        </footer>
    </div>
</article>