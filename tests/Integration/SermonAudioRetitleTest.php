<?php
/**
 * Tests that a SermonAudio re-sync retitles a sermon without touching its URL.
 *
 * Sermons imported before fullTitle was used are stored under the abbreviated
 * displayTitle, with a slug generated from that short title (and -2, -3, …
 * when several parts shared it). A later fetch updates the title, because the
 * sync hash includes it. Scheduled sync and Check Now only fetch the newest
 * check_count sermons (default 50); older ones are fetched by a full import.
 * That update goes through wp_insert_post. An empty post_name on that call is
 * rebuilt from the new title, which would change the permalink. The slug the
 * sermon already has must survive, and the sermon must be found by its
 * external_id meta so the fetch updates it instead of inserting another post.
 *
 * A sermon that does not exist yet still gets WordPress's usual slug, derived
 * from the title being saved (now the full title). Two new sermons with the
 * same title get distinct slugs. Speakers use the same insert helper and do
 * not pass a post_name, so an update keeps the slug it already had.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Integration;

use CP_Library\Adapters\SermonAudio;
use CP_Library\Models\Item as ItemModel;
use CP_Library\Models\Speaker as SpeakerModel;

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
	 * A draft can already have a slug. Republishing it on import (format_item
	 * always sends post_status publish) must keep that slug. The empty
	 * post_name is what would otherwise rebuild it from the new title.
	 */
	public function test_a_draft_sermon_keeps_its_slug_when_it_is_published() {
		$post_type = cp_library()->setup->post_types->item->post_type;
		$full      = 'Example Sermon About Gathering Together, Part 4';
		$slug      = 'kept-from-draft';

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_title'  => 'Example Sermon About Gather...',
				'post_name'   => $slug,
				'post_status' => 'draft',
			)
		);
		update_post_meta( $post_id, 'external_id', 'sa-draft' );

		$this->assertSame( $slug, get_post( $post_id )->post_name, 'the fixture itself has a slug' );

		$formatted              = $this->adapter->format_item( $this->sermon( 'sa-draft', 'Example Sermon About Gather...', $full ) );
		$formatted['post_name'] = '';
		$this->adapter->load_item( $formatted, ItemModel::class );

		$post = get_post( $post_id );

		$this->assertSame( 'publish', $post->post_status, 'format_item publishes; that is existing behavior' );
		$this->assertSame( $full, $post->post_title );
		$this->assertSame( $slug, $post->post_name );
	}

	/**
	 * Creating a pending sermon does not store a slug: wp_insert_post clears
	 * post_name when the user cannot publish that post type. There is nothing
	 * to preserve, so publishing it on import generates the slug from the
	 * full title.
	 */
	public function test_a_pending_sermon_with_no_slug_is_published_under_the_full_title() {
		$post_type = cp_library()->setup->post_types->item->post_type;
		$full      = 'Example Sermon About Gathering Together, Part 4b';

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_title'  => 'Example Sermon About Gather...',
				'post_name'   => 'will-not-stick',
				'post_status' => 'pending',
			)
		);
		update_post_meta( $post_id, 'external_id', 'sa-pending-empty' );

		$this->assertSame( '', get_post( $post_id )->post_name );

		$this->import( $this->sermon( 'sa-pending-empty', 'Example Sermon About Gather...', $full ) );

		$post = get_post( $post_id );

		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( $full, $post->post_title );
		$this->assertSame( sanitize_title( $full ), $post->post_name );
	}

	/**
	 * A pending sermon that already has a slug (set outside wp_insert_post)
	 * keeps it when the import publishes the sermon.
	 */
	public function test_a_pending_sermon_keeps_a_slug_it_already_has() {
		global $wpdb;

		$post_type = cp_library()->setup->post_types->item->post_type;
		$full      = 'Example Sermon About Gathering Together, Part 4c';
		$slug      = 'kept-from-pending';

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_title'  => 'Example Sermon About Gather...',
				'post_status' => 'pending',
			)
		);
		$wpdb->update(
			$wpdb->posts,
			array( 'post_name' => $slug ),
			array( 'ID' => $post_id )
		);
		clean_post_cache( $post_id );
		update_post_meta( $post_id, 'external_id', 'sa-pending' );

		$this->assertSame( $slug, get_post( $post_id )->post_name );

		$formatted              = $this->adapter->format_item( $this->sermon( 'sa-pending', 'Example Sermon About Gather...', $full ) );
		$formatted['post_name'] = '';
		$this->adapter->load_item( $formatted, ItemModel::class );

		$post = get_post( $post_id );

		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( $full, $post->post_title );
		$this->assertSame( $slug, $post->post_name );
	}

	/**
	 * Re-importing a trashed sermon publishes it, because format_item() always
	 * sends post_status publish. WordPress then puts back the pre-trash slug
	 * stored in _wp_desired_post_slug. The slug must not stay __trashed, and
	 * must not be rebuilt from the new title.
	 */
	public function test_a_trashed_sermon_is_published_with_its_pre_trash_slug() {
		$post_type = cp_library()->setup->post_types->item->post_type;
		$full      = 'Example Sermon About Gathering Together, Part 5';
		$slug      = 'example-sermon-about-gather';

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_title'  => 'Example Sermon About Gather...',
				'post_name'   => $slug,
				'post_status' => 'publish',
			)
		);
		update_post_meta( $post_id, 'external_id', 'sa-trashed' );
		wp_trash_post( $post_id );

		$trashed = get_post( $post_id );
		$this->assertSame( 'trash', $trashed->post_status );
		$this->assertSame( $slug . '__trashed', $trashed->post_name );
		$this->assertSame( $slug, get_post_meta( $post_id, '_wp_desired_post_slug', true ) );

		$formatted              = $this->adapter->format_item( $this->sermon( 'sa-trashed', 'Example Sermon About Gather...', $full ) );
		$formatted['post_name'] = '';
		$this->adapter->load_item( $formatted, ItemModel::class );

		$post = get_post( $post_id );

		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( $full, $post->post_title );
		$this->assertSame( $slug, $post->post_name );
		$this->assertStringNotContainsString( '__trashed', $post->post_name );
		$this->assertSame( array( $post_id ), $this->posts_with_external_id( $post_type, 'sa-trashed' ) );
	}

	public function test_two_new_sermons_with_the_same_title_get_unique_slugs() {
		$full = 'Example Sermon About Gathering Together';

		$first  = $this->import( $this->sermon( 'sa-same-1', 'Example Sermon About Gather...', $full ) );
		$second = $this->import( $this->sermon( 'sa-same-2', 'Example Sermon About Gather...', $full ) );

		$first_post  = get_post( $first->origin_id );
		$second_post = get_post( $second->origin_id );

		$this->assertSame( sanitize_title( $full ), $first_post->post_name );
		$this->assertSame( sanitize_title( $full ) . '-2', $second_post->post_name );
		$this->assertNotSame( $first_post->ID, $second_post->ID );
	}

	/**
	 * The slug the new title would produce is already another sermon's.
	 * Retitling must keep the imported sermon's own slug and leave the other
	 * post's slug alone. An empty post_name is what would otherwise adopt the
	 * new title's slug (or a -2 of it).
	 */
	public function test_retitle_does_not_take_a_slug_another_post_already_has() {
		$post_type = cp_library()->setup->post_types->item->post_type;
		$full      = 'Example Sermon About Gathering Together, Part 6';
		$taken     = sanitize_title( $full );
		$own       = 'example-sermon-about-gather';

		$other_id = self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_title'  => 'Already Using That Slug',
				'post_name'   => $taken,
				'post_status' => 'publish',
			)
		);
		$post_id  = self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_title'  => 'Example Sermon About Gather...',
				'post_name'   => $own,
				'post_status' => 'publish',
			)
		);
		update_post_meta( $post_id, 'external_id', 'sa-taken' );

		$formatted              = $this->adapter->format_item( $this->sermon( 'sa-taken', 'Example Sermon About Gather...', $full ) );
		$formatted['post_name'] = '';
		$this->adapter->load_item( $formatted, ItemModel::class );

		$this->assertSame( $full, get_post( $post_id )->post_title );
		$this->assertSame( $own, get_post( $post_id )->post_name );
		$this->assertSame( $taken, get_post( $other_id )->post_name );
		$this->assertSame( 'Already Using That Slug', get_post( $other_id )->post_title );
	}

	/**
	 * Two posts can share an external_id. The lookup returns one of them and
	 * the import updates that one. The other is left in place, and no third
	 * post is created.
	 */
	public function test_duplicate_external_id_rows_update_only_the_matched_post() {
		$post_type = cp_library()->setup->post_types->item->post_type;
		$full      = 'Example Sermon About Gathering Together, Part 7';

		$first_id  = self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_title'  => 'First Copy',
				'post_name'   => 'first-copy',
				'post_status' => 'publish',
			)
		);
		$second_id = self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_title'  => 'Second Copy',
				'post_name'   => 'second-copy',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $first_id, 'external_id', 'sa-dup' );
		update_post_meta( $second_id, 'external_id', 'sa-dup' );

		$matched = (int) $this->adapter->get_item_id_from_external( 'sa-dup' );
		$this->assertContains( $matched, array( $first_id, $second_id ) );
		$other = ( $matched === $first_id ) ? $second_id : $first_id;
		$other_title = get_post( $other )->post_title;
		$other_slug  = get_post( $other )->post_name;

		$this->import( $this->sermon( 'sa-dup', 'Example Sermon About Gather...', $full ) );

		$this->assertSame( $full, get_post( $matched )->post_title );
		$this->assertSame( $other_title, get_post( $other )->post_title );
		$this->assertSame( $other_slug, get_post( $other )->post_name );
		$this->assertSame(
			array( $first_id, $second_id ),
			$this->posts_with_external_id( $post_type, 'sa-dup' )
		);
	}

	/**
	 * A save_post callback that inserts another post must not receive the
	 * sermon's slug. That is what a wp_insert_post_data filter locked to the
	 * outer slug would do.
	 */
	public function test_a_post_created_during_save_does_not_inherit_the_sermon_slug() {
		$post_type = cp_library()->setup->post_types->item->post_type;
		$slug      = 'example-sermon-about-gather';
		$full      = 'Example Sermon About Gathering Together, Part 8';
		$note      = 'A Note Created Alongside The Import';

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_title'  => 'Example Sermon About Gather...',
				'post_name'   => $slug,
				'post_status' => 'publish',
			)
		);
		update_post_meta( $post_id, 'external_id', 'sa-nested' );

		$created = null;
		add_action(
			'save_post',
			static function ( $saved_id ) use ( &$created, $post_id, $post_type, $note ) {
				if ( (int) $saved_id !== (int) $post_id || null !== $created ) {
					return;
				}

				$created = wp_insert_post(
					array(
						'post_type'   => $post_type,
						'post_status' => 'publish',
						'post_title'  => $note,
					)
				);
			}
		);

		$formatted              = $this->adapter->format_item( $this->sermon( 'sa-nested', 'Example Sermon About Gather...', $full ) );
		$formatted['post_name'] = '';
		$this->adapter->load_item( $formatted, ItemModel::class );

		$this->assertIsInt( $created );
		$created_post = get_post( $created );

		$this->assertSame( sanitize_title( $note ), $created_post->post_name );
		$this->assertNotSame( $slug, $created_post->post_name );
		$this->assertSame( $slug, get_post( $post_id )->post_name );
		$this->assertSame( $full, get_post( $post_id )->post_title );
	}

	/**
	 * Speakers share insert_imported_post(). An update does not pass a
	 * post_name, so the speaker keeps the slug it already had. A new speaker
	 * still receives one generated from its title.
	 */
	public function test_speaker_import_keeps_an_existing_slug_and_generates_one_for_a_new_speaker() {
		$post_type = cp_library()->setup->post_types->speaker->post_type;
		$slug      = 'john-smith';

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_title'  => 'John Smith',
				'post_name'   => $slug,
				'post_status' => 'publish',
			)
		);
		update_post_meta( $post_id, 'external_id', 'spk-1' );

		$this->adapter->load_item(
			array(
				'external_id' => 'spk-1',
				'post_title'  => 'John Smith Jr.',
				'post_status' => 'publish',
				'post_type'   => $post_type,
			),
			SpeakerModel::class
		);

		$this->assertSame( 'John Smith Jr.', get_post( $post_id )->post_title );
		$this->assertSame( $slug, get_post( $post_id )->post_name );

		$created = $this->adapter->load_item(
			array(
				'external_id' => 'spk-2',
				'post_title'  => 'Jane Doe',
				'post_status' => 'publish',
				'post_type'   => $post_type,
			),
			SpeakerModel::class
		);

		$this->assertSame( 'jane-doe', get_post( $created->origin_id )->post_name );
		$this->assertNotSame( $post_id, (int) $created->origin_id );
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
