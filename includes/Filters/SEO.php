<?php
/**
 * Filter System SEO Enhancements
 *
 * Manages SEO optimizations for filtered content.
 *
 * @package CP_Library\Filters
 * @since 1.6.0
 */

namespace CP_Library\Filters;

/**
 * SEO class - Manages SEO for filtered content.
 *
 * This class provides SEO enhancements for filtered content pages, including
 * canonical URL management, meta tag optimization, and schema markup for
 * filtered archive pages.
 *
 * @since 1.6.0
 */
class SEO {

    /**
     * Instance of this class
     *
     * @var SEO
     */
    private static $instance = null;

    /**
     * Error handler instance
     *
     * @var ErrorHandler
     */
    protected $error_handler;

    /**
     * Active filters on the current page
     *
     * @var array
     */
    protected $active_filters = [];

    /**
     * Get the singleton instance
     *
     * @return SEO
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Initialize error handler
        $this->error_handler = ErrorHandler::get_instance();

        // Initialize hooks
        $this->actions();
    }

    /**
     * Setup actions and filters
     */
    protected function actions() {
		return;

        // Filter notice display
        add_action('cpl_before_archive_list', [$this, 'output_filter_notice'], 10);
        add_action('cpl_before_archive_item-type_list', [$this, 'output_filter_notice'], 10);
        // Canonical URL management
        add_filter('wpseo_canonical', [$this, 'filter_canonical_url'], 10, 1);
        add_filter('get_canonical_url', [$this, 'filter_canonical_url'], 10, 1);
        add_filter('rank_math/frontend/canonical', [$this, 'filter_canonical_url'], 10, 1);

        // Meta tag modifications
        add_filter('wpseo_title', [$this, 'filter_page_title'], 10, 1);
        add_filter('wpseo_metadesc', [$this, 'filter_meta_description'], 10, 1);
        add_filter('rank_math/frontend/title', [$this, 'filter_page_title'], 10, 1);
        add_filter('rank_math/frontend/description', [$this, 'filter_meta_description'], 10, 1);

        // Theme integration (for themes without SEO plugins)
        add_filter('document_title_parts', [$this, 'filter_document_title_parts'], 10, 1);
        add_action('wp_head', [$this, 'output_meta_tags'], 1);

        // Schema markup (JSON-LD)
        add_action('wp_head', [$this, 'output_schema_markup'], 10);
        add_filter('wpseo_schema_graph_pieces', [$this, 'filter_yoast_schema'], 10, 2);
        add_filter('rank_math/schema/graph', [$this, 'filter_rank_math_schema'], 10, 1);

        // Set up filter detection early
        add_action('wp', [$this, 'detect_active_filters']);
    }

    /**
     * Detect active filters on the current page
     * Called during the 'wp' action when WordPress core is loaded
     */
    public function detect_active_filters() {
        $this->active_filters = $this->get_active_filters();
    }

    /**
     * Get active filters from current request
     *
     * @return array Array of active filters by post type
     */
    protected function get_active_filters() {
        $active_filters = [];

        // Get all filter managers
        $filter_managers = FilterManager::get_registered_managers();

        // Get active filters for each manager
        foreach ($filter_managers as $post_type => $manager_config) {
            // Get the actual manager instance
            $manager_instance = FilterManager::get_filter_manager($post_type);

            if (!$manager_instance) {
                continue;
            }

            $facets = $manager_instance->get_active_facets_from_request();

            if (!empty($facets)) {
                $active_filters[$post_type] = [
                    'facets' => $facets,
                    'manager' => $manager_instance
                ];
            }
        }

        return $active_filters;
    }

    /**
     * Check if the current page has active filters
     *
     * @return bool True if filters are active, false otherwise
     */
    public function has_active_filters() {
        return !empty($this->active_filters);
    }

    /**
     * Get the filter description for the current page
     *
     * @param int $max_terms Maximum number of terms to include in the description
     * @return string Filter description
     */
    protected function get_filter_description($max_terms = 3) {
        // If no active filters, return empty string
        if (!$this->has_active_filters()) {
            return '';
        }

        $descriptions = [];

        foreach ($this->active_filters as $post_type => $filter_data) {
            $manager = $filter_data['manager'];
            $facets = $filter_data['facets'];

            $post_type_label = get_post_type_object($post_type)->labels->name ?? $post_type;
            $terms_by_facet = [];

            foreach ($facets as $facet_id => $values) {
                // Get facet config
                $facet_config = $manager->get_facet($facet_id);
                if (!$facet_config) {
                    continue;
                }

                $facet_label = $facet_config['label'] ?? $facet_id;
                $terms = [];

                // Handle different facet types
                if ($facet_config['type'] === 'taxonomy' && !empty($facet_config['taxonomy'])) {
                    // Get term names for taxonomy facets
                    foreach ($values as $value) {
                        $term = get_term_by('slug', $value, $facet_config['taxonomy']);
                        if ($term) {
                            $terms[] = $term->name;
                        }
                    }
                } else {
                    // For other facet types, use values directly
                    $terms = $values;
                }

                // Limit the number of terms displayed
                if (count($terms) > $max_terms) {
                    $terms = array_slice($terms, 0, $max_terms);
                    $terms[] = '...';
                }

                if (!empty($terms)) {
                    $terms_by_facet[$facet_label] = $terms;
                }
            }

            // Build description for this post type
            if (!empty($terms_by_facet)) {
                $facet_descriptions = [];

                foreach ($terms_by_facet as $facet_label => $terms) {
                    $facet_descriptions[] = sprintf(
                        '%s: %s',
                        $facet_label,
                        implode(', ', $terms)
                    );
                }

                $descriptions[] = sprintf(
                    '%s filtered by %s',
                    $post_type_label,
                    implode('; ', $facet_descriptions)
                );
            }
        }

        if (empty($descriptions)) {
            return '';
        }

        return implode('. ', $descriptions) . '.';
    }

    /**
     * Filter the canonical URL for filtered pages
     *
     * @param string $canonical_url The original canonical URL
     * @return string The modified canonical URL
     */
    public function filter_canonical_url($canonical_url) {
        // If no active filters or not a filter-able page, return original URL
        if (!$this->has_active_filters() || !is_archive()) {
            return $canonical_url;
        }

        // Set canonical to the page URL without filter parameters
        $canonical_url = $this->get_unfiltered_url();

        return $canonical_url;
    }

    /**
     * Get the current URL without filter parameters
     *
     * @return string The unfiltered URL
     */
    protected function get_unfiltered_url() {
        global $wp;

        // Get current URL
        $current_url = home_url($wp->request);

        // If not a filter-able archive page, return current URL
        if (!is_archive()) {
            return $current_url;
        }

        // Check if we have any active filter managers
        $filter_managers = FilterManager::get_registered_managers();

        if (empty($filter_managers)) {
            return $current_url;
        }

        // Get all facet parameters from all managers
        $filter_params = [];
        foreach ($filter_managers as $manager) {
            $facets = $manager->get_facets();
            foreach ($facets as $facet_config) {
                if (!empty($facet_config['param'])) {
                    $filter_params[] = $facet_config['param'];
                }
            }
        }

        // Add search parameter
        $filter_params[] = 'cpl_search';

        // Remove filter parameters from URL
        $url_parts = parse_url($current_url);
        if (!empty($url_parts['query'])) {
            parse_str($url_parts['query'], $query_params);

            foreach ($filter_params as $param) {
                if (isset($query_params[$param])) {
                    unset($query_params[$param]);
                }

                // Also check for array parameters (param[])
                $array_param = $param . '[]';
                if (isset($query_params[$array_param])) {
                    unset($query_params[$array_param]);
                }
            }

            // Rebuild URL without filter parameters
            $url_parts['query'] = http_build_query($query_params);
            $current_url = $this->build_url($url_parts);
        }

        return $current_url;
    }

    /**
     * Helper function to build a URL from parse_url() parts
     *
     * @param array $parts URL parts from parse_url()
     * @return string Assembled URL
     */
    private function build_url($parts) {
        $url = '';

        if (!empty($parts['scheme'])) {
            $url .= $parts['scheme'] . '://';
        }

        if (!empty($parts['user'])) {
            $url .= $parts['user'];
            if (!empty($parts['pass'])) {
                $url .= ':' . $parts['pass'];
            }
            $url .= '@';
        }

        if (!empty($parts['host'])) {
            $url .= $parts['host'];
        }

        if (!empty($parts['port'])) {
            $url .= ':' . $parts['port'];
        }

        if (!empty($parts['path'])) {
            $url .= $parts['path'];
        }

        if (!empty($parts['query'])) {
            $url .= '?' . $parts['query'];
        }

        if (!empty($parts['fragment'])) {
            $url .= '#' . $parts['fragment'];
        }

        return $url;
    }

    /**
     * Filter the page title for SEO plugins
     *
     * @param string $title The original title
     * @return string The modified title
     */
    public function filter_page_title($title) {
        // If no active filters or not an archive page, return original title
        if (!$this->has_active_filters() || !is_archive()) {
            return $title;
        }

        // Append filter information to title
        $filter_title = $this->get_filter_title();

        if (!empty($filter_title)) {
            $title = sprintf('%s - %s', $title, $filter_title);
        }

        return $title;
    }

    /**
     * Filter the document title parts for themes without SEO plugins
     *
     * @param array $title_parts The title parts array
     * @return array The modified title parts
     */
    public function filter_document_title_parts($title_parts) {
        // If no active filters or not an archive page, return original title parts
        if (!$this->has_active_filters() || !is_archive()) {
            return $title_parts;
        }

        // Append filter information to title
        $filter_title = $this->get_filter_title();

        if (!empty($filter_title) && isset($title_parts['title'])) {
            $title_parts['title'] = sprintf('%s - %s', $title_parts['title'], $filter_title);
        }

        return $title_parts;
    }

    /**
     * Get a concise title representing the active filters
     *
     * @param int $max_terms Maximum number of terms to include in the title
     * @return string Filter title
     */
    protected function get_filter_title($max_terms = 2) {
        // If no active filters, return empty string
        if (!$this->has_active_filters()) {
            return '';
        }

        $titles = [];

        foreach ($this->active_filters as $post_type => $filter_data) {
            $manager = $filter_data['manager'];
            $facets = $filter_data['facets'];

            $terms_by_facet = [];

            foreach ($facets as $facet_id => $values) {
                // Get facet config
                $facet_config = $manager->get_facet($facet_id);
                if (!$facet_config) {
                    continue;
                }

                $terms = [];

                // Handle different facet types
                if ($facet_config['type'] === 'taxonomy' && !empty($facet_config['taxonomy'])) {
                    // Get term names for taxonomy facets
                    foreach ($values as $value) {
                        $term = get_term_by('slug', $value, $facet_config['taxonomy']);
                        if ($term) {
                            $terms[] = $term->name;
                        }
                    }
                } else {
                    // For other facet types, use values directly
                    $terms = $values;
                }

                // Limit the number of terms displayed
                if (count($terms) > $max_terms) {
                    $terms = array_slice($terms, 0, $max_terms);
                    $terms[] = '...';
                }

                if (!empty($terms)) {
                    $terms_by_facet[] = implode(', ', $terms);
                }
            }

            // Build title for this post type
            if (!empty($terms_by_facet)) {
                $titles[] = implode(' | ', $terms_by_facet);
            }
        }

        return implode(' | ', $titles);
    }

    /**
     * Filter the meta description for SEO plugins
     *
     * @param string $description The original description
     * @return string The modified description
     */
    public function filter_meta_description($description) {
        // If no active filters or not an archive page, return original description
        if (!$this->has_active_filters() || !is_archive()) {
            return $description;
        }

        // Get filter description
        $filter_desc = $this->get_filter_description();

        if (!empty($filter_desc)) {
            if (!empty($description)) {
                // Append filter information to existing description
                $description = sprintf('%s %s', $description, $filter_desc);
            } else {
                // Use filter description as meta description
                $description = $filter_desc;
            }
        }

        return $description;
    }

    /**
     * Output meta tags for themes without SEO plugins
     */
    public function output_meta_tags() {
        // Only run for archive pages with active filters
        if (!$this->has_active_filters() || !is_archive()) {
            return;
        }

        // Check if popular SEO plugins are active
        if ($this->is_seo_plugin_active()) {
            return;
        }

        // Add canonical tag if not already added
        if (!$this->has_canonical_tag()) {
            $canonical_url = $this->get_unfiltered_url();
            printf('<link rel="canonical" href="%s" />' . "\n", esc_url($canonical_url));
        }

        // Add meta description if not already added
        if (!$this->has_meta_description()) {
            $filter_desc = $this->get_filter_description();
            if (!empty($filter_desc)) {
                printf('<meta name="description" content="%s" />' . "\n", esc_attr($filter_desc));
            }
        }

        // Add robots meta tag for filtered pages
        printf('<meta name="robots" content="noindex, follow" />' . "\n");
    }

    /**
     * Output schema markup (JSON-LD) for filtered pages
     */
    public function output_schema_markup() {
        // Only run for archive pages with active filters
        if (!$this->has_active_filters() || !is_archive()) {
            return;
        }

        // Check if popular SEO plugins are active
        if ($this->is_seo_plugin_active()) {
            return;
        }

        // Build schema markup
        $schema = $this->build_schema_markup();

        if (!empty($schema)) {
            echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
        }
    }

    /**
     * Build schema markup for filtered pages
     *
     * @return array Schema markup array
     */
    protected function build_schema_markup() {
        global $wp_query;

        // Base schema
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'url' => esc_url($this->get_current_url()),
            'name' => wp_get_document_title(),
        ];

        // Add description
        $filter_desc = $this->get_filter_description();
        if (!empty($filter_desc)) {
            $schema['description'] = $filter_desc;
        }

        // Add breadcrumb
        $schema['breadcrumb'] = [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $this->get_breadcrumb_items(),
        ];

        // Add items if available
        $items = [];

        if (!empty($wp_query->posts)) {
            foreach ($wp_query->posts as $post) {
                $item_schema = $this->get_item_schema($post);
                if (!empty($item_schema)) {
                    $items[] = $item_schema;
                }
            }
        }

        if (!empty($items)) {
            $schema['mainEntity'] = [
                '@type' => 'ItemList',
                'itemListElement' => $items,
            ];
        }

        return $schema;
    }

    /**
     * Get breadcrumb items for schema markup
     *
     * @return array Breadcrumb items
     */
    protected function get_breadcrumb_items() {
        $items = [];

        // Home page
        $items[] = [
            '@type' => 'ListItem',
            'position' => 1,
            'name' => __('Home', 'cp-library'),
            'item' => home_url(),
        ];

        // Archive page
        if (is_post_type_archive()) {
            $post_type = get_query_var('post_type');
            $post_type_obj = get_post_type_object($post_type);

            if ($post_type_obj) {
                $items[] = [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => $post_type_obj->labels->name,
                    'item' => get_post_type_archive_link($post_type),
                ];
            }
        } elseif (is_tax()) {
            $term = get_queried_object();
            $taxonomy = get_taxonomy($term->taxonomy);
            $post_type = $taxonomy->object_type[0] ?? '';
            $post_type_obj = get_post_type_object($post_type);

            if ($post_type_obj) {
                $items[] = [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => $post_type_obj->labels->name,
                    'item' => get_post_type_archive_link($post_type),
                ];
            }

            if ($taxonomy) {
                $items[] = [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => $term->name,
                    'item' => get_term_link($term),
                ];
            }
        }

        // Filtered page
        $items[] = [
            '@type' => 'ListItem',
            'position' => count($items) + 1,
            'name' => $this->get_filter_title(),
            'item' => $this->get_current_url(),
        ];

        return $items;
    }

    /**
     * Get schema markup for an individual item
     *
     * @param \WP_Post $post The post object
     * @return array Item schema markup
     */
    protected function get_item_schema($post) {
        $schema = [
            '@type' => 'ListItem',
            'position' => 1, // Will be updated later
            'url' => get_permalink($post),
            'name' => get_the_title($post),
        ];

        // Add featured image if available
        if (has_post_thumbnail($post->ID)) {
            $image_id = get_post_thumbnail_id($post->ID);
            $image_url = wp_get_attachment_image_url($image_id, 'full');

            if ($image_url) {
                $schema['image'] = $image_url;
            }
        }

        return $schema;
    }

    /**
     * Filter Yoast SEO schema markup
     *
     * @param array $pieces Schema pieces
     * @param \WPSEO_Schema_Context $context Schema context
     * @return array Modified schema pieces
     */
    public function filter_yoast_schema($pieces, $context) {
        // Only modify for archive pages with active filters
        if (!$this->has_active_filters() || !is_archive()) {
            return $pieces;
        }

        // Modify existing pieces
        foreach ($pieces as $index => $piece) {
            if (isset($piece->schema['@type']) && $piece->schema['@type'] === 'CollectionPage') {
                // Modify collection page schema
                $piece->schema['name'] = wp_get_document_title();

                // Add description
                $filter_desc = $this->get_filter_description();
                if (!empty($filter_desc)) {
                    $piece->schema['description'] = $filter_desc;
                }
            }
        }

        return $pieces;
    }

    /**
     * Filter Rank Math schema markup
     *
     * @param array $schema Schema data
     * @return array Modified schema data
     */
    public function filter_rank_math_schema($schema) {
        // Only modify for archive pages with active filters
        if (!$this->has_active_filters() || !is_archive()) {
            return $schema;
        }

        // Find and modify collection page schema
        foreach ($schema as $index => $piece) {
            if (isset($piece['@type']) && $piece['@type'] === 'CollectionPage') {
                // Modify collection page schema
                $schema[$index]['name'] = wp_get_document_title();

                // Add description
                $filter_desc = $this->get_filter_description();
                if (!empty($filter_desc)) {
                    $schema[$index]['description'] = $filter_desc;
                }
            }
        }

        return $schema;
    }

    /**
     * Check if a popular SEO plugin is active
     *
     * @return bool True if an SEO plugin is active, false otherwise
     */
    protected function is_seo_plugin_active() {
        // Check for Yoast SEO
        if (defined('WPSEO_VERSION')) {
            return true;
        }

        // Check for Rank Math
        if (class_exists('RankMath')) {
            return true;
        }

        // Check for All in One SEO Pack
        if (class_exists('AIOSEO\Plugin\AIOSEO')) {
            return true;
        }

        // Check for The SEO Framework
        if (class_exists('The_SEO_Framework\Plugin')) {
            return true;
        }

        return false;
    }

    /**
     * Check if a canonical tag is already output
     *
     * @return bool True if a canonical tag is found, false otherwise
     */
    protected function has_canonical_tag() {
        // Check if wp_head has been run
        if (!did_action('wp_head')) {
            return false;
        }

        // Get the HTML output from wp_head
        ob_start();
        do_action('wp_head');
        $head = ob_get_clean();

        // Check for canonical tag
        return (bool) preg_match('/<link[^>]+rel=["\']canonical["\'][^>]+>/i', $head);
    }

    /**
     * Check if a meta description tag is already output
     *
     * @return bool True if a meta description tag is found, false otherwise
     */
    protected function has_meta_description() {
        // Check if wp_head has been run
        if (!did_action('wp_head')) {
            return false;
        }

        // Get the HTML output from wp_head
        ob_start();
        do_action('wp_head');
        $head = ob_get_clean();

        // Check for meta description tag
        return (bool) preg_match('/<meta[^>]+name=["\']description["\'][^>]+>/i', $head);
    }

    /**
     * Get the current URL with query parameters
     *
     * @return string Current URL
     */
    protected function get_current_url() {
        global $wp;
        return home_url(add_query_arg($_GET, $wp->request));
    }

    /**
     * Output filter notice on archive pages
     */
    public function output_filter_notice() {
        // Only display on archive pages with active filters
        if (!$this->has_active_filters() || !is_archive()) {
            return;
        }

        // Display the filter notice template
        cp_library()->templates->get_template_part('parts/filter-notice', [
            'filter_title' => $this->get_filter_title(),
            'filter_count' => $GLOBALS['wp_query']->found_posts ?? 0
        ]);
    }
}
