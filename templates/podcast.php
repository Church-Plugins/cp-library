<?php
/**
 * Podcast Feed Template
 *
 * Note: Not relying on DOMDocument to avoid issue in rare case that is not available.
 *
 * @since      1.4
 */

// No direct access.
if (! defined( 'ABSPATH' )) {
	exit;
}

use CP_Library\Admin\Settings\Podcast;

// Podcast settings array.
// Key is setting ID without podcast_ prefix, value is default when setting value empty.
$settings = array(
	'image'        => '',
	'title'        => get_bloginfo( 'name' ),
	'subtitle'     => get_bloginfo( 'description' ),
	'summary'      => Podcast::get( 'subtitle', get_bloginfo( 'description' ) ),
	'author'       => get_bloginfo( 'name' ),
	'copyright'    => '© ' . get_bloginfo( 'name' ),
	'link'         => trailingslashit( home_url() ),
	'email'        => '',
	'category'     => '',
	'not_explicit' => '',
	'language'     => 'en-US',
	'new_url'      => '',
);

// Loop settings to prepare values.
foreach ($settings as $setting => $default) {

	// Get setting value.
	$value = Podcast::get( $setting, $default );

	// Make XML-safe and trim.
	$value = htmlspecialchars( $value );
	$value = trim( $value );

	// Create variable with same name as key ($title, $summary, etc.).
	extract( array( $setting => $value ) );

}

// Category.
if ($category && 'none' !== $category) {
	list( $category, $subcategory ) = explode( '|', $category );
} else {
	$category = '';
}

// Other podcast settings.
$owner_name = htmlspecialchars( get_bloginfo( 'name' ) ); // Owner name as site name.
$explicit = $not_explicit ? 'no' : 'yes'; // Explicit or not.

// Character set from WordPress settings.
$charset = get_option( 'blog_charset' );

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

		<?php if ($link) : ?>
			<link><?php echo esc_url( $link ); ?></link>
		<?php endif; ?>

		<language><?php echo esc_html( $language ); ?></language>

		<copyright><?php echo esc_html( $copyright ); ?></copyright>

		<itunes:subtitle><?php echo esc_html( $subtitle ); ?></itunes:subtitle>

		<itunes:author><?php echo esc_html( $author ); ?></itunes:author>
		<googleplay:author><?php echo esc_html( $author ); ?></googleplay:author>

		<?php if ($summary) : ?>
			<description><?php echo esc_html( $summary ); ?></description>
			<googleplay:description><?php echo esc_html( $summary ); ?></googleplay:description>
		<?php endif; ?>

		<?php if ($email) : ?>

			<itunes:owner>
				<itunes:name><?php echo esc_html( $owner_name ); ?></itunes:name>
				<itunes:email><?php echo esc_html( $email ); ?></itunes:email>
			</itunes:owner>

			<googleplay:owner><?php echo esc_html( $email ); ?></googleplay:owner>
			<googleplay:email><?php echo esc_html( $email ); ?></googleplay:email>

		<?php endif; ?>

		<?php if ($image) : ?>
			<itunes:image href="<?php echo esc_url( $image ); ?>"></itunes:image>
			<googleplay:image href="<?php echo esc_url( $image ); ?>"></googleplay:image>
		<?php endif; ?>

		<?php if ($category) : ?>

			<itunes:category text="<?php echo esc_html( $category ); ?>">

				<?php if ($subcategory) : ?>
					<itunes:category text="<?php echo esc_html( $subcategory ); ?>"/>
				<?php endif; ?>

			</itunes:category>

			<googleplay:category text="<?php echo esc_html( $category ); ?>"></googleplay:category>

		<?php endif; ?>

		<itunes:explicit><?php echo esc_html( $explicit ); ?></itunes:explicit>
		<googleplay:explicit><?php echo esc_html( $explicit ); ?></googleplay:explicit>

		<?php if ($new_url) : ?>
			<itunes:new-feed-url><?php echo esc_url( $new_url ); ?></itunes:new-feed-url>
		<?php endif; ?>

		<sy:updatePeriod><?php echo apply_filters( 'rss_update_period', 'hourly' ); // Core filter. ?></sy:updatePeriod>
		<sy:updateFrequency><?php echo apply_filters( 'rss_update_frequency', '1' ); // Core filter. ?></sy:updateFrequency>

		<lastBuildDate><?php // from core, standard practice.
			$date = get_lastpostmodified( 'GMT' );
			echo $date ? mysql2date( 'r', $date, false ) : date( 'r' );
		?></lastBuildDate>

		<?php

		do_action( 'rss2_head' ); // Core: Fires at the end of the RSS2 Feed Header (before items).

		while ( have_posts() ) {
			the_post();
			\CP_Library\Templates::get_template_part( "parts/podcast-item" );
		}

		?>

	</channel>

</rss>
