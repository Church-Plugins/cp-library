<?php
/**
 * Settings shortcuts on the CP Sermons dashboard.
 *
 * @package CP_Library
 * @since 1.7.0
 */

namespace CP_Library\Admin\Dashboard;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Deep links into the settings tabs, with what each one is for.
 *
 * The settings screen already has a tab bar, so a plain list of tab names would
 * add nothing. What it does not have is any indication of what lives behind
 * each tab — "Advanced" is where you turn Series, Speakers, Service Types and
 * the podcast on and off, which is not something the word "Advanced" conveys.
 * The description is the reason this card exists; the link is incidental.
 *
 * Tabs for features that are switched off are not listed.
 *
 * @since 1.7.0
 */
class SettingsLinks {

	/**
	 * The single instance of the class.
	 *
	 * @var SettingsLinks
	 */
	protected static $_instance; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Only make one instance of SettingsLinks.
	 *
	 * @return SettingsLinks
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof SettingsLinks ) {
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
		$cards['settings'] = array(
			'title'    => __( 'Settings', 'cp-library' ),
			'column'   => 'side',
			'priority' => 10,
			'render'   => array( $this, 'render' ),
		);

		return $cards;
	}

	/**
	 * The settings destinations, in the order they are useful.
	 *
	 * @return array
	 * @since 1.7.0
	 */
	protected function get_links() {
		$post_types = cp_library()->setup->post_types;

		$links = array(
			array(
				'label' => $post_types->item->plural_label,
				'desc'  => __( 'What to call them, their web address, and what shows on each one.', 'cp-library' ),
				'url'   => admin_url( 'admin.php?page=cpl_item_options' ),
			),
		);

		if ( $post_types->item_type_enabled() ) {
			$links[] = array(
				'label' => $post_types->item_type->plural_label,
				'desc'  => __( 'Sort order, images, and how many to show.', 'cp-library' ),
				'url'   => admin_url( 'admin.php?page=cpl_item_type_options' ),
			);
		}

		if ( $post_types->speaker_enabled() ) {
			$links[] = array(
				'label' => $post_types->speaker->plural_label,
				'desc'  => __( 'Labels, and whether each one gets its own page.', 'cp-library' ),
				'url'   => admin_url( 'admin.php?page=cpl_speaker_options' ),
			);
		}

		if ( cp_library()->setup->podcast->is_enabled() ) {
			$links[] = array(
				'label' => __( 'Podcast', 'cp-library' ),
				'desc'  => __( 'Feed details, cover art, and iTunes category.', 'cp-library' ),
				'url'   => admin_url( 'admin.php?page=cpl_podcast_options' ),
			);
		}

		$links[] = array(
			'label' => __( 'Advanced', 'cp-library' ),
			'desc'  => __( 'Turn Series, Speakers, Service Types and the podcast feed on or off.', 'cp-library' ),
			'url'   => admin_url( 'admin.php?page=cpl_advanced_options' ),
		);

		$links[] = array(
			'label' => __( 'Tools', 'cp-library' ),
			'desc'  => __( 'Import and export, and move a library between sites.', 'cp-library' ),
			'url'   => admin_url( 'edit.php?post_type=' . $post_types->item->post_type . '&page=cp-library-tools' ),
		);

		/**
		 * Filters the settings shortcuts on the dashboard.
		 *
		 * @param array $links Each with a `label`, `desc` and `url`.
		 * @since 1.7.0
		 */
		return apply_filters( 'cpl_dashboard_settings_links', $links );
	}

	/**
	 * Render the card body.
	 *
	 * @return void
	 * @since 1.7.0
	 */
	public function render() {
		?>
		<ul class="cpl-dashboard__settings">
			<?php foreach ( $this->get_links() as $link ) : ?>
				<li>
					<a href="<?php echo esc_url( $link['url'] ); ?>"><?php echo esc_html( $link['label'] ); ?></a>
					<span><?php echo esc_html( $link['desc'] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}
}
