<?php
/**
 * "Put sermons on your site" — the shortcode and template reference.
 *
 * @package CP_Library
 * @since 1.7.0
 */

namespace CP_Library\Admin\Dashboard;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Light documentation for getting sermon content onto a page.
 *
 * This is the least discoverable thing the plugin does. The shortcodes are
 * registered in code and documented on a website; nothing in the admin has ever
 * listed them, so the only way to learn `[cp-sermons]` exists is to be told.
 *
 * When a page builder is active the card also explains `[cp-template]`, which
 * is close to undiscoverable and solves a problem builder users reliably hit:
 * CP Sermons takes over the template for sermon pages, so a layout built in
 * Divi or Elementor gets bypassed. Putting `[cp-template]` in the layout
 * inverts that — the plugin stands down and renders its output exactly where
 * the shortcode sits instead (see ChurchPlugins\Templates::get_template(),
 * which checks for the shortcode before taking over).
 *
 * @since 1.7.0
 */
class Placement {

	/**
	 * The single instance of the class.
	 *
	 * @var Placement
	 */
	protected static $_instance; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Only make one instance of Placement.
	 *
	 * @return Placement
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof Placement ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor.
	 */
	protected function __construct() {
		add_filter( 'cpl_dashboard_cards', array( $this, 'register_card' ) );
	}

	/**
	 * Register the card.
	 *
	 * @param array $cards The registered cards.
	 * @return array
	 * @since 1.7.0
	 */
	public function register_card( $cards ) {
		$cards['placement'] = array(
			'title'    => __( 'Put sermons on your site', 'cp-library' ),
			'column'   => 'main',
			'priority' => 20,
			'render'   => array( $this, 'render' ),
		);

		return $cards;
	}

	/**
	 * The page builders active on this site.
	 *
	 * Uses the same constants the plugin's own builder modules gate on, so this
	 * can never claim a builder the modules are not loaded for.
	 *
	 * @return array Builder display names.
	 * @since 1.7.0
	 */
	public function get_active_builders() {
		$builders = array();

		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			$builders[] = 'Elementor';
		}

		if ( defined( 'FL_BUILDER_VERSION' ) ) {
			$builders[] = 'Beaver Builder';
		}

		// Divi ships as a theme and as the Divi Builder plugin; the builder class
		// is what both have in common, and it is what Modules\Divi extends.
		if ( class_exists( '\ET_Builder_Module' ) || function_exists( 'et_setup_theme' ) ) {
			$builders[] = 'Divi';
		}

		/**
		 * Filters the page builders reported on the dashboard.
		 *
		 * @param array $builders Builder display names.
		 * @since 1.7.0
		 */
		return apply_filters( 'cpl_dashboard_active_builders', $builders );
	}

	/**
	 * The shortcodes worth putting in front of someone.
	 *
	 * Not the full registered list — `cpl_item_list`, `cpl_source`, and friends
	 * are internal or superseded, and listing all nine would bury the two that
	 * most people want.
	 *
	 * @return array
	 * @since 1.7.0
	 */
	protected function get_shortcodes() {
		$item = cp_library()->setup->post_types->item;

		$shortcodes = array(
			array(
				'code'  => '[cp-sermons]',
				'label' => sprintf(
					/* translators: %s: the plural sermon label, e.g. "Sermons". */
					__( 'The full %s archive, with filters and search.', 'cp-library' ),
					strtolower( $item->plural_label )
				),
			),
			array(
				'code'  => '[cp-sermon id="123"]',
				'label' => sprintf(
					/* translators: %s: the singular sermon label, e.g. "sermon". */
					__( 'One %s, with its player. Use the ID from the address bar when editing it.', 'cp-library' ),
					strtolower( $item->single_label )
				),
			),
			array(
				'code'  => '[cpl_video_widget]',
				'label' => sprintf(
					/* translators: %s: the singular sermon label. */
					__( 'The most recent %s as a video widget — good for a home page.', 'cp-library' ),
					strtolower( $item->single_label )
				),
			),
		);

		/**
		 * Filters the shortcodes listed on the dashboard.
		 *
		 * @param array $shortcodes Each with a `code` and a `label`.
		 * @since 1.7.0
		 */
		return apply_filters( 'cpl_dashboard_shortcodes', $shortcodes );
	}

	/**
	 * Render the card body.
	 *
	 * @return void
	 * @since 1.7.0
	 */
	public function render() {
		$builders = $this->get_active_builders();
		?>
		<p class="cpl-dashboard__hint">
			<?php esc_html_e( 'Paste any of these into a page or post. Blocks for each are in the editor under "CP Sermons".', 'cp-library' ); ?>
		</p>

		<ul class="cpl-dashboard__codes">
			<?php foreach ( $this->get_shortcodes() as $shortcode ) : ?>
				<li>
					<code class="cpl-copy__value"><?php echo esc_html( $shortcode['code'] ); ?></code>
					<button type="button" class="button button-small cpl-copy" data-copy="<?php echo esc_attr( $shortcode['code'] ); ?>">
						<?php esc_html_e( 'Copy', 'cp-library' ); ?>
					</button>
					<span class="cpl-dashboard__code-label"><?php echo esc_html( $shortcode['label'] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( $builders ) : ?>
			<div class="cpl-dashboard__builder">
				<h4>
					<?php
					printf(
						/* translators: %s: a page builder name, e.g. "Divi". */
						esc_html__( 'Using %s?', 'cp-library' ),
						esc_html( implode( ', ', $builders ) )
					);
					?>
				</h4>
				<p>
					<?php
					printf(
						/* translators: %s: the singular sermon label, e.g. "sermon". */
						esc_html__( 'CP Sermons supplies its own layout for a single %s, which replaces whatever your builder would render. To design that page yourself instead, add this shortcode anywhere in your builder layout — CP Sermons will stand down and render its content in that exact spot.', 'cp-library' ),
						esc_html( strtolower( cp_library()->setup->post_types->item->single_label ) )
					);
					?>
				</p>
				<p class="cpl-dashboard__codes">
					<code class="cpl-copy__value">[cp-template]</code>
					<button type="button" class="button button-small cpl-copy" data-copy="[cp-template]">
						<?php esc_html_e( 'Copy', 'cp-library' ); ?>
					</button>
				</p>
				<p>
					<a href="<?php echo esc_url( Help::doc_url( 'church-plugins-customizer' ) ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Templates and display options', 'cp-library' ); ?>
						<span aria-hidden="true">&rarr;</span>
					</a>
				</p>
			</div>
		<?php endif; ?>
		<?php
	}
}
