<?php
/**
 * Template part for displaying structured data on filtered pages.
 *
 * @package CP_Library
 * @version 1.6.0
 *
 * @var array $data Data for the template
 * @var \WP_Post $item The post
 * @var string $post_type The post type
 * @var int $position The item position in the list
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Get post data
$item = $data['item'] ?? null;
$post_type = $data['post_type'] ?? '';
$position = $data['position'] ?? 1;

if (!$item || !$post_type) {
    return;
}

// Basic schema
$schema = [
    '@type' => 'ListItem',
    'position' => $position,
    'url' => get_permalink($item),
    'name' => get_the_title($item),
];

// Add featured image if available
if (has_post_thumbnail($item->ID)) {
    $image_id = get_post_thumbnail_id($item->ID);
    $image_url = wp_get_attachment_image_url($image_id, 'full');
    
    if ($image_url) {
        $schema['image'] = $image_url;
    }
}

// Add post type specific details
if ($post_type === 'cpl_item') {
    // Sermon-specific schema
    $schema['@type'] = 'CreativeWork';
    
    // Add description
    $description = get_post_meta($item->ID, 'sermon_description', true);
    if (empty($description)) {
        $description = has_excerpt($item->ID) ? get_the_excerpt($item->ID) : wp_trim_words(get_the_content(null, false, $item), 40);
    }
    if (!empty($description)) {
        $schema['description'] = strip_tags($description);
    }
    
    // Add date
    $date = get_post_meta($item->ID, 'sermon_date', true);
    if (empty($date)) {
        $date = get_the_date('Y-m-d', $item);
    }
    if (!empty($date)) {
        $schema['datePublished'] = $date;
    }
    
    // Add speaker
    $speaker_id = get_post_meta($item->ID, 'sermon_speaker', true);
    if (!empty($speaker_id)) {
        $speaker = get_post($speaker_id);
        if ($speaker) {
            $schema['author'] = [
                '@type' => 'Person',
                'name' => get_the_title($speaker),
                'url' => get_permalink($speaker),
            ];
        }
    }
    
    // Add series
    $series_id = wp_get_post_parent_id($item->ID);
    if (!empty($series_id)) {
        $series = get_post($series_id);
        if ($series) {
            $schema['isPartOf'] = [
                '@type' => 'CreativeWorkSeries',
                'name' => get_the_title($series),
                'url' => get_permalink($series),
            ];
        }
    }
    
    // Add audio/video if available
    $audio_url = get_post_meta($item->ID, 'sermon_audio', true);
    if (!empty($audio_url)) {
        $schema['audio'] = [
            '@type' => 'AudioObject',
            'contentUrl' => $audio_url,
            'name' => get_the_title($item) . ' - Audio',
        ];
    }
    
    $video_url = get_post_meta($item->ID, 'sermon_video', true);
    if (!empty($video_url)) {
        $schema['video'] = [
            '@type' => 'VideoObject',
            'contentUrl' => $video_url,
            'name' => get_the_title($item) . ' - Video',
        ];
    }
} elseif ($post_type === 'cpl_item_type') {
    // Series-specific schema
    $schema['@type'] = 'CreativeWorkSeries';
    
    // Add description
    $description = get_post_meta($item->ID, 'series_description', true);
    if (empty($description)) {
        $description = has_excerpt($item->ID) ? get_the_excerpt($item->ID) : wp_trim_words(get_the_content(null, false, $item), 40);
    }
    if (!empty($description)) {
        $schema['description'] = strip_tags($description);
    }
    
    // Add child sermons count
    $sermons_count = get_posts([
        'post_type' => 'cpl_item',
        'post_parent' => $item->ID,
        'posts_per_page' => -1,
        'fields' => 'ids',
    ]);
    
    if (!empty($sermons_count)) {
        $schema['numEpisodes'] = count($sermons_count);
    }
}

// Add any taxonomies as keywords
$taxonomies = get_object_taxonomies($post_type, 'objects');
$keywords = [];

foreach ($taxonomies as $taxonomy) {
    $terms = get_the_terms($item->ID, $taxonomy->name);
    if (!empty($terms) && !is_wp_error($terms)) {
        foreach ($terms as $term) {
            $keywords[] = $term->name;
        }
    }
}

if (!empty($keywords)) {
    $schema['keywords'] = implode(', ', $keywords);
}

// Organization data
$org_name = get_bloginfo('name');
$org_url = home_url();
$schema['provider'] = [
    '@type' => 'Organization',
    'name' => $org_name,
    'url' => $org_url,
];

// Optional logo
$custom_logo_id = get_theme_mod('custom_logo');
if ($custom_logo_id) {
    $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
    if ($logo_url) {
        $schema['provider']['logo'] = [
            '@type' => 'ImageObject',
            'url' => $logo_url,
        ];
    }
}

// Output schema as JSON
echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';