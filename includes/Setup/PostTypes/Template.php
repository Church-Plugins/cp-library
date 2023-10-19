<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName

namespace CP_Library\Setup\PostTypes;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ChurchPlugins\Setup\PostTypes\PostType;
use WP_Block_Editor_Context;
use WP_Post;

/**
 * Setup for custom post type: Template
 *
 * @since 1.3
 */
class Template extends PostType {

	/**
	 * The shortcode name
	 *
	 * @var string
	 */
	public static $shortcode_slug;

	/**
	 * Child class constructor
	 */
	protected function __construct() {
		$this->post_type = CP_LIBRARY_UPREFIX . '_template';

		$this->single_label = apply_filters( "cploc_single_{$this->post_type}_label", 'Template' );
		$this->plural_label = apply_filters( "cploc_plural_{$this->post_type}_label", 'Templates' );

		self::$shortcode_slug = CP_LIBRARY_UPREFIX . '_template';

		parent::__construct();

		// the model for this class is not compatible with CP core.
		$this->model = false;
	}

	/**
	 * Initializes actions and filters
	 */
	public function add_actions() {
		add_action( 'add_meta_boxes', array( $this, 'meta_boxes' ) );
		add_shortcode( self::$shortcode_slug, array( $this, 'display' ) );
		add_filter( 'allowed_block_types_all', array( $this, 'allowed_block_types' ), 10, 2 );
		add_filter( 'default_content', array( $this, 'populate_content' ), 10, 2 );
		add_filter( "{$this->post_type}_show_in_menu", array( $this, 'show_in_submenu' ) );
		add_filter( "{$this->post_type}_slug", array( $this, 'custom_slug' ) );
		add_action( 'wp_ajax_cpl_render_template', array( $this, 'render_ajax_content' ) );
		add_filter( 'block_editor_settings_all', array( $this, 'block_editor_settings' ), 10, 2 );
		parent::add_actions();
	}

	/**
	 * Create CMB2 metaboxes
	 */
	public function register_metaboxes() {}

	/**
	 * Adds metaboxes for this CPT
	 */
	public function meta_boxes() {
		add_meta_box( 'shortcode', 'Shortcode', array( $this, 'shortcode_meta_box' ), $this->post_type, 'side', 'high' );
	}

	/**
	 * Displays a metabox for copying the shortcode in the block editor
	 *
	 * @param \WP_Post $post The post object.
	 */
	public function shortcode_meta_box( $post ) {
		$slug      = self::$shortcode_slug;
		$shortcode = "[{$slug} id={$post->ID}]";
		?>
		<input type='text' disabled value="<?php echo esc_attr( $shortcode ); ?>">
		<button class="button" onclick="navigator.clipboard.writeText('<?php echo esc_attr( $shortcode ); ?>')"><?php echo esc_html_e( 'Copy shortcode', 'cp-library' ); ?></button>
		<?php
	}

	/**
	 * Displays Template content when used via a shortcode
	 *
	 * @param array       $atts Shortcode attributes.
	 * @param string|null $content Shortcode content.
	 * @param string      $shortcode_tag Shortcode tag.
	 * @return string
	 */
	public function display( $atts, $content, $shortcode_tag ) {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			$atts,
			$shortcode_tag
		);

		return self::render_content( $atts['id'] );
	}

	/**
	 * Specifies Gutenberg blocks allowed when editing a Template with the block editor
	 *
	 * @param bool|string[]            $allowed Array of allowed block types.
	 * @param \WP_Block_Editor_Context $context The block editor context.
	 * @return bool|string[] Array of allowed block types.
	 */
	public function allowed_block_types( $allowed, $context ) {
		if ( $context->post && $context->post->post_type === $this->post_type ) {
			return apply_filters(
				'cp_library_shortcodes_block_types',
				array(
					'core/spacer',
					'core/group',
					'core/columns',
					'core/column',
					'core/query-pagination',
					'core/query-pagination-next',
					'core/query-pagination-numbers',
					'core/query-pagination-previous',
					'cp-library/item-date',
					'cp-library/item-description',
					'cp-library/item-title',
					'cp-library/item-graphic',
					'cp-library/pagination',
					'cp-library/sermon-template',
					'cp-library/sermon-speaker',
					'cp-library/sermon-actions',
					'cp-library/sermon-series',
					'cp-library/sermon-scripture',
					'cp-library/sermon-location',
					'cp-library/sermon-season',
					'cp-library/sermon-topics',
					'cp-library/query',
					'core/post-title',
					'core/paragraph',
					'core/heading',
				)
			);
		}

		return $allowed;
	}

	/**
	 * Populates content when creating a new Template
	 *
	 * @param string   $content The default post content.
	 * @param \WP_Post $post The post object.
	 * @return string The default post content.
	 */
	public function populate_content( $content, $post ) {
		if ( $post->post_type !== $this->post_type ) {
			return $content;
		}
		$html_file = trailingslashit( CP_LIBRARY_PLUGIN_DIR ) . 'templates/default_content/shortcode-content.html';
		return file_get_contents( $html_file );
	}

	/**
	 * Displays menu item in Series submenu instead of as a seperate menu item
	 *
	 * @return string the submenu in which to display
	 */
	public function show_in_submenu() {
		return 'edit.php?post_type=cpl_item_type';
	}

	/**
	 * Custom rewrite to prevent clashing with other plugins
	 *
	 * @param string $slug The default slug.
	 * @return string
	 */
	public function custom_slug( $slug ) {
		return 'cp_library_template';
	}

	/**
	 * Returns an array of templates formatted id => title for use in custom page builder blocks.
	 *
	 * @param bool $add_default Whether or not to add a default option.
	 * @return array
	 */
	public function get_shortcode_templates( bool $add_default = true ) {
		$query = new \WP_Query(
			array(
				'post_type'      => $this->post_type,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
			)
		);
		wp_reset_postdata();

		$templates = array();

		if ( $add_default ) {
			$templates[0] = __( 'Select a Template', 'cp-library' );
		}

		foreach ( $query->posts as $post ) {
			$templates[ $post->ID ] = $post->post_title;
		}

		return $templates;
	}

	/**
	 * Render the template content for a given template id.
	 *
	 * @param mixed $template_id The template id.
	 */
	public static function render_content( $template_id = 0 ) {
		$template_id = absint( $template_id );

		if ( 0 === $template_id ) {
			return esc_html__( 'Invalid template id', 'cp-library' );
		}

		$template = get_post( $template_id );

		if ( ! $template ) {
			return esc_html__( 'Template not found', 'cp-library' );
		}

		$content = $template->post_content;

		$content = shortcode_unautop( $content );
		$content = do_shortcode( $content );
		$content = do_blocks( $content );
		$content = wptexturize( $content );
		$content = convert_smilies( $content );
		$content = wp_filter_content_tags( $content, 'template' );
		$content = str_replace( ']]>', ']]&gt;', $content );

		return '<div class="wp-site-blocks">' . $content . '</div>';
	}

	/**
	 * Handles an ajax request
	 */
	public function render_ajax_content() {
		$template_id = isset( $_GET['templateId'] ) ? absint( $_GET['templateId'] ) : 0;
		echo self::render_content( $template_id ); // phpcs:ignore WordPress.Security.EscapeOutput
		wp_die();
	}

	/**
	 * Adds custom block editor settings
	 *
	 * @param array                    $editor_settings The block editor settings.
	 * @param \WP_Block_Editor_Context $block_editor_context The block editor context.
	 */
	public function block_editor_settings( $editor_settings, $block_editor_context ) {
		if ( ! $block_editor_context->post || $block_editor_context->post->post_type !== $this->post_type ) {
			return $editor_settings;
		}

		if ( ! isset( $editor_settings['__experimentalFeatures']['spacing']['padding'] ) ) {
			$editor_settings['__experimentalFeatures']['spacing']['padding'] = true;
		}

		if ( ! isset( $editor_settings['__experimentalFeatures']['spacing']['margin'] ) ) {
			$editor_settings['__experimentalFeatures']['spacing']['margin'] = true;
		}

		if ( ! isset( $editor_settings['__experimentalFeatures']['spacing']['blockGap'] ) ) {
			$editor_settings['__experimentalFeatures']['spacing']['blockGap'] = true;
		}

		if ( ! isset( $editor_settings['__experimentalFeatures']['border']['color'] ) ) {
			$editor_settings['__experimentalFeatures']['border']['color'] = true;
		}

		if ( ! isset( $editor_settings['__experimentalFeatures']['border']['radius'] ) ) {
			$editor_settings['__experimentalFeatures']['border']['radius'] = true;
		}

		if ( ! isset( $editor_settings['__experimentalFeatures']['border']['style'] ) ) {
			$editor_settings['__experimentalFeatures']['border']['style'] = true;
		}

		if ( ! isset( $editor_settings['__experimentalFeatures']['border']['width'] ) ) {
			$editor_settings['__experimentalFeatures']['border']['width'] = true;
		}

		return $editor_settings;
	}
}
