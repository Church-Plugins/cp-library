<?php
/**
 * Podcast Feed Template
 *
 * Note: Not relying on DOMDocument to avoid issue in rare case that is not available.
 *
 * @since 1.4.0
 * @package CP_Library
 */

// No direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CP_Library\Admin\Settings\Podcast;
use CP_Library\Templates;

// Podcast settings array.
// Key is setting ID without podcast_ prefix, value is default when setting value empty.
$settings = array(
	'image'        => '',
	'title'        => get_the_title_rss(),
	'subtitle'     => get_bloginfo( 'description' ),
	'summary'      => Podcast::get( 'subtitle', get_bloginfo( 'description' ) ),
	'author'       => get_bloginfo( 'name' ),
	'copyright'    => '© ' . get_bloginfo( 'name' ),
	'link'         => trailingslashit( home_url() ),
	'email'        => '',
	'category'     => '',
	'not_explicit' => 1,
	'language'     => 'en-US',
	'new_url'      => '',
);

// Loop settings to prepare values.
foreach ( $settings as $setting => $default ) {

	// Get setting value.
	$value = Podcast::get( $setting, $default );


	// Make XML-safe and trim.
	if ( in_array( $setting, [ 'subtitle', 'summary' ] ) ) {
		$value = apply_filters( 'cpl_podcast_content', $value );
	} elseif ( in_array( $settings, [ 'title', 'author' ] ) ) {
		$value = apply_filters( 'cpl_podcast_text', $value );
	}

	$value = trim( $value );

	// Create variable with same name as key ($title, $summary, etc.).
	extract( array( $setting => $value ) ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
}

// Default image is post thumbnail, otherwise specified image.
if ( is_singular() ) {
	$fallback_image = $image;
	$image          = get_the_post_thumbnail_url( get_the_ID(), array( 600, 600 ) );
	$image          = $image ? $image : $fallback_image;
}

// Category.
if ( $category && 'none' !== $category ) {
	list( $category, $subcategory ) = explode( '|', $category );
} else {
	$category = '';
}

// Other podcast settings.
$owner_name = htmlspecialchars( get_bloginfo( 'name' ) ); // Owner name as site name.
$explicit   = $not_explicit ? 'no' : 'yes'; // Explicit or not.

// Character set from WordPress settings.
$charset  = get_option( 'blog_charset' );
$per_page = get_option( 'posts_per_rss', 10 );

if ( isset( $_GET['show-all'] ) ) {
	$per_page = 9999;
}

// Set content type and charset.
header( 'Content-Type: ' . feed_content_type( 'rss2' ) . '; charset=' . $charset, true );

// Begin output.
// The contents are retrieved from buffer, trimmed and formatted before output.
echo '<?xml version="1.0" encoding="' . esc_attr( $charset ) . '"?>';
?>
<rss version="2.0"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wfw="http://wellformedweb.org/CommentAPI/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:atom="http://www.w3.org/2005/Atom"
	xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
	xmlns:slash="http://purl.org/rss/1.0/modules/slash/"
	xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"
	xmlns:googleplay="http://www.google.com/schemas/play-podcasts/1.0"
	<?php do_action( 'rss2_ns' ); // Core: Fires at the end of the RSS root to add namespaces. ?>
>

	<channel>

		<title><?php echo esc_html( $title ); ?></title>

		<atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />

		<?php if ( $link ) : ?>
			<link><?php echo esc_url( $link ); ?></link>
		<?php endif; ?>

		<language><?php echo esc_html( $language ); ?></language>

		<copyright><?php echo esc_html( $copyright ); ?></copyright>

		<itunes:subtitle><?php echo esc_html( $subtitle ); ?></itunes:subtitle>

		<itunes:author><?php echo esc_html( $author ); ?></itunes:author>
		<googleplay:author><?php echo esc_html( $author ); ?></googleplay:author>

		<?php $description = empty( $summary ) ? $subtitle : $summary; ?>

		<description><?php echo esc_html( $description ); ?></description>
		<googleplay:description><?php echo esc_html( $description ); ?></googleplay:description>

		<?php if ( $email ) : ?>

			<itunes:owner>
				<itunes:name><?php echo esc_html( $owner_name ); ?></itunes:name>
				<itunes:email><?php echo esc_html( $email ); ?></itunes:email>
			</itunes:owner>

			<googleplay:owner><?php echo esc_html( $email ); ?></googleplay:owner>
			<googleplay:email><?php echo esc_html( $email ); ?></googleplay:email>

		<?php endif; ?>

		<?php if ( $image ) : ?>
			<itunes:image href="<?php echo esc_url( $image ); ?>"></itunes:image>
			<googleplay:image href="<?php echo esc_url( $image ); ?>"></googleplay:image>
		<?php endif; ?>

		<?php if ( $category ) : ?>

			<itunes:category text="<?php echo esc_attr( $category ); ?>">

				<?php if ( $subcategory ) : ?>
					<itunes:category text="<?php echo esc_attr( $subcategory ); ?>"/>
				<?php endif; ?>

			</itunes:category>

			<googleplay:category text="<?php echo esc_attr( $category ); ?>"></googleplay:category>

		<?php endif; ?>

		<itunes:explicit><?php echo esc_html( $explicit ); ?></itunes:explicit>
		<googleplay:explicit><?php echo esc_html( $explicit ); ?></googleplay:explicit>

		<?php if ( $new_url ) : ?>
			<itunes:new-feed-url><?php echo esc_url( $new_url ); ?></itunes:new-feed-url>
		<?php endif; ?>

		<sy:updatePeriod><?php echo esc_html( apply_filters( 'rss_update_period', 'hourly' ) ); // Core filter. ?></sy:updatePeriod>
		<sy:updateFrequency><?php echo esc_html( apply_filters( 'rss_update_frequency', '1' ) ); // Core filter. ?></sy:updateFrequency>

		<lastBuildDate>
		<?php
		// from core, standard practice.
		$date = get_lastpostmodified( 'GMT' );
		echo esc_html( $date ? mysql2date( 'r', $date, false ) : gmdate( 'r' ) );
		?>
		</lastBuildDate>

		<?php

		do_action( 'rss2_head' ); // Core: Fires at the end of the RSS2 Feed Header (before items).

		global $wp_query;

		if ( true === $wp_query->get( 'cpl_relation_feed' ) && get_post_type() !== cp_library()->setup->post_types->item->post_type ) {
			$items = array();

			if ( get_post_type() === cp_library()->setup->post_types->item_type->post_type ) {
				$items = \CP_Library\Models\ItemType::get_instance_from_origin( get_the_ID() )->get_items();
				$items = wp_list_pluck( $items, 'origin_id' );
			} elseif ( get_post_type() === cp_library()->setup->post_types->speaker->post_type ) {
				$items = \CP_Library\Models\Speaker::get_instance_from_origin( get_the_ID() )->get_all_items();
			} elseif ( get_post_type() === cp_library()->setup->post_types->service_type->post_type ) {
				$items = \CP_Library\Models\ServiceType::get_instance_from_origin( get_the_ID() )->get_all_items();
			}

			if ( ! empty( $items ) ) {
				$query_args = array(
					'post_type'      => cp_library()->setup->post_types->item->post_type,
					'post__in'       => array_map( 'absint', $items ),
					'posts_per_page' => $per_page,
					'orderby'        => 'post__in',
					'post_status'    => 'publish',
					'fields'         => 'ids',
					'meta_query'     => array(
						'relation' => 'AND',
						array(
							'key'     => 'enclosure',
							'value'   => '',
							'compare' => '!=',
						),
						array(
							'relation' => 'OR',
							array(
								'key'     => 'podcast_exclude',
								'value'   => '',
								'compare' => '=',
							),
							array(
								'key'     => 'podcast_exclude',
								'value'   => '',
								// empty required for back compat with WP 3.8 and below (core bug).
								'compare' => 'NOT EXISTS',
								// field did not always exist, so don't just check empty; check not exist and include those.
							),
						),
					),
				);

				$query = new WP_Query( $query_args );

				while ( $query->have_posts() ) {
					$query->the_post();
					Templates::get_template_part( 'parts/podcast-item' );
				}

				wp_reset_postdata();
			}
		} else {
			while ( have_posts() ) {
				the_post();
				Templates::get_template_part( 'parts/podcast-item' );
			}
		}
		?>

	</channel>

</rss>
