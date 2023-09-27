<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * CP Library block initialization class
 *
 * @package CP_Library/Setup/Blocks
 */

namespace CP_Library\Setup\Blocks;

use CP_Library\Admin\Settings;

/**
 * Setup plugin initialization for CPTs
 */
class Init {

	/**
	 * Single class instance
	 *
	 * @var Init
	 */
	protected static $_instance;

	/**
	 * Only make one instance of Init
	 *
	 * @return Init
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof Init ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor
	 *
	 * Run includes and actions on instantiation
	 */
	protected function __construct() {
		$this->includes();
		$this->actions();
	}

	/**
	 * Plugin init includes
	 *
	 * @return void
	 */
	protected function includes() {
		new ItemDate();
		new ItemDescription();
		new ItemGraphic();
		new ItemTitle();
		new Pagination();
		new Query();
		new SermonActions();
		new SermonScripture();
		new SermonSeries();
		new SermonSpeaker();
		new SermonTemplate();
		new SermonTopics();
		new ShortcodeTemplate();
	}

	/**
	 * Plugin init actions
	 *
	 * @return void
	 */
	protected function actions() {
		add_filter( 'default_post_metadata', array( $this, 'default_thumbnail' ), 10, 5 );
		add_filter( 'block_categories_all', array( $this, 'block_categories' ) );
	}

	/**
	 * Sets default thumbnail for sermon and series post types
	 *
	 * @param mixed  $value the value of the meta field.
	 * @param int    $object_id the post ID.
	 * @param string $meta_key the meta key.
	 * @param bool   $single whether to return a single value.
	 * @param string $meta_type the type of metadata.
	 * @author Jonathan Roley
	 */
	public function default_thumbnail( $value, $object_id, $meta_key, $single, $meta_type ) {
		if ( $value ) {
			return $value;
		}
		if ( '_thumbnail_id' === $meta_key && 'post' === $meta_type ) {
			$post_type = get_post_type( $object_id );
			if ( cp_library()->setup->post_types->item->post_type === $post_type || cp_library()->setup->post_types->item_type->post_type === $post_type ) {
				$image = Settings::get( 'default_thumbnail', false );
				if ( $image ) {
					$image = attachment_url_to_postid( $image );
				}
				return $image ? $image : $value;
			}
		}
		return $value;
	}

	/**
	 * Adds a custom block category to be used by custom Gutenberg blocks
	 *
	 * @param array $categories the default block categories.
	 * @return array the updated block categories.
	 */
	public function block_categories( $categories ) {

		$categories[] = array(
			'slug'  => 'cp-library',
			'title' => 'CP Library',
		);

		$categories[] = array(
			'slug'  => 'cp-library-queries',
			'title' => 'CP Library Queries',
		);

		return $categories;
	}
}
