<?php
/**
 * Custom Podcast Meta Fields
 *
 * Note: Not relying on DOMDocument to avoid issue in rare case that is not available.
 *
 * @since   1.4
 * @package CP_Library
 */

// No direct access.
if ( ! defined( 'ABSPATH' ) ) {
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
foreach ( $settings as $setting => $default ) {

	// Get setting value.
	$value = Podcast::get( $setting, $default );

	// Make XML-safe and trim.
	$value = htmlspecialchars( $value );
	$value = trim( $value );

	// Create variable with same name as key ($title, $summary, etc.).
	extract( array( $setting => $value ) );

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
$charset = get_option( 'blog_charset' );


?>

<copyright><?php echo esc_html( $copyright ); ?></copyright>

<itunes:subtitle><?php echo esc_html( $subtitle ); ?></itunes:subtitle>

<itunes:author><?php echo esc_html( $author ); ?></itunes:author>
<googleplay:author><?php echo esc_html( $author ); ?></googleplay:author>

<?php if ( $summary ) : ?>
	<description><?php echo esc_html( $summary ); ?></description>
	<googleplay:description><?php echo esc_html( $summary ); ?></googleplay:description>
<?php endif; ?>

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
