<?php
/**
 * The Help card on the CP Sermons dashboard.
 *
 * @package CP_Library
 * @since 1.7.0
 */

namespace CP_Library\Admin\Dashboard;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Links to the documentation articles that answer the questions this plugin
 * generates most, plus support.
 *
 * Costs nothing to render — no queries, no options.
 *
 * @since 1.7.0
 */
class Help {

	/**
	 * Where published documentation lives.
	 *
	 * Articles are authored in this repo's `docs/` folder and synced to a
	 * HeroThemes knowledge base, which serves them under /knowledge-base/.
	 * Bare and /docs/ URLs redirect there rather than serving a copy, so link
	 * to the canonical form directly.
	 *
	 * @var string
	 */
	const DOCS_BASE = 'https://docs.churchplugins.com/knowledge-base/';

	/**
	 * The single instance of the class.
	 *
	 * @var Help
	 */
	protected static $_instance; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Only make one instance of Help.
	 *
	 * @return Help
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof Help ) {
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
	 * The public URL for a documentation article.
	 *
	 * @param string $slug The article slug, matching the `slug` in its docs/ front matter.
	 * @return string
	 * @since 1.7.0
	 */
	public static function doc_url( $slug ) {
		/**
		 * Filters the URL for a documentation article.
		 *
		 * @param string $url  The full article URL.
		 * @param string $slug The article slug.
		 * @since 1.7.0
		 */
		return apply_filters( 'cpl_doc_url', self::DOCS_BASE . $slug . '/', $slug );
	}

	/**
	 * Register the card.
	 *
	 * @param array $cards The registered cards.
	 * @return array
	 * @since 1.7.0
	 */
	public function register_card( $cards ) {
		$cards['help'] = array(
			'title'      => __( 'Help', 'cp-library' ),
			'column'     => 'side',
			'priority'   => 90,
			// Anyone who can reach the dashboard can read the docs.
			'capability' => '',
			'render'     => array( $this, 'render' ),
		);

		return $cards;
	}

	/**
	 * The articles to link, as slug => label.
	 *
	 * @return array
	 * @since 1.7.0
	 */
	protected function get_articles() {
		$articles = array(
			'adding-messages'                       => __( 'Adding sermons', 'cp-library' ),
			'podcast-feed'                          => __( 'Setting up your podcast', 'cp-library' ),
			'timestamps-and-transcripts-cp-library' => __( 'Timestamps & transcripts', 'cp-library' ),
			'cp-sermons-settings-and-configuration' => __( 'Settings reference', 'cp-library' ),
			'troubleshooting-cp-library'            => __( 'Troubleshooting', 'cp-library' ),
		);

		/**
		 * Filters the documentation articles listed on the dashboard.
		 *
		 * @param array $articles Article slug => link label.
		 * @since 1.7.0
		 */
		return apply_filters( 'cpl_dashboard_help_articles', $articles );
	}

	/**
	 * Render the card body.
	 *
	 * @return void
	 * @since 1.7.0
	 */
	public function render() {
		?>
		<ul class="cpl-dashboard__links">
			<?php foreach ( $this->get_articles() as $slug => $label ) : ?>
				<li>
					<a href="<?php echo esc_url( self::doc_url( $slug ) ); ?>" target="_blank" rel="noopener noreferrer">
						<?php echo esc_html( $label ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>

		<p class="cpl-dashboard__support">
			<a href="<?php echo esc_url( cp_library()->get_support_url() ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Contact support', 'cp-library' ); ?>
				<span aria-hidden="true">&rarr;</span>
			</a>
		</p>
		<?php
	}
}
