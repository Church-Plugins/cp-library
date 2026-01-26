<?php
/**
 * Template part for displaying a notice when filters are active.
 *
 * @package CP_Library
 * @version 1.6.0
 *
 * @var array $data Data for the template
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Check if we have active filters and the helper function exists
if (!function_exists('cpl_has_active_filters') || !cpl_has_active_filters()) {
    return;
}

// Get current URL for clearing filters
$current_url = home_url(add_query_arg([], $GLOBALS['wp']->request));

// Get filter data
$filter_title = $data['filter_title'] ?? '';
$filter_count = $data['filter_count'] ?? '';

// If no filter title provided, try to generate one
if (empty($filter_title) && function_exists('cpl_get_active_filters')) {
    $active_filters = cpl_get_active_filters();
    if (!empty($active_filters)) {
        $filter_terms = [];
        foreach ($active_filters as $facet => $values) {
            $filter_terms[] = implode(', ', $values);
        }
        
        if (!empty($filter_terms)) {
            $filter_title = implode(' | ', $filter_terms);
        }
    }
}

// If no count provided, use the global query
if (empty($filter_count) && isset($GLOBALS['wp_query'])) {
    $filter_count = $GLOBALS['wp_query']->found_posts;
}
?>

<div class="cpl-filter-notice">
    <span class="cpl-filter-notice--icon">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
            <path d="M0 0h24v24H0V0z" fill="none"/>
            <path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/>
        </svg>
    </span>
    
    <div class="cpl-filter-notice--text">
        <?php if (!empty($filter_title)): ?>
            <span class="cpl-filter-notice--filters">
                <?php echo esc_html__('Filtered by', 'cp-library'); ?>: 
                <strong><?php echo esc_html($filter_title); ?></strong>
            </span>
        <?php endif; ?>
        
        <?php if (!empty($filter_count)): ?>
            <span class="cpl-filter-notice--count">
                (<?php echo sprintf(_n('%s result', '%s results', $filter_count, 'cp-library'), number_format_i18n($filter_count)); ?>)
            </span>
        <?php endif; ?>
    </div>
    
    <a href="<?php echo esc_url($current_url); ?>" class="cpl-filter-notice--clear">
        <?php echo esc_html__('Clear filters', 'cp-library'); ?>
    </a>
</div>

<?php if (current_user_can('edit_posts')): ?>
    <div class="cpl-canonical-info">
        <?php esc_html_e('Filter pages use a canonical URL to prevent search engines from indexing duplicate content.', 'cp-library'); ?>
        <?php if (function_exists('cpl_get_canonical_url')): ?>
            <br>
            <?php esc_html_e('Canonical URL:', 'cp-library'); ?> <code><?php echo esc_url(cpl_get_canonical_url()); ?></code>
        <?php endif; ?>
    </div>
<?php endif; ?>