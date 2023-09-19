<?php

namespace CP_Library\Admin;

use CP_Library\Admin\Settings\Podcast;
use CP_Library\Models\ServiceType;

/**
 * Plugin settings
 *
 */
class Settings {

	/**
	 * @var
	 */
	protected static $_instance;

	/**
	 * Only make one instance of \CP_Library\Settings
	 *
	 * @return Settings
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof Settings ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Get a value from the options table
	 *
	 * @param $key
	 * @param $default
	 * @param $group
	 *
	 * @return mixed|void
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public static function get( $key, $default = '', $group = 'cpl_main_options' ) {
		$options = get_option( $group, [] );

		if ( isset( $options[ $key ] ) ) {
			$value = $options[ $key ];
		} else {
			$value = $default;
		}

		return apply_filters( 'cpl_settings_get', $value, $key, $group );
	}

	/**
	 * Get advanced options
	 *
	 * @param $key
	 * @param $default
	 *
	 * @return mixed|void
	 * @since  1.0.0
	 * @updated 1.2.0 - handle default_menu_item
	 *
	 * @author Tanner Moushey
	 */
	public static function get_advanced( $key, $default = '' ) {
		// force item as the default menu item if item type is not enabled
		if ( 'default_menu_item' == $key ) {
			if ( ! cp_library()->setup->post_types->item_type_enabled() ) {
				return 'item';
			}
		}

		return self::get( $key, $default, 'cpl_advanced_options' );
	}

	public static function get_item( $key, $default = '' ) {
		return self::get( $key, $default, 'cpl_item_options' );
	}

	public static function get_item_type( $key, $default = '' ) {
		return self::get( $key, $default, 'cpl_item_type_options' );
	}

	public static function get_speaker( $key, $default = '' ) {
		return self::get( $key, $default, 'cpl_speaker_options' );
	}

	public static function get_service_type( $key, $default = '' ) {
		return self::get( $key, $default, 'cpl_service_type_options' );
	}

	/**
	 * Class constructor. Add admin hooks and actions
	 *
	 */
	protected function __construct() {
		add_action( 'cmb2_admin_init', [ $this, 'register_main_options_metabox' ] );
		add_action( 'cmb2_save_options_page_fields', 'flush_rewrite_rules' );
	}

	public function register_main_options_metabox() {

		$post_type = Settings::get_advanced( 'default_menu_item', 'item_type' ) === 'item_type' ? cp_library()->setup->post_types->item_type->post_type : cp_library()->setup->post_types->item->post_type;

		/**
		 * Registers main options page menu item and form.
		 */
		$args = array(
			'id'           => 'cpl_main_options_page',
			'title'        => 'Settings',
			'object_types' => array( 'options-page' ),
			'option_key'   => 'cpl_main_options',
			'tab_group'    => 'cpl_main_options',
			'tab_title'    => 'Main',
			'parent_slug'  => 'edit.php?post_type=' . $post_type,
			'display_cb'   => [ $this, 'options_display_with_tabs'],
		);

		$main_options = new_cmb2_box( $args );

		/**
		 * Options fields ids only need
		 * to be unique within this box.
		 * Prefix is not needed.
		 */
		$main_options->add_field( array(
			'name'    => __( 'Primary Color', 'cp-library' ),
			'desc'    => __( 'The primary color to use in the templates.', 'cp-library' ),
			'id'      => 'color_primary',
			'type'    => 'colorpicker',
			'default' => '#333333',
		) );

		$main_options->add_field( array(
			'name'         => __( 'Site Logo', 'cp-library' ),
			'desc'         => sprintf( __( 'The logo to use for %s.', 'cp-library' ), cp_library()->setup->post_types->item->plural_label ),
			'id'           => 'logo',
			'type'         => 'file',
			// query_args are passed to wp.media's library query.
			'query_args'   => array(
				// Or only allow gif, jpg, or png images
				 'type' => array(
				     'image/gif',
				     'image/jpeg',
				     'image/png',
				 ),
			),
			'preview_size' => 'thumbnail', // Image size to use when previewing in the admin
		) );

		$main_options->add_field( array(
			'name'         => __( 'Default Thumbnail', 'cp-library' ),
			'desc'         => sprintf( __( 'The default thumbnail image to use for %s.', 'cp-library' ), cp_library()->setup->post_types->item->plural_label ),
			'id'           => 'default_thumbnail',
			'type'         => 'file',
			// query_args are passed to wp.media's library query.
			'query_args'   => array(
				// Or only allow gif, jpg, or png images
				 'type' => array(
				     'image/gif',
				     'image/jpeg',
				     'image/png',
				 ),
			),
			'preview_size' => 'medium', // Image size to use when previewing in the admin
		) );

		$main_options->add_field( array(
			'name'         => __( 'Labels', 'cp-library' ),
			'id'           => 'label_title',
			'type'         => 'title',
		) );

		$main_options->add_field( array(
			'name'         => __( 'Play Video Button', 'cp-library' ),
			'desc'         => sprintf( __( 'The text to use on the play button.', 'cp-library' ), cp_library()->setup->post_types->item->plural_label ),
			'id'           => 'label_play_video',
			'type'         => 'text',
			'default'      => __( 'Watch', 'cp-library' ),
		) );

		$main_options->add_field( array(
			'name'         => __( 'Play Audio Button', 'cp-library' ),
			'desc'         => sprintf( __( 'The text to use on the play button.', 'cp-library' ), cp_library()->setup->post_types->item->plural_label ),
			'id'           => 'label_play_audio',
			'type'         => 'text',
			'default'      => __( 'Listen', 'cp-library' ),
		) );

		$this->item_options();

		if ( cp_library()->setup->post_types->item_type_enabled() ) {
			$this->item_type_options();
		}

		if ( cp_library()->setup->post_types->speaker_enabled() ) {
			$this->speaker_options();
		}

		if ( cp_library()->setup->post_types->service_type_enabled() ) {
			$this->service_type_options();
		}

		if ( cp_library()->setup->podcast->is_enabled() ) {
			Podcast::fields();
		}

		$this->advanced_options();
		$this->license_fields();

	}

	protected function license_fields() {
		$license = new \ChurchPlugins\Setup\Admin\License( 'cpl_license', 436, CP_LIBRARY_STORE_URL, CP_LIBRARY_PLUGIN_FILE, get_admin_url( null, 'admin.php?page=cpl_license' ) );

		/**
		 * Registers settings page, and set main item as parent.
		 */
		$args = array(
			'id'           => 'cpl_options_page',
			'title'        => 'CP Library Settings',
			'object_types' => array( 'options-page' ),
			'option_key'   => 'cpl_license',
			'parent_slug'  => 'cpl_main_options',
			'tab_group'    => 'cpl_main_options',
			'tab_title'    => 'License',
			'display_cb'   => [ $this, 'options_display_with_tabs' ]
		);

		$options = new_cmb2_box( $args );
		$license->license_field( $options );
	}


	protected function item_options() {
		/**
		 * Registers secondary options page, and set main item as parent.
		 */
		$args = array(
			'id'           => 'cpl_item_options_page',
			'title'        => 'Settings',
			'object_types' => array( 'options-page' ),
			'option_key'   => 'cpl_item_options',
			'parent_slug'  => 'cpl_main_options',
			'tab_group'    => 'cpl_main_options',
			'tab_title'    => cp_library()->setup->post_types->item->plural_label,
			'display_cb'   => [ $this, 'options_display_with_tabs' ],
		);

		$options = new_cmb2_box( $args );

		$options->add_field( array(
			'name' => __( 'Labels' ),
			'id'   => 'labels',
			'type' => 'title',
		) );

		$options->add_field( array(
			'name'    => __( 'Singular Label', 'cp-library' ),
			'id'      => 'singular_label',
			'type'    => 'text',
			'default' => cp_library()->setup->post_types->item->single_label,
		) );

		$options->add_field( array(
			'name'    => __( 'Plural Label', 'cp-library' ),
			'id'      => 'plural_label',
			'type'    => 'text',
			'default' => cp_library()->setup->post_types->item->plural_label,
		) );

		$options->add_field( array(
			'name'    => __( 'Slug', 'cp-library' ),
			'id'      => 'slug',
			'desc'    => __( 'Caution: changing this value will also adjust the url structure and may affect your SEO.', 'cp-library' ),
			'type'    => 'text',
			'default' => strtolower( sanitize_title( cp_library()->setup->post_types->item->plural_label ) ),
		) );

		$options->add_field( array(
			'name' => __( 'Template Options', 'cp-library' ),
			'id'   => 'template_title',
			'type' => 'title',
		) );

		$template_items = [
			'date'      => __( 'Publish Date', 'cp-library' ),
			'topics'    => __( 'Topics', 'cp-library' ),
			'scripture' => __( 'Scripture', 'cp-library' ),
		];

		if ( cp_library()->setup->post_types->speaker_enabled() ) {
			$template_items['speakers'] = cp_library()->setup->post_types->speaker->plural_label;
		}

		if ( cp_library()->setup->post_types->item_type_enabled() ) {
			$template_items['types'] = cp_library()->setup->post_types->item_type->plural_label;
		}

		if ( cp_library()->setup->post_types->service_type_enabled() ) {
			$template_items['service_type'] = cp_library()->setup->post_types->service_type->plural_label;
		}

		$template_items = apply_filters( 'cp_library_template_items', $template_items );

		$options->add_field( [
			'name' => __( 'Info Items', 'cp-library' ),
			'desc' => __( 'The items to show under the title on the single view and list view.', 'cp-library' ),
			'id'   => 'info_items',
			'type' => 'pw_multiselect',
			'options' => $template_items,
			'default' => [ 'speakers', 'locations', 'types' ]
		] );

		$options->add_field( [
			'name' => __( 'Meta Items', 'cp-library' ),
			'desc' => __( 'The items to show above the title on the single view and at the bottom of the card in the list view.', 'cp-library' ),
			'id'   => 'meta_items',
			'type' => 'pw_multiselect',
			'options' => $template_items,
			'default' => [ 'date', 'topics', 'scripture' ]
		] );

		$variation_sources = cp_library()->setup->variations->get_sources();
		$desc              = __( 'Use this section to control the sermon variation functionality. Variations allows you to create multiple versions of a sermon with different speakers, media, etc. This is ideal for churches that deliver the same message from multiple locations each Sunday.', 'cp-library' );

		if ( empty( $variation_sources ) ) {
			$desc .= '<br /><br />' . __( 'To enable Variations, activate Service Types or a supported add-on.', 'cp-library' );
		}

		$options->add_field( array(
			'name' => __( 'Variations', 'cp-library' ),
			'id'   => 'variations_title',
			'desc' => $desc,
			'type' => 'title',
		) );

		if ( empty( $variation_sources ) ) {
			return;
		}

		$options->add_field( array(
			'name'    => __( 'Enable Variations (beta)', 'cp-library' ),
			'id'      => 'variations_enabled',
			'type'    => 'radio_inline',
			'default' => 0,
			'options' => [
				1 => __( 'Enable', 'cp-library' ),
				0 => __( 'Disable', 'cp-library' ),
			]
		) );

		$options->add_field( array(
			'name' => __( 'Variation Source', 'cp-library' ),
			'id'   => 'variation_source',
			'type' => 'select',
			'desc' => __( 'The Variation Source is used to define the different sermons variations.', 'cp-library' ),
			'options' => $variation_sources,
		) );

	}

	protected function item_type_options() {
		/**
		 * Registers secondary options page, and set main item as parent.
		 */
		$args = array(
			'id'           => 'cpl_item_type_options_page',
			'title'        => 'Settings',
			'object_types' => array( 'options-page' ),
			'option_key'   => 'cpl_item_type_options',
			'parent_slug'  => 'cpl_main_options',
			'tab_group'    => 'cpl_main_options',
			'tab_title'    => cp_library()->setup->post_types->item_type->plural_label,
			'display_cb'   => [ $this, 'options_display_with_tabs' ],
		);

		$options = new_cmb2_box( $args );

		$options->add_field( array(
			'name' => __( 'Labels' ),
			'id'   => 'labels',
			'type' => 'title',
		) );

		$options->add_field( array(
			'name'    => __( 'Singular Label', 'cp-library' ),
			'id'      => 'singular_label',
			'type'    => 'text',
			'default' => cp_library()->setup->post_types->item_type->single_label,
		) );

		$options->add_field( array(
			'name'    => __( 'Plural Label', 'cp-library' ),
			'id'      => 'plural_label',
			'type'    => 'text',
			'default' => cp_library()->setup->post_types->item_type->plural_label,
		) );

		$options->add_field( array(
			'name'    => __( 'Slug', 'cp-library' ),
			'id'      => 'slug',
			'desc'    => __( 'Caution: changing this value will also adjust the url structure and may affect your SEO.', 'cp-library' ),
			'type'    => 'text',
			'default' => strtolower( sanitize_title( cp_library()->setup->post_types->item_type->plural_label ) ),
		) );

	}

	protected function speaker_options() {
		/**
		 * Registers secondary options page, and set main item as parent.
		 */
		$args = array(
			'id'           => 'cpl_speaker_options_page',
			'title'        => 'Settings',
			'object_types' => array( 'options-page' ),
			'option_key'   => 'cpl_speaker_options',
			'parent_slug'  => 'cpl_main_options',
			'tab_group'    => 'cpl_main_options',
			'tab_title'    => cp_library()->setup->post_types->speaker->plural_label,
			'display_cb'   => [ $this, 'options_display_with_tabs' ],
		);

		$options = new_cmb2_box( $args );

		$options->add_field( array(
			'name' => __( 'Labels' ),
			'id'   => 'labels',
			'type' => 'title',
		) );

		$options->add_field( array(
			'name'    => __( 'Singular Label', 'cp-library' ),
			'id'      => 'singular_label',
			'type'    => 'text',
			'default' => cp_library()->setup->post_types->speaker->single_label,
		) );

		$options->add_field( array(
			'name'    => __( 'Plural Label', 'cp-library' ),
			'desc'    => __( 'Caution: changing this value will also adjust the url structure and may affect your SEO.', 'cp-library' ),
			'id'      => 'plural_label',
			'type'    => 'text',
			'default' => cp_library()->setup->post_types->speaker->plural_label,
		) );

	}

	protected function service_type_options() {
		/**
		 * Registers secondary options page, and set main item as parent.
		 */
		$args = array(
			'id'           => 'cpl_service_type_options_page',
			'title'        => 'Settings',
			'object_types' => array( 'options-page' ),
			'option_key'   => 'cpl_service_type_options',
			'parent_slug'  => 'cpl_main_options',
			'tab_group'    => 'cpl_main_options',
			'tab_title'    => cp_library()->setup->post_types->service_type->plural_label,
			'display_cb'   => [ $this, 'options_display_with_tabs' ],
		);

		$options = new_cmb2_box( $args );

		$options->add_field( array(
			'name' => __( 'Labels' ),
			'id'   => 'labels',
			'type' => 'title',
		) );

		$options->add_field( array(
			'name'    => __( 'Singular Label', 'cp-library' ),
			'id'      => 'singular_label',
			'type'    => 'text',
			'default' => cp_library()->setup->post_types->service_type->single_label,
		) );

		$options->add_field( array(
			'name'    => __( 'Plural Label', 'cp-library' ),
			'id'      => 'plural_label',
			'type'    => 'text',
			'default' => cp_library()->setup->post_types->service_type->plural_label,
		) );

		$service_types = ServiceType::get_all_service_types();

		if ( empty( $service_types ) ) {
			$options->add_field( [
				'desc' => sprintf( __( 'No %s have been created yet. <a href="%s">Create one here.</a>', 'cp-library' ), cp_library()->setup->post_types->service_type->plural_label, add_query_arg( [ 'post_type' => cp_library()->setup->post_types->service_type->post_type ], admin_url( 'post-new.php' ) )  ),
				'type' => 'title',
				'id' => 'cpl_no_service_types',
			] );
		} else {
			$service_types = array_combine( wp_list_pluck( $service_types, 'id' ), wp_list_pluck( $service_types, 'title' ) );

			$options->add_field( array(
				'name'             => __( 'Default Service Type', 'cp-library' ),
				'id'               => 'default_service_type',
				'type'             => 'select',
				'show_option_none' => true,
				'options'          => $service_types,
			) );
		}

	}

	protected function advanced_options() {
		/**
		 * Registers secondary options page, and set main item as parent.
		 */
		$args = array(
			'id'           => 'cpl_advanced_options_page',
			'title'        => 'Settings',
			'object_types' => array( 'options-page' ),
			'option_key'   => 'cpl_advanced_options',
			'parent_slug'  => 'cpl_main_options',
			'tab_group'    => 'cpl_main_options',
			'tab_title'    => 'Advanced',
			'display_cb'   => [ $this, 'options_display_with_tabs' ],
		);

		$advanced_options = new_cmb2_box( $args );

		$advanced_options->add_field( array(
			'name' => __( 'Modules' ),
			'id'   => 'modules_enabled',
			'type' => 'title',
		) );

		$advanced_options->add_field( array(
			'name'    => __( 'Enable' ) . ' ' . cp_library()->setup->post_types->item_type->plural_label,
			'id'      => 'item_type_enabled',
			'type'    => 'radio_inline',
			'default' => 1,
			'options' => [
				1 => __( 'Enable', 'cp-library' ),
				0 => __( 'Disable', 'cp-library' ),
			]
		) );

		$advanced_options->add_field( array(
			'name'    => __( 'Enable' ) . ' ' . cp_library()->setup->post_types->speaker->plural_label,
			'id'      => 'speaker_enabled',
			'type'    => 'radio_inline',
			'default' => 1,
			'options' => [
				1 => __( 'Enable', 'cp-library' ),
				0 => __( 'Disable', 'cp-library' ),
			]
		) );

		$advanced_options->add_field( array(
			'name'    => __( 'Enable' ) . ' ' . cp_library()->setup->post_types->service_type->plural_label,
			'id'      => 'service_type_enabled',
			'type'    => 'radio_inline',
			'default' => 0,
			'options' => [
				1 => __( 'Enable', 'cp-library' ),
				0 => __( 'Disable', 'cp-library' ),
			]
		) );

		$advanced_options->add_field( array(
			'name'    => __( 'Enable Podcast Feed' ),
			'id'      => 'podcast_feed_enable',
			'type'    => 'radio_inline',
			'default' => 0,
			'options' => [
				1 => __( 'Enable', 'cp-library' ),
				0 => __( 'Disable', 'cp-library' ),
			]
		) );

		if ( cp_library()->setup->post_types->item_type_enabled() ) {

			// @todo move this out of conditional once we add more settings
			$advanced_options->add_field( array(
				'name' => __( 'Settings' ),
				'id'   => 'settings',
				'type' => 'title',
			) );

			$advanced_options->add_field( [
				'name'    => __( 'Set default menu item', 'cp-library' ),
				'id'      => 'default_menu_item',
				'type'    => 'select',
				'options' => [
					'item'      => cp_library()->setup->post_types->item->plural_label,
					'item_type' => cp_library()->setup->post_types->item_type->plural_label,
				],
				'default' => 'item_type',
				'desc'    => sprintf( __( 'Select which object to use for the Admin menu item.', 'cp-library' ), cp_library()->setup->post_types->item_type->plural_label, cp_library()->setup->post_types->item->plural_label ),
			] );
		}

		$advanced_options->add_field(
			array(
				'name' => __( 'Filters', 'cp-library' ),
				'id'   => 'filters',
				'type' => 'title',
			)
		);

		$advanced_options->add_field(
			array(
				'name'    => __( 'Count Threshold', 'cp-library' ),
				'id'      => 'filter_count_threshold',
				/* translators: %s is the plural label for the item post type */
				'desc'    => sprintf( __( 'The minimum number of %s to show a filter field for.', 'cp-library' ), cp_library()->setup->post_types->item->plural_label ),
				'type'    => 'text_small',
				'default' => 3,
			)
		);

		$taxonomies = cp_library()->setup->taxonomies->get_objects();

		foreach ( $taxonomies as $taxonomy ) {
			$advanced_options->add_field(
				array(
					/* translators: %s is the single label for the taxonomy */
					'name'    => sprintf( __( 'Sort %s', 'cp-library' ), $taxonomy->single_label ),
					'id'      => 'sort_' . $taxonomy->taxonomy,
					'type'    => 'select',
					'options' => array(
						/* translators: %s is the plural label for the item post type */
						'count' => sprintf( __( 'By %s Count', 'cp-library' ), cp_library()->setup->post_types->item->single_label ),
						'name'  => __( 'Alphabetically', 'cp-library' ),
					),
				)
			);
		}

		$sources = array(
			'speaker'      => cp_library()->setup->post_types->speaker,
			'service_type' => cp_library()->setup->post_types->service_type
		);

		foreach ( $sources as $key => $source ) {
			$advanced_options->add_field(
				array(
					/* translators: %s is the single label for the source post type */
					'name'    => sprintf( __( 'Sort %s', 'cp-library' ), $source->single_label ),
					'id'      => "sort_{$key}",
					'type'    => 'select',
					'options' => array(
						/* translators: %s is the plural label for the item post type */
						'count' => sprintf( __( 'By %s Count', 'cp-library' ), cp_library()->setup->post_types->item->single_label ),
						'name'  => __( 'Alphabetically', 'cp-library' ),
					),
				)
			);
		}
	}

	/**
	 * A CMB2 options-page display callback override which adds tab navigation among
	 * CMB2 options pages which share this same display callback.
	 *
	 * @param \CMB2_Options_Hookup $cmb_options The CMB2_Options_Hookup object.
	 */
	public function options_display_with_tabs( $cmb_options ) {
		$tabs = $this->options_page_tabs( $cmb_options );
		?>
		<div class="wrap cmb2-options-page option-<?php echo $cmb_options->option_key; ?>">
			<?php if ( get_admin_page_title() ) : ?>
				<h2><?php echo wp_kses_post( get_admin_page_title() ); ?></h2>
			<?php endif; ?>
			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $option_key => $tab_title ) : ?>
					<a class="nav-tab<?php if ( isset( $_GET['page'] ) && $option_key === $_GET['page'] ) : ?> nav-tab-active<?php endif; ?>"
					   href="<?php menu_page_url( $option_key ); ?>"><?php echo wp_kses_post( $tab_title ); ?></a>
				<?php endforeach; ?>
			</h2>
			<form class="cmb-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST"
				  id="<?php echo $cmb_options->cmb->cmb_id; ?>" enctype="multipart/form-data"
				  encoding="multipart/form-data">
				<input type="hidden" name="action" value="<?php echo esc_attr( $cmb_options->option_key ); ?>">
				<?php $cmb_options->options_page_metabox(); ?>
				<?php submit_button( esc_attr( $cmb_options->cmb->prop( 'save_button' ) ), 'primary', 'submit-cmb' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Gets navigation tabs array for CMB2 options pages which share the given
	 * display_cb param.
	 *
	 * @param \CMB2_Options_Hookup $cmb_options The CMB2_Options_Hookup object.
	 *
	 * @return array Array of tab information.
	 */
	public function options_page_tabs( $cmb_options ) {
		$tab_group = $cmb_options->cmb->prop( 'tab_group' );
		$tabs      = array();

		foreach ( \CMB2_Boxes::get_all() as $cmb_id => $cmb ) {
			if ( $tab_group === $cmb->prop( 'tab_group' ) ) {
				$tabs[ $cmb->options_page_keys()[0] ] = $cmb->prop( 'tab_title' )
					? $cmb->prop( 'tab_title' )
					: $cmb->prop( 'title' );
			}
		}

		return $tabs;
	}


}
