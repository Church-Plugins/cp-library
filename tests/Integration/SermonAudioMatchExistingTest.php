<?php
/**
 * SermonAudio sync links one existing sermon instead of inserting a duplicate.
 *
 * A sermon with no external_id was inserted again when SermonAudio sent the
 * same service. The sync now updates that cpl_item when it is the only post
 * of its type on the same site-local calendar day with the same normalized
 * title, and the post has no external id yet. The SermonAudio id and
 * `_cpl_sa_match_linked` are stored on it. Non-empty local content and
 * existing scripture stay, including on the next sync. Title, date, and
 * audio still update. Zero matches, two matches, and a different external
 * id still insert a new sermon and leave the others alone.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Integration;

use CP_Library\Adapters\SermonAudio;
use CP_Library\Adapters\SermonAudioMatch;
use CP_Library\Models\Item as ItemModel;

/**
 * @covers \CP_Library\Adapters\SermonAudio::load_item
 * @covers \CP_Library\Adapters\SermonAudioMatch
 */
class SermonAudioMatchExistingTest extends TestCase {

	/**
	 * @var SermonAudio
	 */
	private $adapter;

	/**
	 * @var mixed
	 */
	private $timezone_before;

	public function set_up() {
		parent::set_up();
		$this->adapter          = new SermonAudio();
		$this->timezone_before  = get_option( 'timezone_string' );
	}

	public function tear_down() {
		update_option( 'timezone_string', $this->timezone_before );
		remove_filter( 'cpl_sermon_audio_match_existing', '__return_false' );
		remove_filter( 'update_post_metadata', array( $this, 'throw_when_snapshot_is_saved' ), 10 );
		remove_filter( 'delete_post_metadata', array( $this, 'throw_when_scripture_is_cleared' ), 10 );
		parent::tear_down();
	}

	/**
	 * A variation child copies the parent's title and date. It is not a
	 * second sermon, so it must not make the day's title look ambiguous,
	 * and a child that sits alone on the feed's day must not be overwritten.
	 */
	public function test_variation_children_are_not_match_candidates() {
		$day    = '2024-06-02 09:00:00';
		$parent = $this->existing_sermon( 'Sunday Grace', $day, 'Parent notes' );
		$this->set_scripture( $parent, 'John 3:16' );
		$child = $this->existing_sermon( 'Sunday Grace', $day, 'Child notes' );
		$this->set_parent( $child, $parent );

		$model = $this->import( $this->sermon( 'sa-child', 'Sunday Grace', '2024-06-02', 'From SermonAudio', 'Romans 8:28' ) );

		$this->assertSame( $parent, (int) $model->origin_id );
		$this->assertSame( array( $parent, $child ), $this->sermon_ids() );
		$this->assertSame( array( $parent ), $this->posts_with_external_id( 'sa-child' ) );
		$this->assertSame( '1', (string) get_post_meta( $parent, SermonAudioMatch::LINKED_META, true ) );
		$this->assertSame( 'Parent notes', get_post( $parent )->post_content );
		$this->assertContains( 'John 3:16', $this->passages( $parent ) );
		$this->assertNotContains( 'Romans 8:28', $this->passages( $parent ) );
		$this->assertSame( 'Child notes', get_post( $child )->post_content );
		$this->assertSame( '', (string) get_post_meta( $child, 'external_id', true ) );
		$this->assertSame( '', (string) get_post_meta( $child, SermonAudioMatch::LINKED_META, true ) );

		$other_day = $this->existing_sermon( 'Evening Grace', '2024-06-01 09:00:00', 'Parent on another day' );
		$only_child = $this->existing_sermon( 'Evening Grace', $day, 'Variant notes' );
		$this->set_parent( $only_child, $other_day );
		$this->set_scripture( $only_child, 'John 3:16' );

		$created = $this->import( $this->sermon( 'sa-child-day', 'Evening Grace', '2024-06-02', 'From SermonAudio', 'Romans 8:28' ) );
		$created_id = (int) $created->origin_id;

		$this->assertNotSame( $only_child, $created_id );
		$this->assertNotSame( $other_day, $created_id );
		$this->assertSame( 'Variant notes', get_post( $only_child )->post_content );
		$this->assertContains( 'John 3:16', $this->passages( $only_child ) );
		$this->assertSame( '', (string) get_post_meta( $only_child, 'external_id', true ) );
		$this->assertSame( '', (string) get_post_meta( $only_child, SermonAudioMatch::LINKED_META, true ) );
	}

	/**
	 * external_id is stored before scripture and the model finish saving.
	 * If that save throws, the post must not stay linked by external_id
	 * without the match marker. The next sync still has to keep the local
	 * notes and scripture.
	 */
	public function test_a_failed_link_rolls_back_so_the_next_sync_keeps_local_fields() {
		$post_id = $this->existing_sermon( 'Sunday Grace', '2024-06-02 09:00:00', 'Keep these notes' );
		$this->set_scripture( $post_id, 'John 3:16' );
		$sermon = $this->sermon( 'sa-rollback', 'Sunday Grace', '2024-06-02', 'From SermonAudio', 'Romans 8:28' );

		$throwing = new class() extends SermonAudio {
			public function process_cpl_data( $item, $cpl_data, $post_type ) {
				throw new \ChurchPlugins\Exception( 'save failed' );
			}
		};

		try {
			$throwing->load_item( $throwing->format_item( $sermon ), ItemModel::class );
			$this->fail( 'the save must throw' );
		} catch ( \ChurchPlugins\Exception $e ) {
			$this->assertStringContainsString( 'save failed', $e->getMessage() );
		}

		$this->assertSame( '', (string) get_post_meta( $post_id, 'external_id', true ) );
		$this->assertSame( '', (string) get_post_meta( $post_id, SermonAudioMatch::LINKED_META, true ) );
		$this->assertSame( '', get_post_meta( $post_id, SermonAudioMatch::SUPPLIED_META, true ) );
		$this->assertSame( 'Keep these notes', get_post( $post_id )->post_content );
		$this->assertContains( 'John 3:16', $this->passages( $post_id ) );

		$model = $this->import( $sermon );

		$this->assertSame( $post_id, (int) $model->origin_id );
		$this->assertSame( array( $post_id ), $this->sermon_ids() );
		$this->assertSame( 'Keep these notes', get_post( $post_id )->post_content );
		$this->assertContains( 'John 3:16', $this->passages( $post_id ) );
		$this->assertNotContains( 'Romans 8:28', $this->passages( $post_id ) );
		$this->assertSame( '1', (string) get_post_meta( $post_id, SermonAudioMatch::LINKED_META, true ) );
	}

	/**
	 * Saving the supplied snapshot happens after the post is written. If that
	 * step throws, the SermonAudio id from this attempt must not stay, or the
	 * next sync treats the post as its own and replaces local notes and scripture.
	 */
	public function test_a_snapshot_failure_rolls_back_so_the_next_sync_keeps_local_fields() {
		$post_id = $this->existing_sermon( 'Sunday Grace', '2024-06-02 09:00:00', 'Keep these notes' );
		$this->set_scripture( $post_id, 'John 3:16' );
		$sermon = $this->sermon( 'sa-snapshot', 'Sunday Grace', '2024-06-02', 'From SermonAudio', 'Romans 8:28' );

		add_filter( 'update_post_metadata', array( $this, 'throw_when_snapshot_is_saved' ), 10, 5 );

		try {
			$this->import( $sermon );
			$this->fail( 'the snapshot step must throw' );
		} catch ( \ChurchPlugins\Exception $e ) {
			$this->assertStringContainsString( 'snapshot failed', $e->getMessage() );
		} finally {
			remove_filter( 'update_post_metadata', array( $this, 'throw_when_snapshot_is_saved' ), 10 );
		}

		$this->assertSame( '', (string) get_post_meta( $post_id, 'external_id', true ) );
		$this->assertSame( '', (string) get_post_meta( $post_id, SermonAudioMatch::LINKED_META, true ) );
		$this->assertSame( '', get_post_meta( $post_id, SermonAudioMatch::SUPPLIED_META, true ) );
		$this->assertSame( 'Keep these notes', get_post( $post_id )->post_content );
		$this->assertContains( 'John 3:16', $this->passages( $post_id ) );

		$model = $this->import( $sermon );

		$this->assertSame( $post_id, (int) $model->origin_id );
		$this->assertSame( array( $post_id ), $this->sermon_ids() );
		$this->assertSame( 'Keep these notes', get_post( $post_id )->post_content );
		$this->assertContains( 'John 3:16', $this->passages( $post_id ) );
		$this->assertNotContains( 'Romans 8:28', $this->passages( $post_id ) );
	}

	/**
	 * Clearing scripture happens after the post is written and before the
	 * match marker. If that step throws, the next sync must still keep the
	 * local notes and scripture.
	 */
	public function test_a_scripture_failure_rolls_back_so_the_next_sync_keeps_local_fields() {
		$post_id = $this->existing_sermon( 'Sunday Grace', '2024-06-02 09:00:00', 'Keep these notes' );
		$this->set_scripture( $post_id, 'John 3:16' );
		$notes = get_post( $post_id )->post_content;
		update_post_meta(
			$post_id,
			SermonAudioMatch::SUPPLIED_META,
			array(
				'content'   => array(
					'feed'   => $notes,
					'stored' => $notes,
				),
				'scripture' => array(
					'feed'   => 'John 3:16',
					'stored' => 'John 3:16',
				),
			)
		);

		$clearing = $this->sermon( 'sa-scripture', 'Sunday Grace', '2024-06-02', $notes, null );
		$later    = $this->sermon( 'sa-scripture', 'Sunday Grace', '2024-06-02', 'From SermonAudio', 'Romans 8:28' );

		add_filter( 'delete_post_metadata', array( $this, 'throw_when_scripture_is_cleared' ), 10, 5 );

		try {
			$this->import( $clearing );
			$this->fail( 'the scripture step must throw' );
		} catch ( \ChurchPlugins\Exception $e ) {
			$this->assertStringContainsString( 'scripture failed', $e->getMessage() );
		} finally {
			remove_filter( 'delete_post_metadata', array( $this, 'throw_when_scripture_is_cleared' ), 10 );
		}

		$this->assertSame( '', (string) get_post_meta( $post_id, 'external_id', true ) );
		$this->assertSame( '', (string) get_post_meta( $post_id, SermonAudioMatch::LINKED_META, true ) );
		$this->assertSame( '', get_post_meta( $post_id, SermonAudioMatch::SUPPLIED_META, true ) );
		$this->assertSame( 'Keep these notes', get_post( $post_id )->post_content );
		$this->assertContains( 'John 3:16', $this->passages( $post_id ) );

		$model = $this->import( $later );

		$this->assertSame( $post_id, (int) $model->origin_id );
		$this->assertSame( array( $post_id ), $this->sermon_ids() );
		$this->assertSame( 'Keep these notes', get_post( $post_id )->post_content );
		$this->assertContains( 'John 3:16', $this->passages( $post_id ) );
		$this->assertNotContains( 'Romans 8:28', $this->passages( $post_id ) );
	}

	/**
	 * The first snapshot has to use the same scripture form later reads use.
	 * A book spelled "Psalm", or a range written with an en dash, is not a
	 * local edit. A later feed correction still applies. A different passage
	 * does not.
	 */
	public function test_scripture_snapshot_ignores_book_spelling_and_dash_style() {
		$book = $this->existing_sermon( 'Book Spelling', '2024-06-02 09:00:00', 'Local notes' );
		update_post_meta( $book, '_cp_scripture', array( 'Psalm 23' ) );

		$this->import( $this->sermon( 'sa-book', 'Book Spelling', '2024-06-02', 'From SermonAudio', 'Psalm 23' ) );
		$this->assertSame( array( 'Psalm 23' ), $this->passages( $book ) );

		$this->import( $this->sermon( 'sa-book', 'Book Spelling', '2024-06-02', 'From SermonAudio', 'Psalm 24' ) );
		$this->assertSame( array( 'Psalms 24' ), $this->passages( $book ) );
		$this->assertSame( 'Local notes', get_post( $book )->post_content );

		$dash = $this->existing_sermon( 'Dash Style', '2024-06-03 09:00:00', 'Dash notes' );
		update_post_meta( $dash, '_cp_scripture', array( 'Psalms 23:1–4' ) );

		$this->import( $this->sermon( 'sa-dash', 'Dash Style', '2024-06-03', 'Dash notes', 'Psalm 23:1-4' ) );
		$this->assertSame( array( 'Psalms 23:1–4' ), $this->passages( $dash ) );

		$this->import( $this->sermon( 'sa-dash', 'Dash Style', '2024-06-03', 'Dash notes', 'Psalm 23:1-6' ) );
		$this->assertSame( array( 'Psalms 23:1-6' ), $this->passages( $dash ) );

		$edited = $this->existing_sermon( 'Real Edit', '2024-06-04 09:00:00', 'Edited notes' );
		$this->set_scripture( $edited, 'John 3:16' );
		$this->import( $this->sermon( 'sa-edited', 'Real Edit', '2024-06-04', 'Edited notes', 'Psalm 23' ) );
		$this->import( $this->sermon( 'sa-edited', 'Real Edit', '2024-06-04', 'Edited notes', 'Psalm 24' ) );
		$this->assertContains( 'John 3:16', $this->passages( $edited ) );
		$this->assertNotContains( 'Psalms 24', $this->passages( $edited ) );
		$this->assertSame( 'Edited notes', get_post( $edited )->post_content );
	}

	public function test_one_normalized_title_on_that_day_links_and_keeps_nonempty_local_fields() {
		$day       = '2024-06-02 09:00:00';
		$decoy_id  = $this->existing_sermon( 'A different sermon', $day, 'Decoy notes', 'decoy-slug' );
		$post_id   = $this->existing_sermon( '  The Lord&#039;s   Prayer  ', $day, 'Placeholder', 'lords-prayer' );
		$this->store_content( $post_id, 'Keep this \\ path' );
		$this->set_scripture( $post_id, 'John 3:16' );

		$model = $this->import(
			$this->sermon(
				'sa-lords',
				"the lord's prayer",
				'2024-06-02',
				'From SermonAudio',
				'Romans 8:28',
				'https://cdn.example.com/audio/lords-prayer.mp3'
			)
		);

		$this->assertSame( $post_id, (int) $model->origin_id );
		$this->assertSame( array( $decoy_id, $post_id ), $this->sermon_ids() );
		$this->assertSame( array( $post_id ), $this->posts_with_external_id( 'sa-lords' ) );
		$this->assertSame( '1', (string) get_post_meta( $post_id, SermonAudioMatch::LINKED_META, true ) );
		$this->assertSame( '', (string) get_post_meta( $decoy_id, 'external_id', true ) );
		$this->assertSame( '', (string) get_post_meta( $decoy_id, SermonAudioMatch::LINKED_META, true ) );
		$this->assertSame( 'Decoy notes', get_post( $decoy_id )->post_content );

		$post = get_post( $post_id );
		$this->assertSame( 'Keep this \\ path', $post->post_content );
		$this->assertSame( "the lord's prayer", $post->post_title );
		$this->assertSame( '2024-06-02 12:00:00', $post->post_date );
		$this->assertSame( 'lords-prayer', $post->post_name );
		$this->assertContains( 'John 3:16', $this->passages( $post_id ) );
		$this->assertNotContains( 'Romans 8:28', $this->passages( $post_id ) );
		$this->assertSame( 'https://cdn.example.com/audio/lords-prayer.mp3', $model->get_meta_value( 'audio_url' ) );
	}

	public function test_zero_matches_creates_a_sermon() {
		$same_title_other_day = $this->existing_sermon( 'New Mercy', '2024-06-01 09:00:00', 'Yesterday' );
		$other_title_same_day = $this->existing_sermon( 'Something else', '2024-06-02 09:00:00', 'Other' );

		$model = $this->import( $this->sermon( 'sa-new', 'New Mercy', '2024-06-02', 'From SermonAudio' ) );

		$created = (int) $model->origin_id;
		$this->assertNotContains( $created, array( $same_title_other_day, $other_title_same_day ) );
		$this->assertSame( array( $created ), $this->posts_with_external_id( 'sa-new' ) );
		$this->assertSame( '', (string) get_post_meta( $created, SermonAudioMatch::LINKED_META, true ) );
		$this->assertSame( '', (string) get_post_meta( $same_title_other_day, 'external_id', true ) );
		$this->assertSame( '', (string) get_post_meta( $other_title_same_day, 'external_id', true ) );
		$this->assertSame( 'Yesterday', get_post( $same_title_other_day )->post_content );
		$this->assertSame( array( $same_title_other_day, $other_title_same_day, $created ), $this->sermon_ids() );
	}

	public function test_two_matches_create_a_sermon_and_do_not_link() {
		$day    = '2024-06-02 09:00:00';
		$first  = $this->existing_sermon( 'Sunday Grace', $day, 'First notes' );
		$second = $this->existing_sermon( 'sunday   grace', $day, 'Second notes' );
		$decoy  = $this->existing_sermon( 'Not this one', $day, 'Decoy' );

		$model   = $this->import( $this->sermon( 'sa-two', 'SUNDAY GRACE', '2024-06-02', 'From SermonAudio' ) );
		$created = (int) $model->origin_id;

		$this->assertSame( array( $first, $second, $decoy, $created ), $this->sermon_ids() );
		$this->assertSame( array( $created ), $this->posts_with_external_id( 'sa-two' ) );
		$this->assertSame( '', (string) get_post_meta( $created, SermonAudioMatch::LINKED_META, true ) );

		foreach ( array( $first, $second, $decoy ) as $post_id ) {
			$this->assertSame( '', (string) get_post_meta( $post_id, 'external_id', true ) );
			$this->assertSame( '', (string) get_post_meta( $post_id, SermonAudioMatch::LINKED_META, true ) );
		}

		$this->assertSame( 'First notes', get_post( $first )->post_content );
		$this->assertSame( 'Second notes', get_post( $second )->post_content );
	}

	public function test_a_sermon_with_a_different_external_id_is_not_linked() {
		$post_id = $this->existing_sermon( 'Sunday Grace', '2024-06-02 09:00:00', 'Keep me' );
		update_post_meta( $post_id, 'external_id', 'csv-1' );
		$this->set_scripture( $post_id, 'John 3:16' );

		$model   = $this->import( $this->sermon( 'sa-other', 'Sunday Grace', '2024-06-02', 'From SermonAudio', 'Romans 8:28' ) );
		$created = (int) $model->origin_id;

		$this->assertNotSame( $post_id, $created );
		$this->assertSame( array( $post_id, $created ), $this->sermon_ids() );
		$this->assertSame( 'csv-1', get_post_meta( $post_id, 'external_id', true ) );
		$this->assertSame( array( $created ), $this->posts_with_external_id( 'sa-other' ) );
		$this->assertSame( '', (string) get_post_meta( $post_id, SermonAudioMatch::LINKED_META, true ) );
		$this->assertSame( '', (string) get_post_meta( $created, SermonAudioMatch::LINKED_META, true ) );
		$this->assertSame( 'Keep me', get_post( $post_id )->post_content );
		$this->assertContains( 'John 3:16', $this->passages( $post_id ) );
		$this->assertNotContains( 'Romans 8:28', $this->passages( $post_id ) );
	}

	public function test_a_second_sync_stays_idempotent() {
		$post_id = $this->existing_sermon( 'Sunday Grace', '2024-06-02 09:00:00', 'Local notes', 'sunday-grace' );
		$this->set_scripture( $post_id, 'John 3:16' );
		$sermon = $this->sermon(
			'sa-once',
			'Sunday Grace',
			'2024-06-02',
			'From SermonAudio',
			'Romans 8:28',
			'https://cdn.example.com/audio/sunday-grace.mp3'
		);

		$first = $this->import( $sermon );
		$this->assertSame( $post_id, (int) $first->origin_id );

		$after_first = $this->snapshot( $post_id );
		$this->assertSame( 'Local notes', $after_first['content'] );
		$this->assertNotSame( 'From SermonAudio', $after_first['content'] );
		$this->assertContains( 'John 3:16', $after_first['passages'] );
		$this->assertSame( '1', $after_first['marker'] );
		$this->assertSame( '2024-06-02 12:00:00', $after_first['date'] );
		$this->assertSame( 'https://cdn.example.com/audio/sunday-grace.mp3', $first->get_meta_value( 'audio_url' ) );

		$second = $this->import( $sermon );

		$this->assertSame( $post_id, (int) $second->origin_id );
		$this->assertSame( array( $post_id ), $this->sermon_ids() );
		$this->assertSame( $after_first, $this->snapshot( $post_id ) );
		$this->assertSame( 'https://cdn.example.com/audio/sunday-grace.mp3', $second->get_meta_value( 'audio_url' ) );
	}

	public function test_an_empty_feed_field_does_not_overwrite_local_notes_or_scripture() {
		$post_id = $this->existing_sermon( 'Sunday Grace', '2024-06-02 09:00:00', 'Church notes' );
		$this->set_scripture( $post_id, 'John 3:16' );

		$this->import( $this->sermon( 'sa-empty-feed', 'Sunday Grace', '2024-06-02', '' ) );

		$this->assertSame( 'Church notes', get_post( $post_id )->post_content );
		$this->assertContains( 'John 3:16', $this->passages( $post_id ) );
		$this->assertSame(
			array(
				'content'   => array(
					'feed'   => '',
					'stored' => '',
				),
				'scripture' => array(
					'feed'   => '',
					'stored' => '',
				),
			),
			get_post_meta( $post_id, SermonAudioMatch::SUPPLIED_META, true )
		);
	}

	public function test_same_local_day_links_when_gmt_is_the_next_day() {
		$this->use_los_angeles();
		$local = '2024-03-10 23:30:00';
		$this->assertStringStartsWith( '2024-03-11', get_gmt_from_date( $local ) );

		$post_id = $this->existing_sermon( 'Boundary Grace', $local, 'Local notes' );
		$model   = $this->import( $this->sermon( 'sa-tz-late', 'Boundary Grace', '2024-03-10' ) );

		$this->assertSame( $post_id, (int) $model->origin_id );
		$this->assertSame( array( $post_id ), $this->posts_with_external_id( 'sa-tz-late' ) );
		$this->assertSame( '1', (string) get_post_meta( $post_id, SermonAudioMatch::LINKED_META, true ) );
	}

	public function test_local_midnight_at_the_start_of_the_preach_day_links() {
		$this->use_los_angeles();
		$local = '2024-03-10 00:00:00';
		$this->assertStringStartsWith( '2024-03-10', get_gmt_from_date( $local ) );

		$post_id = $this->existing_sermon( 'Boundary Grace', $local );
		$model   = $this->import( $this->sermon( 'sa-tz-start', 'Boundary Grace', '2024-03-10' ) );

		$this->assertSame( $post_id, (int) $model->origin_id );
		$this->assertSame( array( $post_id ), $this->sermon_ids() );
	}

	public function test_previous_local_day_does_not_link_when_gmt_is_the_preach_day() {
		$this->use_los_angeles();
		$local = '2024-03-09 20:00:00';
		$this->assertStringStartsWith( '2024-03-10', get_gmt_from_date( $local ) );

		$post_id = $this->existing_sermon( 'Boundary Grace', $local, 'Yesterday local' );
		$model   = $this->import( $this->sermon( 'sa-tz-gmt', 'Boundary Grace', '2024-03-10', 'From SermonAudio' ) );
		$created = (int) $model->origin_id;

		$this->assertNotSame( $post_id, $created );
		$this->assertSame( '', (string) get_post_meta( $post_id, 'external_id', true ) );
		$this->assertSame( '', (string) get_post_meta( $post_id, SermonAudioMatch::LINKED_META, true ) );
		$this->assertSame( 'Yesterday local', get_post( $post_id )->post_content );
		$this->assertSame( array( $created ), $this->posts_with_external_id( 'sa-tz-gmt' ) );
		$this->assertSame( '', (string) get_post_meta( $created, SermonAudioMatch::LINKED_META, true ) );
	}

	public function test_the_next_local_midnight_does_not_link() {
		$this->use_los_angeles();
		$local = '2024-03-11 00:00:00';

		$post_id = $this->existing_sermon( 'Boundary Grace', $local, 'Next day' );
		$model   = $this->import( $this->sermon( 'sa-tz-next', 'Boundary Grace', '2024-03-10' ) );
		$created = (int) $model->origin_id;

		$this->assertNotSame( $post_id, $created );
		$this->assertSame( '', (string) get_post_meta( $post_id, 'external_id', true ) );
		$this->assertSame( '', (string) get_post_meta( $post_id, SermonAudioMatch::LINKED_META, true ) );
		$this->assertSame( array( $post_id, $created ), $this->sermon_ids() );
	}

	public function test_two_batch_sermons_with_the_same_title_on_the_same_day_are_not_linked() {
		$existing = $this->existing_sermon( 'Sunday Worship', '2024-06-02 09:00:00', 'Local notes' );
		$morning  = $this->adapter->format_item( $this->sermon( 'sa-9am', 'Sunday Worship', '2024-06-02', 'Nine' ) );
		$late     = $this->adapter->format_item( $this->sermon( 'sa-11am', 'sunday   worship', '2024-06-02', 'Eleven' ) );
		$morning['post_date'] = '2024-06-02 09:00:00';
		$late['post_date']    = '2024-06-02 11:00:00';

		$batch = SermonAudioMatch::flag_batch_ambiguities( array( $morning, $late ) );

		$first  = $this->adapter->load_item( $batch[0], ItemModel::class );
		$second = $this->adapter->load_item( $batch[1], ItemModel::class );

		$this->assertNotSame( $existing, (int) $first->origin_id );
		$this->assertNotSame( $existing, (int) $second->origin_id );
		$this->assertNotSame( (int) $first->origin_id, (int) $second->origin_id );
		$this->assertSame( array( $existing, (int) $first->origin_id, (int) $second->origin_id ), $this->sermon_ids() );
		$this->assertSame( '', (string) get_post_meta( $existing, 'external_id', true ) );
		$this->assertSame( '', (string) get_post_meta( $existing, SermonAudioMatch::LINKED_META, true ) );
		$this->assertSame( 'Local notes', get_post( $existing )->post_content );
		$this->assertSame( array( (int) $first->origin_id ), $this->posts_with_external_id( 'sa-9am' ) );
		$this->assertSame( array( (int) $second->origin_id ), $this->posts_with_external_id( 'sa-11am' ) );
		$this->assertSame( '', (string) get_post_meta( $first->origin_id, SermonAudioMatch::LINKED_META, true ) );
		$this->assertSame( '', (string) get_post_meta( $second->origin_id, SermonAudioMatch::LINKED_META, true ) );
		$this->assertSame( 'publish', get_post_status( $first->origin_id ) );
		$this->assertSame( 'publish', get_post_status( $second->origin_id ) );
	}

	public function test_a_trashed_sermon_is_not_linked_or_republished() {
		$post_id = $this->existing_sermon( 'Sunday Worship', '2024-06-02 09:00:00', 'Trashed notes' );
		wp_trash_post( $post_id );

		$this->assertSame( 'trash', get_post_status( $post_id ) );

		$model   = $this->import( $this->sermon( 'sa-trash', 'Sunday Worship', '2024-06-02', 'From SermonAudio' ) );
		$created = (int) $model->origin_id;

		$this->assertNotSame( $post_id, $created );
		$this->assertSame( 'trash', get_post_status( $post_id ) );
		$this->assertSame( 'Trashed notes', get_post( $post_id )->post_content );
		$this->assertSame( 'Sunday Worship', get_post( $post_id )->post_title );
		$this->assertSame( '', (string) get_post_meta( $post_id, 'external_id', true ) );
		$this->assertSame( '', (string) get_post_meta( $post_id, SermonAudioMatch::LINKED_META, true ) );
		$this->assertSame( 'publish', get_post_status( $created ) );
		$this->assertSame( array( $created ), $this->posts_with_external_id( 'sa-trash' ) );
		$this->assertSame( '', (string) get_post_meta( $created, SermonAudioMatch::LINKED_META, true ) );
		$this->assertSame( array( $post_id, $created ), $this->sermon_ids() );
	}

	public function test_a_trashed_duplicate_does_not_block_linking_the_live_sermon() {
		$live   = $this->existing_sermon( 'Sunday Worship', '2024-06-02 09:00:00', 'Live notes' );
		$trashed = $this->existing_sermon( 'Sunday Worship', '2024-06-02 11:00:00', 'Trashed notes' );
		wp_trash_post( $trashed );

		$model = $this->import( $this->sermon( 'sa-live', 'Sunday Worship', '2024-06-02', 'From SermonAudio' ) );

		$this->assertSame( $live, (int) $model->origin_id );
		$this->assertSame( 'trash', get_post_status( $trashed ) );
		$this->assertSame( 'Trashed notes', get_post( $trashed )->post_content );
		$this->assertSame( '', (string) get_post_meta( $trashed, 'external_id', true ) );
		$this->assertSame( '', (string) get_post_meta( $trashed, SermonAudioMatch::LINKED_META, true ) );
		$this->assertSame( 'Live notes', get_post( $live )->post_content );
		$this->assertSame( '1', (string) get_post_meta( $live, SermonAudioMatch::LINKED_META, true ) );
		$this->assertSame( array( $live ), $this->posts_with_external_id( 'sa-live' ) );
	}

	public function test_publish_timestamp_without_a_preach_date_matches_the_site_day_around_midnight() {
		$this->use_los_angeles();

		$local     = new \DateTimeImmutable( '2024-03-10 23:30:00', new \DateTimeZone( 'America/Los_Angeles' ) );
		$timestamp = $local->getTimestamp();

		$this->assertStringStartsWith( '2024-03-11', gmdate( 'Y-m-d H:i:s', $timestamp ) );

		$post_id = $this->existing_sermon( 'Evening Worship', '2024-03-10 21:00:00', 'Local notes' );
		$sermon  = $this->sermon( 'sa-evening', 'Evening Worship', '', 'From SermonAudio' );
		$sermon->preachDate       = '';
		$sermon->publishTimestamp = $timestamp;

		$formatted = $this->adapter->format_item( $sermon );

		$this->assertSame( '2024-03-10 23:30:00', $formatted['post_date'] );

		$model = $this->adapter->load_item( $formatted, ItemModel::class );

		$this->assertSame( $post_id, (int) $model->origin_id );
		$this->assertSame( array( $post_id ), $this->sermon_ids() );
		$this->assertSame( '2024-03-10 21:00:00', get_post( $post_id )->post_date );
		$this->assertSame( '1', (string) get_post_meta( $post_id, SermonAudioMatch::LINKED_META, true ) );
		$this->assertSame( 'Local notes', get_post( $post_id )->post_content );
	}

	public function test_a_second_sync_keeps_the_post_date_saved_before_the_timezone_fallback() {
		$this->use_los_angeles();

		$local     = new \DateTimeImmutable( '2024-03-10 23:30:00', new \DateTimeZone( 'America/Los_Angeles' ) );
		$timestamp = $local->getTimestamp();
		$old_date  = gmdate( 'Y-m-d H:i:s', $timestamp );
		$new_date  = wp_date( 'Y-m-d H:i:s', $timestamp );

		$this->assertSame( '2024-03-11 06:30:00', $old_date );
		$this->assertSame( '2024-03-10 23:30:00', $new_date );

		$post_id = $this->existing_sermon( 'Evening Worship', $old_date, 'Already synced' );
		update_post_meta( $post_id, 'external_id', 'sa-old-date' );

		$sermon                   = $this->sermon( 'sa-old-date', 'Evening Worship', '', 'From SermonAudio' );
		$sermon->preachDate       = '';
		$sermon->publishTimestamp = $timestamp;

		$model = $this->adapter->load_item( $this->adapter->format_item( $sermon ), ItemModel::class );

		$this->assertSame( $post_id, (int) $model->origin_id );
		$this->assertSame( $old_date, get_post( $post_id )->post_date );
		$this->assertSame( array( $post_id ), $this->sermon_ids() );
	}

	public function test_a_new_sermon_without_a_preach_date_uses_the_site_day() {
		$this->use_los_angeles();

		$local     = new \DateTimeImmutable( '2024-03-10 23:30:00', new \DateTimeZone( 'America/Los_Angeles' ) );
		$timestamp = $local->getTimestamp();
		$sermon    = $this->sermon( 'sa-new-date', 'Evening Worship', '', 'From SermonAudio' );
		$sermon->preachDate       = '';
		$sermon->publishTimestamp = $timestamp;

		$model = $this->adapter->load_item( $this->adapter->format_item( $sermon ), ItemModel::class );

		$this->assertSame( '2024-03-10 23:30:00', get_post( $model->origin_id )->post_date );
		$this->assertSame( '', (string) get_post_meta( $model->origin_id, SermonAudioMatch::LINKED_META, true ) );
	}

	public function test_the_same_media_and_a_different_title_creates_a_new_post() {
		$post_id = $this->existing_sermon( 'Morning message', '2024-06-02 09:00:00', 'CSV notes' );
		update_post_meta( $post_id, 'audio_url', 'http://www.media.sermonaudio.com/audio/sermon.mp3?sermonID=1&download=true' );

		$model   = $this->import(
			$this->sermon(
				'sa-same-file',
				'Sunday Worship',
				'2024-06-02',
				'From SermonAudio',
				null,
				'https://media.sermonaudio.com/audio/sermon.mp3/'
			)
		);
		$created = (int) $model->origin_id;

		$this->assertNotSame( $post_id, $created );
		$this->assertSame( array( $post_id, $created ), $this->sermon_ids() );
		$this->assertSame( '', (string) get_post_meta( $post_id, 'external_id', true ) );
		$this->assertSame( '', (string) get_post_meta( $post_id, SermonAudioMatch::LINKED_META, true ) );
		$this->assertSame( 'CSV notes', get_post( $post_id )->post_content );
		$this->assertSame( 'Morning message', get_post( $post_id )->post_title );
		$this->assertSame( array( $created ), $this->posts_with_external_id( 'sa-same-file' ) );
		$this->assertSame( '', (string) get_post_meta( $created, SermonAudioMatch::LINKED_META, true ) );
	}

	public function test_two_same_day_sermons_with_the_same_title_and_url_are_not_linked() {
		$url   = 'https://media.sermonaudio.com/audio/sermon.mp3?sermonID=1&download=true';
		$first = $this->existing_sermon( 'Sunday Worship', '2024-06-02 09:00:00', 'First notes' );
		$second = $this->existing_sermon( 'sunday   worship', '2024-06-02 11:00:00', 'Second notes' );
		update_post_meta( $first, 'audio_url', $url );
		update_post_meta( $second, 'audio_url', 'https://www.media.sermonaudio.com/audio/sermon.mp3/' );

		$model   = $this->import(
			$this->sermon(
				'sa-two-urls',
				'Sunday Worship',
				'2024-06-02',
				'From SermonAudio',
				null,
				$url
			)
		);
		$created = (int) $model->origin_id;

		$this->assertNotContains( $created, array( $first, $second ) );
		$this->assertSame( array( $first, $second, $created ), $this->sermon_ids() );
		$this->assertSame( array( $created ), $this->posts_with_external_id( 'sa-two-urls' ) );
		$this->assertSame( '', (string) get_post_meta( $created, SermonAudioMatch::LINKED_META, true ) );

		foreach ( array( $first, $second ) as $post_id ) {
			$this->assertSame( '', (string) get_post_meta( $post_id, 'external_id', true ) );
			$this->assertSame( '', (string) get_post_meta( $post_id, SermonAudioMatch::LINKED_META, true ) );
		}

		$this->assertSame( 'First notes', get_post( $first )->post_content );
		$this->assertSame( 'Second notes', get_post( $second )->post_content );
	}

	public function test_a_different_file_on_the_same_host_is_not_linked() {
		$post_id = $this->existing_sermon( 'Sunday Worship', '2024-06-02 09:00:00', 'CSV notes' );
		update_post_meta( $post_id, 'audio_url', 'https://media.sermonaudio.com/audio/111.mp3' );

		$model   = $this->import(
			$this->sermon(
				'sa-other-file',
				'Sunday Worship',
				'2024-06-02',
				'From SermonAudio',
				null,
				'https://media.sermonaudio.com/audio/222.mp3?download=true'
			)
		);
		$created = (int) $model->origin_id;

		$this->assertNotSame( $post_id, $created );
		$this->assertSame( '', (string) get_post_meta( $post_id, 'external_id', true ) );
		$this->assertSame( '', (string) get_post_meta( $post_id, SermonAudioMatch::LINKED_META, true ) );
		$this->assertSame( 'CSV notes', get_post( $post_id )->post_content );
		$this->assertSame( array( $created ), $this->posts_with_external_id( 'sa-other-file' ) );
	}

	public function test_a_different_host_falls_back_to_the_title_and_day() {
		$post_id = $this->existing_sermon( 'Sunday Worship', '2024-06-02 09:00:00', 'CSV notes' );
		update_post_meta( $post_id, 'video_url', 'https://www.youtube.com/watch?v=abc123' );

		$model = $this->import(
			$this->sermon(
				'sa-youtube',
				'Sunday Worship',
				'2024-06-02',
				'From SermonAudio',
				null,
				'https://media.sermonaudio.com/audio/sermon.mp3'
			)
		);

		$this->assertSame( $post_id, (int) $model->origin_id );
		$this->assertSame( 'CSV notes', get_post( $post_id )->post_content );
		$this->assertSame( '1', (string) get_post_meta( $post_id, SermonAudioMatch::LINKED_META, true ) );
	}

	public function test_a_sermon_with_no_local_media_still_links_by_title() {
		$post_id = $this->existing_sermon( 'Sunday Worship', '2024-06-02 09:00:00', 'CSV notes' );

		$model = $this->import(
			$this->sermon(
				'sa-no-local-media',
				'Sunday Worship',
				'2024-06-02',
				'From SermonAudio',
				null,
				'https://media.sermonaudio.com/audio/sermon.mp3'
			)
		);

		$this->assertSame( $post_id, (int) $model->origin_id );
		$this->assertSame( array( $post_id ), $this->sermon_ids() );
	}

	public function test_an_old_sermonaudio_audio_url_matches_its_stored_redirect() {
		$post_id = $this->existing_sermon( 'Sunday Worship', '2024-06-02 09:00:00', 'CSV notes' );
		update_post_meta( $post_id, 'audio_url', 'https://media.sermonaudio.com/audio/old-sermon.mp3?sermonID=12345&download=true' );
		update_post_meta( $post_id, '_video_url_redirect', 'https://cdn.sermonaudio.com/media/old-sermon.mp3' );

		$model = $this->import(
			$this->sermon(
				'sa-redirect',
				'Sunday Worship',
				'2024-06-02',
				'From SermonAudio',
				null,
				'https://www.cdn.sermonaudio.com/media/old-sermon.mp3?token=abc'
			)
		);

		$this->assertSame( $post_id, (int) $model->origin_id );
		$this->assertSame( array( $post_id ), $this->sermon_ids() );
		$this->assertSame( 'CSV notes', get_post( $post_id )->post_content );
		$this->assertSame( '1', (string) get_post_meta( $post_id, SermonAudioMatch::LINKED_META, true ) );
	}

	public function test_the_match_filter_disables_linking() {
		add_filter( 'cpl_sermon_audio_match_existing', '__return_false' );
		$post_id = $this->existing_sermon( 'Sunday Grace', '2024-06-02 09:00:00', 'Keep me' );

		$model   = $this->import( $this->sermon( 'sa-off', 'Sunday Grace', '2024-06-02' ) );
		$created = (int) $model->origin_id;

		$this->assertNotSame( $post_id, $created );
		$this->assertSame( array( $post_id, $created ), $this->sermon_ids() );
		$this->assertSame( '', (string) get_post_meta( $post_id, 'external_id', true ) );
		$this->assertSame( '', (string) get_post_meta( $post_id, SermonAudioMatch::LINKED_META, true ) );
		$this->assertSame( array( $created ), $this->posts_with_external_id( 'sa-off' ) );
		$this->assertSame( 'Keep me', get_post( $post_id )->post_content );
	}

	public function test_a_speaker_with_the_same_title_and_day_is_not_linked() {
		$speaker_type = cp_library()->setup->post_types->speaker->post_type;
		$speaker_id   = wp_insert_post(
			array(
				'post_type'    => $speaker_type,
				'post_title'   => 'Sunday Grace',
				'post_status'  => 'publish',
				'post_date'    => '2024-06-02 09:00:00',
				'post_content' => 'Speaker bio',
			),
			true
		);
		$this->assertNotWPError( $speaker_id );

		$model = $this->import( $this->sermon( 'sa-speaker', 'Sunday Grace', '2024-06-02', 'From SermonAudio' ) );

		$this->assertSame( $speaker_type, get_post_type( $speaker_id ) );
		$this->assertSame( 'Speaker bio', get_post( $speaker_id )->post_content );
		$this->assertSame( '', (string) get_post_meta( $speaker_id, 'external_id', true ) );
		$this->assertSame( array( (int) $model->origin_id ), $this->sermon_ids() );
		$this->assertNotSame( (int) $speaker_id, (int) $model->origin_id );
	}

	public function test_a_supplied_field_is_cleared_when_sermonaudio_removes_it() {
		$post_id = $this->existing_sermon( 'Empty Vessel', '2024-06-02 09:00:00', '' );

		$this->import( $this->sermon( 'sa-clear', 'Empty Vessel', '2024-06-02', 'SA description', 'Romans 8:28' ) );
		$this->assertSame( 'SA description', get_post( $post_id )->post_content );
		$this->assertContains( 'Romans 8:28', $this->passages( $post_id ) );

		$this->import( $this->sermon( 'sa-clear', 'Empty Vessel', '2024-06-02', '' ) );

		$this->assertSame( '', get_post( $post_id )->post_content );
		$this->assertSame( array(), $this->passages( $post_id ) );
		$this->assertSame(
			array(
				'content'   => array(
					'feed'   => '',
					'stored' => '',
				),
				'scripture' => array(
					'feed'   => '',
					'stored' => '',
				),
			),
			get_post_meta( $post_id, SermonAudioMatch::SUPPLIED_META, true )
		);
	}

	public function test_an_empty_local_field_is_filled_at_link_time_and_then_follows_the_feed() {
		$post_id = $this->existing_sermon( 'Changed Vessel', '2024-06-02 09:00:00', '' );

		$this->import( $this->sermon( 'sa-change', 'Changed Vessel', '2024-06-02', 'SA description', 'Romans 8:28' ) );

		$this->assertSame( 'SA description', get_post( $post_id )->post_content );
		$this->assertContains( 'Romans 8:28', $this->passages( $post_id ) );

		$this->import( $this->sermon( 'sa-change', 'Changed Vessel', '2024-06-02', 'Changed later', 'John 1:1' ) );

		$this->assertSame( array( $post_id ), $this->sermon_ids() );
		$this->assertSame( 'Changed later', get_post( $post_id )->post_content );
		$this->assertContains( 'John 1:1', $this->passages( $post_id ) );
		$this->assertNotContains( 'Romans 8:28', $this->passages( $post_id ) );
	}

	public function test_a_locally_edited_field_is_not_cleared_when_sermonaudio_removes_it() {
		$post_id = $this->existing_sermon( 'Edited Vessel', '2024-06-02 09:00:00', '' );
		$this->import( $this->sermon( 'sa-edit', 'Edited Vessel', '2024-06-02', 'SA description', 'Romans 8:28' ) );
		$this->assertSame( 'SA description', get_post( $post_id )->post_content );

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'My notes',
			)
		);
		$this->set_scripture( $post_id, 'John 3:16' );

		$this->import( $this->sermon( 'sa-edit', 'Empty Vessel', '2024-06-02', '' ) );

		$this->assertSame( 'My notes', get_post( $post_id )->post_content );
		$this->assertContains( 'John 3:16', $this->passages( $post_id ) );
		$this->assertNotContains( 'Romans 8:28', $this->passages( $post_id ) );
		$this->assertSame( 'SA description', SermonAudioMatch::snapshot_part( get_post_meta( $post_id, SermonAudioMatch::SUPPLIED_META, true ), 'content', 'stored' ) );
		$this->assertSame( '', SermonAudioMatch::snapshot_part( get_post_meta( $post_id, SermonAudioMatch::SUPPLIED_META, true ), 'content', 'feed' ) );
	}

	public function test_a_sermon_with_no_snapshot_keeps_local_notes_and_records_the_feed() {
		$post_id = $this->existing_sermon( 'Sunday Grace', '2024-06-02 09:00:00', 'Local notes' );
		update_post_meta( $post_id, 'external_id', 'sa-already' );
		update_post_meta( $post_id, SermonAudioMatch::LINKED_META, '1' );
		$this->set_scripture( $post_id, 'John 3:16' );
		$this->assertSame( '', get_post_meta( $post_id, SermonAudioMatch::SUPPLIED_META, true ) );

		$model = $this->import( $this->sermon( 'sa-already', 'Sunday Grace', '2024-06-02', 'From SermonAudio', 'Romans 8:28' ) );

		$this->assertSame( $post_id, (int) $model->origin_id );
		$this->assertSame( array( $post_id ), $this->sermon_ids() );
		$this->assertSame( 'Local notes', get_post( $post_id )->post_content );
		$this->assertContains( 'John 3:16', $this->passages( $post_id ) );
		$this->assertNotContains( 'Romans 8:28', $this->passages( $post_id ) );

		$empty_id = $this->existing_sermon( 'Blank Notes', '2024-06-03 09:00:00', '' );
		update_post_meta( $empty_id, 'external_id', 'sa-blank' );
		update_post_meta( $empty_id, SermonAudioMatch::LINKED_META, '1' );

		$this->import( $this->sermon( 'sa-blank', 'Blank Notes', '2024-06-03', 'From SermonAudio', 'Romans 8:28' ) );

		$this->assertSame( 'From SermonAudio', get_post( $empty_id )->post_content );
		$this->assertContains( 'Romans 8:28', $this->passages( $empty_id ) );
		$this->assertSame( 'From SermonAudio', SermonAudioMatch::snapshot_part( get_post_meta( $post_id, SermonAudioMatch::SUPPLIED_META, true ), 'content', 'stored' ) );
		$this->assertSame( 'Romans 8:28', SermonAudioMatch::snapshot_part( get_post_meta( $post_id, SermonAudioMatch::SUPPLIED_META, true ), 'scripture', 'stored' ) );
	}

	public function test_local_notes_and_scripture_survive_repeated_feed_changes_after_linking() {
		$post_id = $this->existing_sermon( 'Case Seven', '2024-06-02 09:00:00', 'Local notes' );
		$this->set_scripture( $post_id, 'John 3:16' );

		$this->import( $this->sermon( 'sa-case-7', 'Case Seven', '2024-06-02', 'SA desc', 'Romans 8:28' ) );
		$this->import( $this->sermon( 'sa-case-7', 'Case Seven', '2024-06-02', 'SA desc 2', 'Romans 1:1' ) );
		$this->import( $this->sermon( 'sa-case-7', 'Case Seven', '2024-06-02', 'SA desc 3', 'Acts 2:1' ) );

		$this->assertSame( $post_id, (int) $this->posts_with_external_id( 'sa-case-7' )[0] );
		$this->assertSame( 'Local notes', get_post( $post_id )->post_content );
		$this->assertContains( 'John 3:16', $this->passages( $post_id ) );
		$this->assertNotContains( 'Romans 1:1', $this->passages( $post_id ) );
		$this->assertNotContains( 'Romans 8:28', $this->passages( $post_id ) );
		$this->assertSame( 'SA desc', SermonAudioMatch::snapshot_part( get_post_meta( $post_id, SermonAudioMatch::SUPPLIED_META, true ), 'content', 'stored' ) );
		$this->assertSame( 'Romans 8:28', SermonAudioMatch::snapshot_part( get_post_meta( $post_id, SermonAudioMatch::SUPPLIED_META, true ), 'scripture', 'stored' ) );
	}

	public function test_a_local_value_equal_to_sermonaudio_follows_later_feed_changes() {
		$post_id = $this->existing_sermon( 'Matching Notes', '2024-06-02 09:00:00', 'SA description' );
		$this->set_scripture( $post_id, 'Romans 8:28' );

		$this->import( $this->sermon( 'sa-match-notes', 'Matching Notes', '2024-06-02', 'SA description', 'Romans 8:28' ) );

		$this->assertSame( 'SA description', get_post( $post_id )->post_content );
		$this->assertContains( 'Romans 8:28', $this->passages( $post_id ) );

		$this->import( $this->sermon( 'sa-match-notes', 'Matching Notes', '2024-06-02', 'SA desc 2', 'Romans 1:1' ) );

		$this->assertSame( 'SA desc 2', get_post( $post_id )->post_content );
		$this->assertContains( 'Romans 1:1', $this->passages( $post_id ) );
		$this->assertNotContains( 'Romans 8:28', $this->passages( $post_id ) );
	}

	public function test_save_filter_normalization_does_not_look_like_a_local_edit() {
		add_filter( 'content_save_pre', array( $this, 'filter_synced_note_markup' ) );

		$raw     = '<p>See <a href="https://example.com/sermon">the notes</a>.</p>';
		$post_id = $this->existing_sermon( 'Filtered Notes', '2024-06-02 09:00:00', $raw );
		$stored  = get_post( $post_id )->post_content;

		$this->assertStringContainsString( '[synced]', $stored );
		$this->assertStringContainsString( 'https://example.com/sermon', $stored );

		try {
			$this->import( $this->sermon( 'sa-kses', 'Filtered Notes', '2024-06-02', $raw ) );

			$this->assertSame( $stored, get_post( $post_id )->post_content );
			$this->assertSame( $stored, SermonAudioMatch::snapshot_part( get_post_meta( $post_id, SermonAudioMatch::SUPPLIED_META, true ), 'content', 'stored' ) );

			$updated = '<p>Updated <a href="https://example.com/sermon">the notes</a>.</p>';
			$this->import( $this->sermon( 'sa-kses', 'Filtered Notes', '2024-06-02', $updated ) );

			$after = get_post( $post_id )->post_content;
			$this->assertStringContainsString( 'Updated', $after );
			$this->assertStringContainsString( '[synced]', $after );
			$this->assertStringContainsString( 'https://example.com/sermon', $after );
		} finally {
			remove_filter( 'content_save_pre', array( $this, 'filter_synced_note_markup' ) );
		}
	}

	public function test_a_plain_synced_sermon_takes_feed_notes_on_every_sync() {
		$post_id = $this->existing_sermon( 'Plain Sync', '2024-06-02 09:00:00', 'Old notes' );
		update_post_meta( $post_id, 'external_id', 'sa-plain' );
		$this->set_scripture( $post_id, 'John 3:16' );
		$this->assertSame( '', (string) get_post_meta( $post_id, SermonAudioMatch::LINKED_META, true ) );
		$this->assertSame( '', get_post_meta( $post_id, SermonAudioMatch::SUPPLIED_META, true ) );

		$this->import( $this->sermon( 'sa-plain', 'Plain Sync', '2024-06-02', 'New notes', 'Romans 8:28' ) );

		$this->assertSame( 'New notes', get_post( $post_id )->post_content );
		$this->assertContains( 'Romans 8:28', $this->passages( $post_id ) );
		$this->assertNotContains( 'John 3:16', $this->passages( $post_id ) );
		$this->assertSame( '', get_post_meta( $post_id, SermonAudioMatch::SUPPLIED_META, true ) );

		$this->import( $this->sermon( 'sa-plain', 'Plain Sync', '2024-06-02', 'Newer notes', 'Psalm 23' ) );

		$this->assertSame( 'Newer notes', get_post( $post_id )->post_content );
		$this->assertContains( 'Psalms 23', $this->passages( $post_id ) );
		$this->assertSame( '', get_post_meta( $post_id, SermonAudioMatch::SUPPLIED_META, true ) );
		$this->assertSame( '', (string) get_post_meta( $post_id, SermonAudioMatch::LINKED_META, true ) );
	}

	public function test_a_save_filter_does_not_freeze_later_feed_updates_on_the_queue() {
		$previous_user = get_current_user_id();
		wp_set_current_user( 0 );
		add_filter( 'content_save_pre', array( $this, 'filter_synced_note_markup' ) );

		$post_id = $this->existing_sermon( 'Filter Notes', '2024-06-02 09:00:00', '' );
		update_post_meta( $post_id, 'external_id', 'sa-filter' );
		update_post_meta( $post_id, SermonAudioMatch::LINKED_META, '1' );
		update_post_meta(
			$post_id,
			SermonAudioMatch::SUPPLIED_META,
			array(
				'content'   => array(
					'feed'   => '',
					'stored' => '',
				),
				'scripture' => array(
					'feed'   => '',
					'stored' => '',
				),
			)
		);

		$first = '<p>See <a href="https://example.com/sermon">the notes</a>.</p>';
		$second = '<p>Updated <a href="https://example.com/sermon">the notes</a>.</p>';

		try {
			$this->adapter->task( $this->adapter->format_item( $this->sermon( 'sa-filter', 'Filter Notes', '2024-06-02', $first ) ) );

			$after_first = get_post( $post_id )->post_content;
			$this->assertStringContainsString( 'the notes', $after_first );
			$this->assertStringContainsString( '[synced]', $after_first );
			$this->assertStringNotContainsString( 'Updated', $after_first );

			$this->adapter->task( $this->adapter->format_item( $this->sermon( 'sa-filter', 'Filter Notes', '2024-06-02', $second ) ) );
			$this->adapter->task( $this->adapter->format_item( $this->sermon( 'sa-filter', 'Filter Notes', '2024-06-02', $second ) ) );

			$after_later = get_post( $post_id )->post_content;
			$this->assertStringContainsString( 'Updated', $after_later );
			$this->assertStringContainsString( '[synced]', $after_later );
			$this->assertStringContainsString( 'https://example.com/sermon', $after_later );
		} finally {
			remove_filter( 'content_save_pre', array( $this, 'filter_synced_note_markup' ) );
			wp_set_current_user( $previous_user );
		}
	}

	/**
	 * Rewrite saved notes the way a cron sync can, without a logged-in user.
	 *
	 * @param mixed $content Post content being saved.
	 * @return mixed
	 */
	/**
	 * @param mixed  $check
	 * @param int    $object_id
	 * @param string $meta_key
	 * @return mixed
	 */
	public function throw_when_snapshot_is_saved( $check, $object_id, $meta_key, $meta_value = null, $prev_value = '' ) {
		if ( SermonAudioMatch::SUPPLIED_META === $meta_key ) {
			throw new \ChurchPlugins\Exception( 'snapshot failed' );
		}

		return $check;
	}

	/**
	 * @param mixed  $check
	 * @param int    $object_id
	 * @param string $meta_key
	 * @return mixed
	 */
	public function throw_when_scripture_is_cleared( $check, $object_id, $meta_key, $meta_value = '', $delete_all = false ) {
		if ( '_cp_scripture' === $meta_key ) {
			throw new \ChurchPlugins\Exception( 'scripture failed' );
		}

		return $check;
	}

	public function filter_synced_note_markup( $content ) {
		if ( ! is_string( $content ) || false !== strpos( $content, '[synced]' ) ) {
			return $content;
		}

		return str_replace( '</p>', ' [synced]</p>', $content );
	}

	/**
	 * @param string $title
	 * @param string $local_date MySQL post_date in the site timezone.
	 * @param string $content
	 * @param string $slug
	 * @return int
	 */
	/**
	 * @param int $child_id
	 * @param int $parent_id
	 */
	private function set_parent( $child_id, $parent_id ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->posts,
			array( 'post_parent' => $parent_id ),
			array( 'ID' => $child_id ),
			array( '%d' ),
			array( '%d' )
		);
		clean_post_cache( $child_id );
	}

	private function existing_sermon( $title, $local_date, $content = 'Local notes', $slug = '' ) {
		$args = array(
			'post_type'     => $this->post_type(),
			'post_title'    => $title,
			'post_content'  => $content,
			'post_status'   => 'publish',
			'post_date'     => $local_date,
			'post_date_gmt' => get_gmt_from_date( $local_date ),
		);

		if ( '' !== $slug ) {
			$args['post_name'] = $slug;
		}

		$post_id = wp_insert_post( $args, true );
		$this->assertNotWPError( $post_id );

		return (int) $post_id;
	}

	/**
	 * Store post_content with a literal backslash.
	 *
	 * wp_insert_post() unslashes input, so a backslash passed there would
	 * not be what the database holds. The matcher has to put that stored
	 * byte back through the same unslash.
	 *
	 * @param int    $post_id
	 * @param string $content Raw database value. A PHP '\\' pair is one backslash.
	 */
	private function store_content( $post_id, $content ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->posts,
			array( 'post_content' => $content ),
			array( 'ID' => $post_id ),
			array( '%s' ),
			array( '%d' )
		);
		clean_post_cache( $post_id );
	}

	/**
	 * @param int    $post_id
	 * @param string $passage
	 */
	private function set_scripture( $post_id, $passage ) {
		cp_library()->setup->taxonomies->scripture->update_object_scripture( $post_id, array( $passage ) );
	}

	/**
	 * @param int $post_id
	 * @return string[]
	 */
	private function passages( $post_id ) {
		$passages = cp_library()->setup->taxonomies->scripture->get_object_passages( $post_id );

		if ( ! is_array( $passages ) ) {
			$passages = array( $passages );
		}

		return array_values( $passages );
	}

	/**
	 * @param object $sermon
	 * @return ItemModel
	 */
	private function import( $sermon ) {
		return $this->adapter->load_item( $this->adapter->format_item( $sermon ), ItemModel::class );
	}

	/**
	 * @param string $external_id
	 * @param string $title
	 * @param string $preach_date Y-m-d.
	 * @param string $content
	 * @param string $bible
	 * @param string $audio
	 * @return object
	 */
	private function sermon( $external_id, $title, $preach_date, $content = 'From SermonAudio', $bible = null, $audio = null ) {
		$sermon = (object) array(
			'sermonID'         => $external_id,
			'displayTitle'     => $title,
			'fullTitle'        => $title,
			'preachDate'       => $preach_date,
			'publishTimestamp' => 1700000000,
			'moreInfoText'     => $content,
			'hasAudio'         => null !== $audio,
			'hasVideo'         => false,
			'bibleText'        => $bible,
			'eventType'        => null,
		);

		if ( null !== $audio ) {
			$sermon->media = (object) array(
				'audio' => array(
					(object) array(
						'downloadURL' => $audio,
					),
				),
			);
		}

		return $sermon;
	}

	/**
	 * @param int $post_id
	 * @return array
	 */
	private function snapshot( $post_id ) {
		$post = get_post( $post_id );

		return array(
			'content'  => $post->post_content,
			'title'    => $post->post_title,
			'date'     => $post->post_date,
			'slug'     => $post->post_name,
			'passages' => $this->passages( $post_id ),
			'marker'   => (string) get_post_meta( $post_id, SermonAudioMatch::LINKED_META, true ),
			'external' => (string) get_post_meta( $post_id, 'external_id', true ),
		);
	}

	/**
	 * @return int[]
	 */
	private function sermon_ids() {
		$ids = get_posts(
			array(
				'post_type'      => $this->post_type(),
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		return array_map( 'intval', $ids );
	}

	/**
	 * @param string $external_id
	 * @return int[]
	 */
	private function posts_with_external_id( $external_id ) {
		$ids = get_posts(
			array(
				'post_type'      => $this->post_type(),
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

	/**
	 * @return string
	 */
	private function post_type() {
		return cp_library()->setup->post_types->item->post_type;
	}

	private function use_los_angeles() {
		update_option( 'timezone_string', 'America/Los_Angeles' );
	}
}
