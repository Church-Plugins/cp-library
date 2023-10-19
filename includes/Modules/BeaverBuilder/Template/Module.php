<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName
/**
 * Template module class
 *
 * @package CP_Library
 */

namespace CP_Library\Modules\BeaverBuilder\Template;

/**
 * Template module class
 */
class Module extends \FLBuilderModule {
	/**
	 * Class constructor
	 */
	public function __construct() {
		parent::__construct(
			array(
				'name'            => __( 'CP Library Template', 'cp-library' ),
				'description'     => __( 'Displays a Template created with the CP Library template builder.', 'cp-library' ),
				'group'           => __( 'CP Library', 'cp-library' ),
				'category'        => __( 'Content', 'cp-library' ),
				'dir'             => CP_LIBRARY_FL_MODULES_DIR . 'Template/',
				'url'             => CP_LIBRARY_FL_MODULES_URL . 'Template/',
				'icon'            => 'layout.svg',
				'editor_export'   => true,
				'enabled'         => true,
				'partial_refresh' => false,
			)
		);
	}
}

/**
 * Register the module and its form settings.
 */
\FLBuilder::register_module(
	Module::class,
	array(
		'general' => array(
			'title'    => __( 'General', 'cp-library' ),
			'sections' => array(
				'template' => array(
					'title'  => __( 'Template', 'cp-library' ),
					'fields' => array(
						'templateId' => array(
							'type'    => 'select',
							'label'   => __( 'Choose a Template', 'cp-library' ),
							'options' => cp_library()->setup->post_types->template->get_shortcode_templates(),
							'default' => 0,
						),
					),
				),
			),
		),
	)
);
