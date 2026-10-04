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
 * format_item() always sends post_status publish and a SermonAudio date. On
 * an update the status the post already has is put back, including trash,
 * draft, pending, private, and future. A future sermon also keeps the date
 * it was scheduled for. Other existing sermons still take the SermonAudio
 * date, which is what 1.7.0 did. A future sermon whose stored date is
 * already past is published by WordPress on update. A pending sermon stays
 * pending. One that has no slug is left without one; WordPress may assign
 * a new permalink when it is published, because core clears a pending slug
 * when no user who can publish is logged in. The main retitle sends an
 * empty post_name so that fill is what preserves the permalink of a
 * published sermon. Speakers and series keep draft, private, and trash on
 * update. A blank speaker or series title is not replaced with "Untitled
 * sermon"; it is passed to wp_insert_post() as before.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Integration;

use CP_Library\Adapters\SermonAudio;
use CP_Library\Models\Item as ItemModel;
use CP_Library\Models\ItemType as ItemTypeModel;
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

		// An empty post_name is what makes wp_insert_post rebuild the slug
		// from the new title. Omitting the key would keep the slug even
		// without the pre-fill. This payload includes the empty key.
		$formatted              = $this->adapter->format_item( $this->sermon( 'sa-gather-1', $short, $full ) );
		$formatted['post_name'] = '';
		$this->adapter->load_item( $formatted, ItemModel::class );
		$this->adapter->load_item( $formatted, ItemModel::class );

		$post = get_post( $post_id );

		$this->assertSame( 'publish', $post->post_status );
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
	 * format_item() sends publish. The update must leave a draft as a draft
	 * and keep a slug it already has. An empty post_name is what would
	 * otherwise rebuild that slug from the new title.
	 */
	public function test_a_draft_sermon_stays_a_draft_and_keeps_its_slug() {
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
		$this->assertSame( 'publish', $this->adapter->format_item( $this->sermon( 'sa-draft', 'Example Sermon About Gather...', $full ) )['post_status'] );

		$formatted              = $this->adapter->format_item( $this->sermon( 'sa-draft', 'Example Sermon About Gather...', $full ) );
		$formatted['post_name'] = '';
		$this->adapter->load_item( $formatted, ItemModel::class );

		$post = get_post( $post_id );

		$this->assertSame( 'draft', $post->post_status );
		$this->assertSame( $full, $post->post_title );
		$this->assertSame( $slug, $post->post_name );
	}

	/**
	 * A retitle leaves a pending sermon pending. Creating one stores no slug,
	 * and the import does not invent one. WordPress assigns a unique slug
	 * when the sermon is published.
	 */
	public function test_a_pending_sermon_stays_pending_after_a_retitle() {
		$post_type = cp_library()->setup->post_types->item->post_type;
		$full      = 'Example Sermon About Gathering Together, Part 4b';
		$short     = 'Example Sermon About Gather...';

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_title'  => $short,
				'post_name'   => 'will-not-stick',
				'post_status' => 'pending',
			)
		);
		update_post_meta( $post_id, 'external_id', 'sa-pending' );

		$this->assertSame( 'pending', get_post( $post_id )->post_status );
		$this->assertSame( '', get_post( $post_id )->post_name );

		$this->import( $this->sermon( 'sa-pending', $short, $full ) );

		$post = get_post( $post_id );

		$this->assertSame( 'pending', $post->post_status );
		$this->assertSame( $full, $post->post_title );
		$this->assertSame( '', $post->post_name );
	}

	/**
	 * A scheduled sermon stays future, and keeps the date it was scheduled
	 * for. format_item() sends a past SermonAudio date; writing that date
	 * would make wp_insert_post() publish the sermon. A published sermon
	 * still takes the SermonAudio date, which is the 1.7.0 update behavior.
	 */
	public function test_a_future_sermon_stays_future_and_keeps_its_scheduled_date() {
		$post_type = cp_library()->setup->post_types->item->post_type;
		$full      = 'Example Sermon About Gathering Together, Part 9';
		$scheduled = '2030-06-15 09:30:00';

		$post_id = self::factory()->post->create(
			array(
				'post_type'     => $post_type,
				'post_title'    => 'Example Sermon About Gather...',
				'post_name'     => 'scheduled-sermon',
				'post_status'   => 'future',
				'post_date'     => $scheduled,
				'post_date_gmt' => $scheduled,
			)
		);
		update_post_meta( $post_id, 'external_id', 'sa-future' );

		$before = get_post( $post_id );
		$this->assertSame( 'future', $before->post_status );
		$this->assertSame( $scheduled, $before->post_date );

		$formatted              = $this->adapter->format_item( $this->sermon( 'sa-future', 'Example Sermon About Gather...', $full ) );
		$formatted['post_name'] = '';
		$this->assertNotSame( $scheduled, $formatted['post_date'] );
		$this->adapter->load_item( $formatted, ItemModel::class );

		$post = get_post( $post_id );

		$this->assertSame( 'future', $post->post_status );
		$this->assertSame( $before->post_date, $post->post_date );
		$this->assertSame( $before->post_date_gmt, $post->post_date_gmt );
		$this->assertSame( $full, $post->post_title );
		$this->assertSame( 'scheduled-sermon', $post->post_name );
	}

	public function test_a_published_sermon_still_takes_the_sermonaudio_date() {
		$post_type = cp_library()->setup->post_types->item->post_type;
		$full      = 'Example Sermon About Gathering Together, Part 9b';

		$post_id = self::factory()->post->create(
			array(
				'post_type'     => $post_type,
				'post_title'    => 'Example Sermon About Gather...',
				'post_status'   => 'publish',
				'post_date'     => '2019-03-03 08:00:00',
				'post_date_gmt' => '2019-03-03 08:00:00',
			)
		);
		update_post_meta( $post_id, 'external_id', 'sa-dated' );

		$formatted = $this->adapter->format_item( $this->sermon( 'sa-dated', 'Example Sermon About Gather...', $full ) );
		$this->assertNotSame( '2019-03-03 08:00:00', $formatted['post_date'] );
		$this->adapter->load_item( $formatted, ItemModel::class );

		$post = get_post( $post_id );

		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( $formatted['post_date'], $post->post_date );
		$this->assertSame( $full, $post->post_title );
	}

	public function test_a_private_sermon_stays_private() {
		$post_type = cp_library()->setup->post_types->item->post_type;
		$full      = 'Example Sermon About Gathering Together, Part 9c';

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_title'  => 'Example Sermon About Gather...',
				'post_name'   => 'kept-private',
				'post_status' => 'private',
			)
		);
		update_post_meta( $post_id, 'external_id', 'sa-private' );

		$this->import( $this->sermon( 'sa-private', 'Example Sermon About Gather...', $full ) );

		$post = get_post( $post_id );

		$this->assertSame( 'private', $post->post_status );
		$this->assertSame( $full, $post->post_title );
		$this->assertSame( 'kept-private', $post->post_name );
	}

	/**
	 * A full import retitles every fetched sermon. format_item() sends
	 * publish, which used to untrash the sermon. The update must leave it in
	 * the trash, change the title, and leave the trashed slug in place
	 * (slug__trashed) rather than rebuilding it from the new title.
	 */
	public function test_a_trashed_sermon_stays_trashed_when_its_title_changes() {
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

		$this->assertSame( 'trash', $post->post_status );
		$this->assertSame( $full, $post->post_title );
		$this->assertSame( $slug . '__trashed', $post->post_name );
		$this->assertNotSame( sanitize_title( $full ), $post->post_name );
		$this->assertSame( $slug, get_post_meta( $post_id, '_wp_desired_post_slug', true ) );
		$this->assertSame( array( $post_id ), $this->posts_with_external_id( $post_type, 'sa-trashed', array( 'trash', 'publish', 'draft', 'pending' ) ) );
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
	 * Speakers and series use the same update path. A draft of either stays
	 * a draft when the payload says publish, and keeps its slug.
	 */
	public function test_a_draft_speaker_and_a_draft_series_keep_their_status() {
		$speaker_type = cp_library()->setup->post_types->speaker->post_type;
		$series_type  = cp_library()->setup->post_types->item_type->post_type;

		$speaker_id = self::factory()->post->create(
			array(
				'post_type'   => $speaker_type,
				'post_title'  => 'John Smith',
				'post_name'   => 'john-smith',
				'post_status' => 'draft',
			)
		);
		$series_id = self::factory()->post->create(
			array(
				'post_type'   => $series_type,
				'post_title'  => 'Gathering',
				'post_name'   => 'gathering',
				'post_status' => 'draft',
			)
		);
		update_post_meta( $speaker_id, 'external_id', 'spk-draft' );
		update_post_meta( $series_id, 'external_id', 'series-draft' );

		$this->adapter->load_item(
			array(
				'external_id' => 'spk-draft',
				'post_title'  => 'John Smith Jr.',
				'post_status' => 'publish',
				'post_type'   => $speaker_type,
				'post_name'   => '',
			),
			SpeakerModel::class
		);
		$this->adapter->load_item(
			array(
				'external_id' => 'series-draft',
				'post_title'  => 'Gathering Together',
				'post_status' => 'publish',
				'post_type'   => $series_type,
				'post_name'   => '',
			),
			ItemTypeModel::class
		);

		$speaker = get_post( $speaker_id );
		$series  = get_post( $series_id );

		$this->assertSame( 'draft', $speaker->post_status );
		$this->assertSame( 'John Smith Jr.', $speaker->post_title );
		$this->assertSame( 'john-smith', $speaker->post_name );
		$this->assertSame( 'draft', $series->post_status );
		$this->assertSame( 'Gathering Together', $series->post_title );
		$this->assertSame( 'gathering', $series->post_name );
	}

	/**
	 * Blank speaker names and series titles are not sermon titles. They must
	 * not become "Untitled sermon". What is stored is whatever wp_insert_post()
	 * did before this PR. title_save_pre trims, so a whitespace-only title is
	 * stored as "". A blank title overwrites an existing one when the insert
	 * is accepted. A series also supports excerpts, so a blank title with
	 * blank content is rejected as empty and the existing series is left
	 * unchanged. A speaker does not support excerpts, so the same payload
	 * clears the speaker title.
	 */
	public function test_a_blank_speaker_or_series_title_is_not_untitled_sermon() {
		$speaker_type = cp_library()->setup->post_types->speaker->post_type;
		$series_type  = cp_library()->setup->post_types->item_type->post_type;
		$blanks       = array(
			'empty'      => '',
			'null'       => null,
			'whitespace' => " \n\t ",
		);

		foreach ( $blanks as $label => $blank ) {
			// title_save_pre is trim(). trim(null) on PHP 8.1+ prints a
			// deprecation and then stores "". Coerce null one priority
			// earlier so that notice does not fail the suite. The title
			// saved is still empty, which is what WordPress stores.
			$coerce_null = null;
			if ( null === $blank ) {
				$coerce_null = static function ( $title ) {
					return is_string( $title ) ? $title : '';
				};
				add_filter( 'title_save_pre', $coerce_null, 9 );
			}

			try {
				$new_speaker = $this->adapter->load_item(
					array(
						'external_id'  => 'spk-new-' . $label,
						'post_title'   => $blank,
						'post_status'  => 'publish',
						'post_type'    => $speaker_type,
						'post_content' => '',
					),
					SpeakerModel::class
				);
				$new_series  = $this->adapter->load_item(
					array(
						'external_id'  => 'series-new-' . $label,
						'post_title'   => $blank,
						'post_status'  => 'publish',
						'post_type'    => $series_type,
						'post_content' => 'A description',
					),
					ItemTypeModel::class
				);

				$this->assertNotSame( 'Untitled sermon', get_post( $new_speaker->origin_id )->post_title, $label );
				$this->assertNotSame( 'Untitled sermon', get_post( $new_series->origin_id )->post_title, $label );
				$this->assertSame( $this->title_wp_stores( $blank ), get_post( $new_speaker->origin_id )->post_title, 'new speaker ' . $label );
				$this->assertSame( $this->title_wp_stores( $blank ), get_post( $new_series->origin_id )->post_title, 'new series ' . $label );

				$speaker_id = self::factory()->post->create(
					array(
						'post_type'   => $speaker_type,
						'post_title'  => 'Kept Speaker',
						'post_status' => 'publish',
					)
				);
				$series_id  = self::factory()->post->create(
					array(
						'post_type'    => $series_type,
						'post_title'   => 'Kept Series',
						'post_content' => 'Existing description',
						'post_status'  => 'publish',
					)
				);
				update_post_meta( $speaker_id, 'external_id', 'spk-old-' . $label );
				update_post_meta( $series_id, 'external_id', 'series-old-' . $label );

				$this->adapter->load_item(
					array(
						'external_id'  => 'spk-old-' . $label,
						'post_title'   => $blank,
						'post_status'  => 'publish',
						'post_type'    => $speaker_type,
						'post_content' => '',
					),
					SpeakerModel::class
				);
				$this->adapter->load_item(
					array(
						'external_id'  => 'series-old-' . $label,
						'post_title'   => $blank,
						'post_status'  => 'publish',
						'post_type'    => $series_type,
						'post_content' => 'A description',
					),
					ItemTypeModel::class
				);

				$this->assertNotSame( 'Untitled sermon', get_post( $speaker_id )->post_title, $label );
				$this->assertNotSame( 'Untitled sermon', get_post( $series_id )->post_title, $label );
				$this->assertSame( $this->title_wp_stores( $blank ), get_post( $speaker_id )->post_title, 'existing speaker ' . $label );
				$this->assertSame( $this->title_wp_stores( $blank ), get_post( $series_id )->post_title, 'existing series ' . $label );
			} finally {
				if ( null !== $coerce_null ) {
					remove_filter( 'title_save_pre', $coerce_null, 9 );
				}
			}
		}
	}

	/**
	 * Series supports excerpts, so WordPress rejects a series whose title,
	 * content, and excerpt are all empty. The existing series is not updated.
	 * A speaker does not support excerpts, so the same empty payload clears
	 * its title.
	 */
	public function test_an_empty_series_is_rejected_and_an_empty_speaker_title_is_cleared() {
		$speaker_type = cp_library()->setup->post_types->speaker->post_type;
		$series_type  = cp_library()->setup->post_types->item_type->post_type;

		$speaker_id = self::factory()->post->create(
			array(
				'post_type'   => $speaker_type,
				'post_title'  => 'Kept Speaker',
				'post_status' => 'publish',
			)
		);
		$series_id  = self::factory()->post->create(
			array(
				'post_type'   => $series_type,
				'post_title'  => 'Kept Series',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $speaker_id, 'external_id', 'spk-empty-all' );
		update_post_meta( $series_id, 'external_id', 'series-empty-all' );

		$this->adapter->load_item(
			array(
				'external_id'  => 'spk-empty-all',
				'post_title'   => '',
				'post_status'  => 'publish',
				'post_type'    => $speaker_type,
				'post_content' => '',
			),
			SpeakerModel::class
		);

		$series_error = null;
		try {
			$this->adapter->load_item(
				array(
					'external_id'  => 'series-empty-all',
					'post_title'   => '',
					'post_status'  => 'publish',
					'post_type'    => $series_type,
					'post_content' => '',
				),
				ItemTypeModel::class
			);
		} catch ( \Exception $e ) {
			$series_error = $e->getMessage();
		}

		$this->assertSame( '', get_post( $speaker_id )->post_title );
		$this->assertNotSame( 'Untitled sermon', get_post( $speaker_id )->post_title );
		$this->assertNotNull( $series_error );
		$this->assertStringContainsString( 'empty', strtolower( $series_error ) );
		$this->assertSame( 'Kept Series', get_post( $series_id )->post_title );
	}

	public function test_an_existing_sermon_keeps_a_backslash_in_its_title_when_the_api_titles_are_blank() {
		$post_type = cp_library()->setup->post_types->item->post_type;
		$kept      = 'Grace \\ Truth';

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_title'  => wp_slash( $kept ),
				'post_status' => 'publish',
			)
		);
		update_post_meta( $post_id, 'external_id', 'sa-backslash' );
		$this->assertSame( $kept, get_post( $post_id )->post_title );

		$formatted = $this->adapter->format_item( $this->sermon( 'sa-backslash', null, " \t " ) );
		$this->assertSame( '', $formatted['post_title'] );
		$this->adapter->task( $formatted );

		$this->assertSame( $kept, get_post( $post_id )->post_title );
	}

	public function test_an_api_title_containing_a_backslash_is_stored_unchanged() {
		$full  = 'Grace \\ Truth';
		$model = $this->import( $this->sermon( 'sa-backslash-api', 'Grace...', $full ) );

		$this->assertSame( $full, get_post( $model->origin_id )->post_title );
	}

	/**
	 * An already-imported sermon is updated through the importer's task(),
	 * the same write the sync queue runs, when both API titles are null,
	 * empty, or whitespace. The title already on the post is kept. The
	 * SermonAudio date is still written, which shows the update ran.
	 */
	public function test_an_existing_sermon_keeps_its_title_when_both_api_titles_are_blank() {
		$post_type = cp_library()->setup->post_types->item->post_type;
		$kept      = 'A Real Sermon Title';
		$payloads  = array(
			'null'       => array( null, null ),
			'empty'      => array( '', '' ),
			'whitespace' => array( " \n\t ", "  \t" ),
		);

		foreach ( $payloads as $label => $titles ) {
			$external_id = 'sa-keep-' . $label;
			$post_id     = self::factory()->post->create(
				array(
					'post_type'     => $post_type,
					'post_title'    => $kept,
					'post_name'     => 'a-real-sermon-title-' . $label,
					'post_status'   => 'publish',
					'post_date'     => '2019-03-03 08:00:00',
					'post_date_gmt' => '2019-03-03 08:00:00',
				)
			);
			update_post_meta( $post_id, 'external_id', $external_id );

			$formatted = $this->adapter->format_item( $this->sermon( $external_id, $titles[0], $titles[1] ) );

			$this->assertSame( '', $formatted['post_title'], $label );
			$this->assertNotSame( '2019-03-03 08:00:00', $formatted['post_date'], $label );

			$this->adapter->task( $formatted );

			$post = get_post( $post_id );

			$this->assertSame( $kept, $post->post_title, $label );
			$this->assertNotSame( 'Untitled sermon', $post->post_title, $label );
			$this->assertSame( $formatted['post_date'], $post->post_date, $label );
			$this->assertSame( array( $post_id ), $this->posts_with_external_id( $post_type, $external_id ), $label );
		}
	}

	public function test_a_new_sermon_with_blank_titles_is_saved_as_untitled() {
		$payloads = array(
			'null'       => array( null, null ),
			'empty'      => array( '', '' ),
			'whitespace' => array( " \n\t ", "  \t" ),
		);

		foreach ( $payloads as $label => $titles ) {
			$external_id = 'sa-new-blank-' . $label;
			$model       = $this->import( $this->sermon( $external_id, $titles[0], $titles[1] ) );
			$post        = get_post( $model->origin_id );

			$this->assertSame( 'Untitled sermon', $post->post_title, $label );
			$this->assertNotSame( $external_id, $post->post_title, $label );
			$this->assertStringStartsWith( 'untitled-sermon', $post->post_name, $label );
		}
	}

	/**
	 * Title wp_insert_post() stores for a value that was not slashed first.
	 *
	 * Null and "" become an empty title. title_save_pre runs trim(), so a
	 * whitespace-only string is stored as "" too.
	 *
	 * @param string|null $title
	 * @return string
	 */
	private function title_wp_stores( $title ) {
		if ( ! is_string( $title ) ) {
			return '';
		}

		return trim( $title );
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
	 * @param string          $post_type
	 * @param string          $external_id
	 * @param string|string[] $post_status get_posts() status. "any" skips trash.
	 * @return int[]
	 */
	private function posts_with_external_id( $post_type, $external_id, $post_status = 'any' ) {
		$ids = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => $post_status,
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
