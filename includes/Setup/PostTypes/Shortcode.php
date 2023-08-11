<?php
namespace CP_Library\Setup\PostTypes;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

use CP_Library\Admin\Settings;
use ChurchPlugins\Setup\PostTypes\PostType;
use WP_Block_Editor_Context;
use WP_Post;

/**
 * Setup for custom post type: Shortcode
 *
 * @since 1.0
 */
class Shortcode extends PostType {

	/**
	 * Child class constructor
	 */
	protected function __construct() {
		$this->post_type = CP_LIBRARY_UPREFIX . "_shortcode";

		$this->single_label = apply_filters( "cploc_single_{$this->post_type}_label", 'Shortcode'  );
		$this->plural_label = apply_filters( "cploc_plural_{$this->post_type}_label", 'Shortcodes' );

		parent::__construct();

		// the model for this class is not compatible with CP core
		$this->model = false;
	}

	/**
	 * Initializes actions and filters
	 */
	public function add_actions() {
		add_action( 'add_meta_boxes', [ $this, 'meta_boxes' ] );
		add_shortcode( 'cpl_sermon_list', [ $this, 'display' ] );
		add_filter( 'allowed_block_types_all', [ $this, 'allowed_block_types' ], 10, 2 );
		add_filter( 'default_content', [ $this, 'populate_content' ], 10, 2 );
		add_filter( "{$this->post_type}_show_in_menu", [ $this, 'show_in_submenu' ] );
		add_filter( "{$this->post_type}_slug", [ $this, 'custom_slug' ] );
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
		add_meta_box( 'shortcode', 'Shortcode', [ $this, 'shortcode_meta_box' ], $this->post_type, 'side', 'high' );
	}

	/**
	 * Displays a metabox for copying the shortcode in the block editor
	 * 
	 * @param \WP_Post $post
	 */
	public function shortcode_meta_box( $post ) {
		$shortcode = "[cpl_sermon_list id={$post->ID}]";
		?>
		<input type='text' disabled value="<?php echo esc_attr( $shortcode ) ?>">
		<button class="button" onclick="navigator.clipboard.writeText('<?php echo esc_attr( $shortcode ) ?>')"><?php echo esc_html_e( 'Copy Shortcode', 'cp-library' ) ?></button>
		<?php
	}
	
	/**
	 * Displays Shortcode content when used inside a shortcode
	 * 
	 * @param array $atts
	 * @param string|null $content
	 * @param string $shortcode_tag
	 */
	public function display( $atts, $content, $shortcode_tag ) {
		$atts = shortcode_atts( array(
			'id' => 0
		), $atts, 'cp_sermon_list' );

		$atts['id'] = absint( $atts['id'] );

		if( ! $atts['id'] ) {
			return esc_html__( 'Invalid template id', 'cp-library' );
		}

		$post = get_post( $atts['id'] );

		if( ! $post ) {
			return esc_html__( 'Template not found', 'cp-library' );
		}

		return apply_filters( 'the_content', $post->post_content );
	}

	/**
	 * Specifies Gutenberg blocks allowed when editing a Shortcode with the block editor
	 * 
	 * @param bool|string[] $allowed
	 * @param \WP_Block_Editor_Context $context
	 */
	public function allowed_block_types( $allowed, $context ) {
		if( $context->post->post_type === $this->post_type ) {
			return apply_filters( 'cp_library_shortcodes_block_types', array( 
				'core/spacer',
				'core/group',
				'core/columns',
				'core/column',
				'cp-library/item-date',
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
				'cp-library/query'
			) );
		}

		return $allowed;
	}

	/**
	 * Populates content when creating a new Shortcode
	 * 
	 * @param string $content
	 * @param \WP_Post $post
	 */
	public function populate_content( $content, $post ) {
		if( $post->post_type !== $this->post_type ) {
			return $content;
		}
		$html_file = trailingslashit( CP_LIBRARY_PLUGIN_DIR )  . 'templates/default_content/shortcode-content.html';
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
	 */
	public function custom_slug( $slug ) {
		return 'cp_library_shortcodes';
	}
}
