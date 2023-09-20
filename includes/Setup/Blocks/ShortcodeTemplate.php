<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Shortcode Template block
 *
 * Displays a Template createed with the CP Library template builder.
 *
 * @package CP_Library
 */

namespace CP_Library\Setup\Blocks;

/**
 * Shortcode Template block class
 */
class ShortcodeTemplate extends Block {
	/**
	 * The block name.
	 *
	 * @var string
	 */
	public $name = 'template';

	/**
	 * Whether or not the block is dynamic.
	 *
	 * @var bool
	 */
	public $is_dynamic = true;

	/**
	 * Renders the `cp-library/template` block on the server.
	 *
	 * @param array     $attributes Block attributes.
	 * @param string    $content    Block default content.
	 * @param \WP_Block $block      Block instance.
	 * @return string Returns the shortcode for displaying the Template.
	 */
	public function render( $attributes, $content, $block ) {
		if ( 0 == $attributes['templateId'] ) {
			return '';
		}

		return sprintf( '[%s id=%d]', cp_library()->setup->post_types->template->shortcode_slug, absint( $attributes['templateId'] ) );
	}
}
