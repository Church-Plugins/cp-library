<?php // phpcs:ignore
/**
 * Class for the Elementor CP Library Template widget.
 *
 * @package CP_Library
 */

namespace CP_Library\Modules\Elementor\Template;

/**
 * Class Template
 */
class Module extends \Elementor\Widget_Base {
	/**
	 * Widget identifier
	 */
	public function get_name() {
		return 'cpl-template';
	}

	/**
	 * Widget title
	 */
	public function get_title() {
		return __( 'CP Library Template', 'cp-library' );
	}

	/**
	 * Widget icon
	 */
	public function get_icon() {
		return 'eicon-post-content';
	}
}
