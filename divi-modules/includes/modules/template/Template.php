<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName
/**
 * Class for the Divi CP Library Template module.
 *
 * @package CP_Library
 */

/**
 * CP Library Template module
 */
class CP_Library_Template_Divi_Module extends ET_Builder_Module {

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
		$this->name = esc_html__( 'CP Library Template', 'cp-library' );
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
		return 'Hello, divi midule';
	}

	/**
	 * Get fields for this module
	 *
	 * @return array
	 */
	public function get_fields() {
		return array(
			'templateId' => array(
				'label'   => esc_html__( 'Template', 'cp-library' ),
				'type'    => 'select',
				'options' => cp_library()->setup->post_types->template->get_shortcode_templates(),
				'default' => 0,
			),
		);
	}

}

new CP_Library_Template_Divi_Module();
