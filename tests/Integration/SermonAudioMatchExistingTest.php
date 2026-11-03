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
		parent::tear_down();
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

	public function test_empty_content_and_scripture_are_filled_from_sermonaudio_and_then_kept() {
		$post_id = $this->existing_sermon( 'Empty Vessel', '2024-06-02 09:00:00', '' );

		$this->import( $this->sermon( 'sa-empty', 'Empty Vessel', '2024-06-02', 'SA description', 'Romans 8:28' ) );

		$this->assertSame( array( $post_id ), $this->sermon_ids() );
		$this->assertSame( 'SA description', get_post( $post_id )->post_content );
		$this->assertContains( 'Romans 8:28', $this->passages( $post_id ) );
		$this->assertSame( '1', (string) get_post_meta( $post_id, SermonAudioMatch::LINKED_META, true ) );

		$this->import( $this->sermon( 'sa-empty', 'Empty Vessel', '2024-06-02', 'Changed later', 'John 1:1' ) );

		$this->assertSame( array( $post_id ), $this->sermon_ids() );
		$this->assertSame( 'SA description', get_post( $post_id )->post_content );
		$this->assertContains( 'Romans 8:28', $this->passages( $post_id ) );
		$this->assertNotContains( 'John 1:1', $this->passages( $post_id ) );
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
		$this->assertSame( '2024-03-10 23:30:00', get_post( $post_id )->post_date );
		$this->assertSame( '1', (string) get_post_meta( $post_id, SermonAudioMatch::LINKED_META, true ) );
		$this->assertSame( 'Local notes', get_post( $post_id )->post_content );
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

	public function test_a_sermon_already_linked_by_external_id_still_takes_sermonaudio_content() {
		$post_id = $this->existing_sermon( 'Sunday Grace', '2024-06-02 09:00:00', 'Local notes' );
		update_post_meta( $post_id, 'external_id', 'sa-already' );
		$this->set_scripture( $post_id, 'John 3:16' );

		$model = $this->import( $this->sermon( 'sa-already', 'Sunday Grace', '2024-06-02', 'From SermonAudio', 'Romans 8:28' ) );

		$this->assertSame( $post_id, (int) $model->origin_id );
		$this->assertSame( array( $post_id ), $this->sermon_ids() );
		$this->assertSame( 'From SermonAudio', get_post( $post_id )->post_content );
		$this->assertContains( 'Romans 8:28', $this->passages( $post_id ) );
		$this->assertNotContains( 'John 3:16', $this->passages( $post_id ) );
		$this->assertSame( '', (string) get_post_meta( $post_id, SermonAudioMatch::LINKED_META, true ) );
	}

	/**
	 * @param string $title
	 * @param string $local_date MySQL post_date in the site timezone.
	 * @param string $content
	 * @param string $slug
	 * @return int
	 */
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
