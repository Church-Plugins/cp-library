<?php
/**
 * Render the template in the Beaver Builder editor
 *
 * @package CP_Library
 */

$template_id = $settings->templateId; // phpcs:ignore

if ( 0 != $template_id ) {
	echo sprintf( '[%s id=%d]', cp_library()->setup->post_types->template->shortcode_slug, absint( $template_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
} else {
	echo esc_html__( 'Please select a template', 'cp-library' );
}

