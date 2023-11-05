<?php
/**
 * Default CPL Content Template
 *
 * Override this template in your own theme by creating a file at [your-theme]/cp-library/default-template.php
 *
 * @package cp-library
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

/**
 * Allows filtering the classes for the main element.
 *
 * @param array<string> $classes An (unindexed) array of classes to apply.
 */
$classes = apply_filters( 'cpl_default_template_classes', [ 'cpl-pg-template', 'cp-pg-template' ] );

get_header();

/**
 * Provides an action that allows for the injection of HTML at the top of the template after the header.
 */
do_action( 'cpl_default_template_after_header' );
?>
<main id="cpl-pg-template" class="<?php echo implode( ' ', $classes ); ?>">
	<?php echo apply_filters( 'cpl_default_template_before_content', '' ); ?>
	<?php \CP_Library\Templates::get_view(); ?>
	<?php echo apply_filters( 'cpl_default_template_after_content', '' ); ?>
</main> <!-- #cpl-pg-template -->
<?php

/**
 * Provides an action that allows for the injections of HTML at the bottom of the template before the footer.
 */
do_action( 'cpl_default_template_before_footer' );

get_footer();
