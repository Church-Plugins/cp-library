<?php
/**
 * Render the template in the Beaver Builder editor
 *
 * @package CP_Library
 */

$template_id = absint( $settings->templateId ); // phpcs:ignore

if ( 0 !== $template_id ) {
	echo \CP_Library\Setup\PostTypes\Template::render_content( $template_id ); // phpcs:ignore WordPress.Security.EscapeOutput
} else {
	echo esc_html__( 'Please select a template', 'cp-library' );
}

