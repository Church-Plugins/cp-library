<?php
/**
 * Dynanmic Template
 *
 * This template is used to display the content of a Template post type.
 *
 * @package CP_Library
 */

$template = \CP_Library\Setup\PostTypes\Template::get_current_template();

$default_template = \CP_Library\Templates::get_template_hierarchy( 'default-template' );



echo apply_filters( 'the_content', $template->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
