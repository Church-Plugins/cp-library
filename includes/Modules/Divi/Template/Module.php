<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName
/**
 * Class for the Divi CP Sermons Template module.
 *
 * @package CP_Library
 */

namespace CP_Library\Modules\Divi\Template;

/**
 * CP Sermons Template module
 */
class Module extends \ET_Builder_Module {

	/**
	 * Module properties initialization
	 *
	 * @var string
	 */
	public $vb_support = 'on';

	/**
	 * Module properties initialization
	 *
	 * @return void
	 */
	public function init() {
		$this->name = esc_html__( 'CP Sermons Template', 'cp-library' );
		$this->slug = 'cpl_template';
	}

	/**
	 * Render the module
	 *
	 * @param array       $attrs The module attributes.
	 * @param string|null $content The module content.
	 * @param string|null $render_slug The module slug.
	 */
	public function render( $attrs, $content = null, $render_slug = null ) {
		$template_id = absint( $attrs['template_id'] ?? 0 );
		if ( 0 === $template_id ) {
			return '';
		}
		return \CP_Library\Setup\PostTypes\Template::render_content( $template_id );
	}

	/**
	 * Get fields for this module
	 *
	 * @return array
	 */
	public function get_fields() {
		return array(
			'template_id' => array(
				'label'   => esc_html__( 'Template', 'cp-library' ),
				'type'    => 'select',
				'options' => cp_library()->setup->post_types->template->get_shortcode_templates(),
				'default' => 0,
			),
		);
	}

}
