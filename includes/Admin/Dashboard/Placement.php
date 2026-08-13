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
					__( 'A page listing all your %s, with search and filters.', 'cp-library' ),
					$item->plural_label
				),
			),
			array(
				'code'  => '[cp-sermon id="123"]',
				'label' => sprintf(
					/* translators: %s: the singular sermon label, e.g. "Sermon". */
					__( 'Shows one %s and its player. Replace 123 with that one\'s number — open it for editing and look for post=1234 in your browser address bar.', 'cp-library' ),
					$item->single_label
				),
			),
			array(
				'code'  => '[cpl_video_widget]',
				'label' => sprintf(
					/* translators: %s: the singular sermon label, e.g. "Sermon". */
					__( 'Always shows your newest %s. Good for a home page — it updates itself.', 'cp-library' ),
					$item->single_label
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
			<?php
			/* The safe path first. The previous copy led with "paste any of these" and buried blocks in a subordinate clause — and overclaimed, since only the archive has a block, under a category named "CP Sermons Queries" rather than "CP Sermons". */
			esc_html_e( 'The easiest way is to edit a page and add the "CP Sermons Sermons/Series" block — look for it under CP Sermons Queries in the block list.', 'cp-library' );
			?>
		</p>
		<p class="cpl-dashboard__hint">
			<?php esc_html_e( 'Prefer to type it? These do the same thing. Paste one into a page and it becomes the real thing when you save.', 'cp-library' ); ?>
		</p>

		<ul class="cpl-dashboard__codes">
			<?php foreach ( $this->get_shortcodes() as $shortcode ) : ?>
				<li>
					<code class="cpl-copy__value"><?php echo esc_html( $shortcode['code'] ); ?></code>
					<button
						type="button"
						class="button button-small cpl-copy"
						data-copy="<?php echo esc_attr( $shortcode['code'] ); ?>"
						<?php /* Three buttons all named "Copy" read as "Copy, Copy, Copy" in a screen reader's button list. */ ?>
						aria-label="<?php echo esc_attr( sprintf( __( 'Copy %s', 'cp-library' ), $shortcode['code'] ) ); ?>"
					>
						<?php esc_html_e( 'Copy', 'cp-library' ); ?>
					</button>
					<span class="cpl-dashboard__code-label"><?php echo esc_html( $shortcode['label'] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( $builders ) : ?>
			<div class="cpl-dashboard__builder">
				<h3>
					<?php
					printf(
						/* translators: %s: a list of page builder names, e.g. "Divi" or "Divi and Elementor". */
						esc_html__( 'Designing %s pages yourself', 'cp-library' ),
						esc_html( cp_library()->setup->post_types->item->single_label )
					);
					?>
				</h3>
				<p>
					<?php
					printf(
						/* translators: 1: the singular sermon label, 2: the page builders in use. */
						esc_html__( 'Right now CP Sermons decides how each %1$s page looks, so a design built in %2$s is not used on them. To design them yourself instead, paste the code below into your builder layout and the sermon content will appear at exactly that spot rather than taking over the page.', 'cp-library' ),
						esc_html( cp_library()->setup->post_types->item->single_label ),
						esc_html( wp_sprintf( '%l', $builders ) )
					);
					?>
				</p>
				<p>
					<?php esc_html_e( 'This only changes how those pages look — nothing is deleted, and removing the code puts it back. If you are not sure, this is usually a job for whoever built your site.', 'cp-library' ); ?>
				</p>
				<p class="cpl-dashboard__codes">
					<code class="cpl-copy__value">[cp-template]</code>
					<button
						type="button"
						class="button button-small cpl-copy"
						data-copy="[cp-template]"
						aria-label="<?php esc_attr_e( 'Copy [cp-template]', 'cp-library' ); ?>"
					>
						<?php esc_html_e( 'Copy', 'cp-library' ); ?>
					</button>
				</p>
				<p>
					<a href="<?php echo esc_url( Help::doc_url( 'church-plugins-customizer' ) ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'How sermon pages are designed', 'cp-library' ); ?>
						<span aria-hidden="true">&rarr;</span>
					</a>
				</p>
			</div>
		<?php endif; ?>
		<?php
	}
}
