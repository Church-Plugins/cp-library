<?php // phpcs:ignore
/**
 * Class for the Elementor CP Sermons Template widget.
 *
 * @package CP_Library
 */

namespace CP_Library\Modules\Elementor\Template;

use \CP_Library\Setup\PostTypes\Template as Template_Post_Type;

/**
 * Class for the Elementor CP Sermons Template widget.
 */
class Module extends \Elementor\Widget_Base {

	/**
	 * Get widget name.
	 */
	public function get_name() {
		return 'cpl_template';
	}

	/**
	 * Widget title
	 */
	public function get_title() {
		return esc_html__( 'CP Sermons Template', 'elementor-addon' );
	}

	/**
	 * Widget icon.
	 */
	public function get_icon() {
		return 'eicon-layout-settings';
	}

	/**
	 * Widget categories.
	 */
	public function get_categories() {
		return array( 'cp-library' );
	}

	/**
	 * Widget keywords.
	 */
	public function get_keywords() {
		return array( 'template', 'cpl', 'cp-library' );
	}

	/**
	 * Render the widget
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		if ( 0 == $settings['templateId'] ) {
			esc_html_e( 'No Template Selected', 'cp-library' );
			return;
		}

		echo Template_Post_Type::render_content( absint( $settings['templateId'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * Register widet controls
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'template',
			array(
				'label' => __( 'Template', 'cp-library' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'templateId',
			array(
				'label'   => __( 'Choose a Template', 'cp-library' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => cp_library()->setup->post_types->template->get_shortcode_templates(),
				'default' => 0,
			)
		);

		$this->end_controls_section();
	}
}
