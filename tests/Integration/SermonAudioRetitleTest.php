<?php
/**
 * Tests that a SermonAudio re-sync retitles a sermon without touching its URL.
 *
 * Sermons imported before fullTitle was used are stored under the abbreviated
 * displayTitle, with a slug generated from that short title (and -2, -3, …
 * when several parts shared it). The next sync updates the title, because the
 * sync hash includes it. That update goes through wp_insert_post. An empty
 * post_name on that call is rebuilt from the new title, which would change
 * the permalink. The slug the sermon already has must survive, and the sermon
 * must be found by its external_id meta so the sync updates it instead of
 * inserting another post.
 *
 * A sermon that does not exist yet still gets WordPress's usual slug, derived
 * from the title being saved (now the full title).
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Integration;

use CP_Library\Adapters\SermonAudio;
use CP_Library\Models\Item as ItemModel;

/**
 * @covers \CP_Library\Adapters\Adapter::load_item
 * @covers \CP_Library\Adapters\Adapter::insert_imported_post
 * @covers \CP_Library\Adapters\SermonAudio::format_item
 */
class SermonAudioRetitleTest extends TestCase {

	/**
	 * @var SermonAudio
	 */
	private $adapter;

	public function set_up() {
		parent::set_up();
		$this->adapter = new SermonAudio();
	}

	public function test_resync_retitles_an_existing_sermon_without_changing_its_slug() {
		$post_type = cp_library()->setup->post_types->item->post_type;
		$full      = 'Example Sermon About Gathering Together, Part 1';
		$short     = 'Example Sermon About Gather...';
		$slug      = 'example-sermon-about-gather';

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_title'  => $short,
				'post_name'   => $slug,
				'post_status' => 'publish',
			)
		);
		update_post_meta( $post_id, 'external_id', 'sa-gather-1' );

		$this->import( $this->sermon( 'sa-gather-1', $short, $full ) );
		// A second sync must still match the same post, not insert another.
		$this->import( $this->sermon( 'sa-gather-1', $short, $full ) );

		$post = get_post( $post_id );

		$this->assertSame( $full, $post->post_title );
		$this->assertSame( $slug, $post->post_name, 'the permalink is the one the sermon already had' );
		$this->assertNotSame( sanitize_title( $full ), $post->post_name );

		$this->assertSame( array( $post_id ), $this->posts_with_external_id( $post_type, 'sa-gather-1' ) );
	}

	public function test_an_empty_slug_on_update_is_not_rebuilt_from_the_new_title() {
		$post_type = cp_library()->setup->post_types->item->post_type;
		$full      = 'Example Sermon About Gathering Together, Part 3';
		$slug      = 'example-sermon-about-gather';

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_title'  => 'Example Sermon About Gather...',
				'post_name'   => $slug,
				'post_status' => 'publish',
			)
		);
		update_post_meta( $post_id, 'external_id', 'sa-gather-3' );

		// wp_insert_post rebuilds post_name from post_title when the field is
		// present and empty. The import update must not take that path.
		$formatted              = $this->adapter->format_item( $this->sermon( 'sa-gather-3', 'Example Sermon About Gather...', $full ) );
		$formatted['post_name'] = '';

		$this->adapter->load_item( $formatted, ItemModel::class );

		$post = get_post( $post_id );

		$this->assertSame( $full, $post->post_title );
		$this->assertSame( $slug, $post->post_name );
		$this->assertSame( array( $post_id ), $this->posts_with_external_id( $post_type, 'sa-gather-3' ) );
	}

	public function test_a_new_sermon_gets_its_slug_from_the_full_title() {
		$post_type = cp_library()->setup->post_types->item->post_type;
		$full      = 'Example Sermon About Gathering Together, Part 2';
		$short     = 'Example Sermon About Gather...';

		$model = $this->import( $this->sermon( 'sa-gather-2', $short, $full ) );
		$post  = get_post( $model->origin_id );

		$this->assertSame( $full, $post->post_title );
		$this->assertSame( sanitize_title( $full ), $post->post_name );
		$this->assertNotSame( sanitize_title( $short ), $post->post_name );
		$this->assertSame( array( (int) $post->ID ), $this->posts_with_external_id( $post_type, 'sa-gather-2' ) );
	}

	public function test_a_very_long_title_is_saved_on_the_post() {
		// No trailing space: wp_insert_post trims the title, which is separate
		// from the abbreviated displayTitle this import used to keep.
		$long = rtrim( str_repeat( 'Gathering Together Part One ', 20 ) );

		$model = $this->import( $this->sermon( 'sa-long', 'Gathering Together Part...', $long ) );

		$this->assertGreaterThan( 200, strlen( $long ) );
		$this->assertSame( $long, get_post( $model->origin_id )->post_title );
	}

	/**
	 * @param object $sermon
	 * @return ItemModel
	 */
	private function import( $sermon ) {
		$formatted = $this->adapter->format_item( $sermon );

		return $this->adapter->load_item( $formatted, ItemModel::class );
	}

	/**
	 * @param string $external_id
	 * @param string $display
	 * @param string $full
	 * @return object
	 */
	private function sermon( $external_id, $display, $full ) {
		return (object) array(
			'sermonID'         => $external_id,
			'displayTitle'     => $display,
			'fullTitle'        => $full,
			'preachDate'       => '2024-01-07',
			'publishTimestamp' => 1700000000,
			'moreInfoText'     => '',
			'hasAudio'         => false,
			'hasVideo'         => false,
			'bibleText'        => null,
			'eventType'        => null,
		);
	}

	/**
	 * @param string $post_type
	 * @param string $external_id
	 * @return int[]
	 */
	private function posts_with_external_id( $post_type, $external_id ) {
		$ids = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'meta_key'       => 'external_id',
				'meta_value'     => $external_id,
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		return array_map( 'intval', $ids );
	}
}
