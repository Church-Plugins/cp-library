<?php
/**
 * SermonAudio sync must not create a second sermon for one that already exists.
 *
 * A CSV import stores the preach date at 09:00 and does not set external_id.
 * SermonAudio stores that preach date at noon and previously inserted a new
 * cpl_item whenever external_id meta was missing. One normalized title on
 * that calendar day, with no external_id or the same one, is linked: the
 * item ID, external_id meta, and this add-on's linked meta are set. Zero
 * matches, two or more matches, a different external_id, and a different
 * calendar day are logged (title, date, candidate IDs) and left alone.
 *
 * A sermon this add-on linked keeps its post content and scripture on the
 * linking sync and on a later sync. A sermon that already had an external_id
 * and was not linked still receives SermonAudio content and scripture.
 *
 * @package CP_SermonAudio_Match
 */

namespace CP_SermonAudio_Match\Tests\Integration;

use CP_Library\Adapters\SermonAudio;
use CP_Library\Models\Item as ItemModel;
use CP_Library\Tests\Integration\TestCase;
use CP_SermonAudio_Match\Matcher;

/**
 * @covers \CP_SermonAudio_Match\Matcher::filter_item
 * @covers \CP_SermonAudio_Match\Matcher::keep_linked_content
 * @covers \CP_SermonAudio_Match\Matcher::posts_on_day
 */
class MatchExistingTest extends TestCase {

	/**
	 * @var SermonAudio
	 */
	private $adapter;

	public function set_up() {
		parent::set_up();
		$this->adapter = new SermonAudio();
		Matcher::reset_skipped();
	}

	public function test_one_match_on_the_same_day_at_a_different_time_links_without_a_duplicate() {
		$post_type = cp_library()->setup->post_types->item->post_type;
		$post_id   = $this->sermon_post( 'The Good Shepherd', '2024-05-12 09:00:00' );
		$decoy_id  = $this->sermon_post( 'Something Else', '2024-05-12 09:00:00' );

		$formatted = $this->adapter->format_item( $this->sermon( 'sa-noon', 'The Good Shepherd', 'The Good Shepherd', '2024-05-12' ) );

		$this->assertSame( '2024-05-12 12:00:00', $formatted['post_date'] );

		$after_first = apply_filters( 'cp_library_sermon_audio_item', $formatted );

		$this->assertSame( $post_id, $after_first['ID'] );
		$this->assertSame( 'sa-noon', get_post_meta( $post_id, 'external_id', true ) );
		$this->assertSame( '1', get_post_meta( $post_id, Matcher::LINKED_META, true ) );

		$model = $this->sync_formatted( $after_first );

		$this->assertSame( $post_id, (int) $model->origin_id );
		$this->assertSame( array( $post_id ), $this->posts_with_external_id( $post_type, 'sa-noon' ) );
		$this->assertSame( '', (string) get_post_meta( $decoy_id, 'external_id', true ) );
		$this->assertSame( array(), Matcher::$skipped );
	}

	public function test_a_normalized_title_on_the_same_day_links() {
		$post_id = $this->sermon_post( "It\u{2019}s a Good Shepherd...", '2024-05-12 09:00:00' );

		$model = $this->sync( $this->sermon( 'sa-norm', "It's a Good...", "It's a Good Shepherd", '2024-05-12' ) );

		$this->assertSame( $post_id, (int) $model->origin_id );
		$this->assertSame( '1', get_post_meta( $post_id, Matcher::LINKED_META, true ) );
		$this->assertSame( array(), Matcher::$skipped );
	}

	public function test_display_title_is_used_when_the_full_title_is_blank() {
		$post_id = $this->sermon_post( 'Morning Grace', '2024-05-12 09:00:00' );

		$model = $this->sync( $this->sermon( 'sa-display', 'Morning Grace', '   ', '2024-05-12' ) );

		$this->assertSame( $post_id, (int) $model->origin_id );
	}

	public function test_zero_matches_logs_and_imports_a_new_sermon() {
		$post_type = cp_library()->setup->post_types->item->post_type;
		$existing  = $this->sermon_post( 'Already Here', '2024-05-12 09:00:00', 'CSV notes stay.' );

		$model = $this->sync(
			$this->sermon( 'sa-new', 'Brand New Sermon', 'Brand New Sermon', '2024-05-12', 'SermonAudio body.' )
		);

		$this->assertNotSame( $existing, (int) $model->origin_id );
		$this->assertSame( 'SermonAudio body.', get_post( $model->origin_id )->post_content );
		$this->assertSame( '', (string) get_post_meta( $existing, 'external_id', true ) );
		$this->assertSame( '', (string) get_post_meta( $existing, Matcher::LINKED_META, true ) );
		$this->assertSame( 'CSV notes stay.', get_post( $existing )->post_content );
		$this->assertSame(
			array(
				array(
					'title'         => 'Brand New Sermon',
					'date'          => '2024-05-12',
					'candidate_ids' => array(),
					'reason'        => 'none',
				),
			),
			Matcher::$skipped
		);
		$this->assertSame( array( (int) $model->origin_id ), $this->posts_with_external_id( $post_type, 'sa-new' ) );
	}

	public function test_two_matches_log_candidate_ids_and_do_not_link() {
		$first  = $this->sermon_post( 'The Good Shepherd', '2024-05-12 09:00:00', 'First notes.' );
		$second = $this->sermon_post( 'The Good Shepherd!', '2024-05-12 18:00:00', 'Second notes.' );

		$model = $this->sync( $this->sermon( 'sa-multi', 'The Good Shepherd', 'The Good Shepherd', '2024-05-12' ) );

		$expected_ids = array( $first, $second );
		sort( $expected_ids );

		$this->assertNotContains( (int) $model->origin_id, array( $first, $second ) );
		$this->assertSame( '', (string) get_post_meta( $first, 'external_id', true ) );
		$this->assertSame( '', (string) get_post_meta( $second, 'external_id', true ) );
		$this->assertSame( '', (string) get_post_meta( $first, Matcher::LINKED_META, true ) );
		$this->assertSame( 'First notes.', get_post( $first )->post_content );
		$this->assertSame( 'Second notes.', get_post( $second )->post_content );
		$this->assertSame(
			array(
				array(
					'title'         => 'The Good Shepherd',
					'date'          => '2024-05-12',
					'candidate_ids' => $expected_ids,
					'reason'        => 'multiple',
				),
			),
			Matcher::$skipped
		);
	}

	public function test_a_different_external_id_is_not_linked() {
		$existing = $this->sermon_post( 'The Good Shepherd', '2024-05-12 09:00:00', 'Keep me.' );
		update_post_meta( $existing, 'external_id', 'sa-other' );

		$model = $this->sync(
			$this->sermon( 'sa-new', 'The Good Shepherd', 'The Good Shepherd', '2024-05-12', 'Incoming body.' )
		);

		$this->assertNotSame( $existing, (int) $model->origin_id );
		$this->assertSame( 'sa-other', get_post_meta( $existing, 'external_id', true ) );
		$this->assertSame( '', (string) get_post_meta( $existing, Matcher::LINKED_META, true ) );
		$this->assertSame( 'Keep me.', get_post( $existing )->post_content );
		$this->assertSame( 'sa-new', get_post_meta( $model->origin_id, 'external_id', true ) );
		$this->assertSame(
			array(
				array(
					'title'         => 'The Good Shepherd',
					'date'          => '2024-05-12',
					'candidate_ids' => array( $existing ),
					'reason'        => 'different_external_id',
				),
			),
			Matcher::$skipped
		);
	}

	public function test_a_sermon_on_a_different_day_is_not_linked() {
		$existing = $this->sermon_post( 'The Good Shepherd', '2024-05-11 09:00:00' );

		$model = $this->sync( $this->sermon( 'sa-next', 'The Good Shepherd', 'The Good Shepherd', '2024-05-12' ) );

		$this->assertNotSame( $existing, (int) $model->origin_id );
		$this->assertSame( '', (string) get_post_meta( $existing, 'external_id', true ) );
		$this->assertSame(
			array(
				array(
					'title'         => 'The Good Shepherd',
					'date'          => '2024-05-12',
					'candidate_ids' => array(),
					'reason'        => 'none',
				),
			),
			Matcher::$skipped
		);
	}

	public function test_a_linked_sermon_keeps_content_and_scripture_after_resync() {
		global $wpdb;

		$post_id = $this->sermon_post( 'The Good Shepherd', '2024-05-12 09:00:00', 'placeholder' );

		// Write the body straight to the posts table. wp_insert_post() unslashes
		// its input, so a backslash passed through the factory would not be
		// what landed in the database. The sync has to put this exact value back.
		$csv_content = 'CSV body with a backslash \\ and "quotes" and <em>emphasis</em>.';
		$wpdb->update(
			$wpdb->posts,
			array( 'post_content' => $csv_content ),
			array( 'ID' => $post_id )
		);
		clean_post_cache( $post_id );

		$item = ItemModel::get_instance_from_origin( $post_id );
		$item->update_scripture( array( 'John 10:11' ) );
		$scripture_before = get_post_meta( $post_id, '_cp_scripture', true );
		$this->assertSame( $csv_content, get_post( $post_id )->post_content );

		$this->sync(
			$this->sermon(
				'sa-keep',
				'The Good Shepherd',
				'The Good Shepherd',
				'2024-05-12',
				'SermonAudio description that must not replace the CSV notes.',
				'Psalm 23'
			)
		);

		$this->assertSame( $csv_content, get_post( $post_id )->post_content );
		$this->assertSame( $scripture_before, get_post_meta( $post_id, '_cp_scripture', true ) );
		$this->assertSame( '1', get_post_meta( $post_id, Matcher::LINKED_META, true ) );
		$this->assertSame( array( $post_id ), $this->posts_with_external_id( 'cpl_item', 'sa-keep' ) );

		Matcher::reset_skipped();

		$this->sync(
			$this->sermon(
				'sa-keep',
				'The Good Shepherd',
				'The Good Shepherd',
				'2024-05-12',
				'A later sync description.',
				'Romans 8:28'
			)
		);

		$this->assertSame( $csv_content, get_post( $post_id )->post_content );
		$this->assertSame( $scripture_before, get_post_meta( $post_id, '_cp_scripture', true ) );
		$this->assertSame( array( $post_id ), $this->posts_with_external_id( 'cpl_item', 'sa-keep' ) );
		$this->assertSame( array(), Matcher::$skipped );
	}

	public function test_an_unlinked_sermon_still_receives_content_and_scripture() {
		$post_id = $this->sermon_post( 'The Good Shepherd', '2024-05-12 12:00:00', 'Original SermonAudio body.' );
		update_post_meta( $post_id, 'external_id', 'sa-plain' );
		$item = ItemModel::get_instance_from_origin( $post_id );
		$item->update_scripture( array( 'John 10:11' ) );

		$this->assertSame( '', (string) get_post_meta( $post_id, Matcher::LINKED_META, true ) );

		$model = $this->sync(
			$this->sermon(
				'sa-plain',
				'The Good Shepherd',
				'The Good Shepherd',
				'2024-05-12',
				'Replacement body from SermonAudio.',
				'Psalm 23'
			)
		);

		$this->assertSame( $post_id, (int) $model->origin_id );
		$this->assertSame( 'Replacement body from SermonAudio.', get_post( $post_id )->post_content );
		$this->assertSame( array( 'Psalms 23' ), get_post_meta( $post_id, '_cp_scripture', true ) );
		$this->assertSame( '', (string) get_post_meta( $post_id, Matcher::LINKED_META, true ) );
		$this->assertSame( array(), Matcher::$skipped );
	}

	/**
	 * @param string $title
	 * @param string $post_date MySQL post_date. CSV imports use 09:00:00.
	 * @param string $content
	 * @return int
	 */
	private function sermon_post( $title, $post_date, $content = 'Existing notes.' ) {
		return (int) self::factory()->post->create(
			array(
				'post_type'    => cp_library()->setup->post_types->item->post_type,
				'post_title'   => $title,
				'post_date'    => $post_date,
				'post_content' => $content,
				'post_status'  => 'publish',
			)
		);
	}

	/**
	 * @param object $sermon SermonAudio payload.
	 * @return ItemModel
	 */
	private function sync( $sermon ) {
		$formatted = $this->adapter->format_item( $sermon );

		return $this->sync_formatted( $formatted );
	}

	/**
	 * Run both filters the queue path runs, then load_item().
	 *
	 * cp_library_sermon_audio_item is also the only filter the CLI path runs.
	 * The single-match test asserts that filter alone sets the ID.
	 *
	 * @param array $formatted Formatted sermon.
	 * @return ItemModel
	 */
	private function sync_formatted( $formatted ) {
		$formatted = apply_filters( 'cp_library_sermon_audio_item', $formatted );
		$formatted = apply_filters( 'cpl_sermon_audio_adapter_item', $formatted, $this->adapter );

		return $this->adapter->load_item( $formatted, ItemModel::class );
	}

	/**
	 * @param string $external_id
	 * @param string $display
	 * @param string $full
	 * @param string $preach_date
	 * @param string $more_info
	 * @param string $bible_text
	 * @return object
	 */
	private function sermon( $external_id, $display, $full, $preach_date, $more_info = 'SermonAudio body.', $bible_text = '' ) {
		return (object) array(
			'sermonID'         => $external_id,
			'displayTitle'     => $display,
			'fullTitle'        => $full,
			'preachDate'       => $preach_date,
			'publishTimestamp' => 1700000000,
			'moreInfoText'     => $more_info,
			'hasAudio'         => false,
			'hasVideo'         => false,
			'bibleText'        => $bible_text,
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
