<?php
/**
 * SermonAudio sync must not duplicate a speaker or series the site already has.
 *
 * Speakers and series were found only by SermonAudio id. A site that entered
 * "John Smith" or the "Romans" series by hand or by CSV got a second copy on
 * the first sync, and every synced sermon moved to the copy. A sermon the
 * matcher linked also kept its local series and gained SermonAudio's, so it
 * was listed in two series (#247).
 *
 * The one local speaker or series with the same normalized name is now
 * updated instead. Two local posts with that name, or one with a different
 * SermonAudio id, still insert a new post. On a linked sermon the SermonAudio
 * series replaces the local one. A sermon the sync created still has the
 * series added, as before.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Integration;

use CP_Library\Adapters\SermonAudio;
use CP_Library\Adapters\SermonAudioMatch;
use CP_Library\Models\Item as ItemModel;
use CP_Library\Models\ItemType as ItemTypeModel;
use CP_Library\Models\Speaker as SpeakerModel;

/**
 * @covers \CP_Library\Adapters\SermonAudio::load_item
 * @covers \CP_Library\Adapters\SermonAudio::add_attachment
 * @covers \CP_Library\Adapters\SermonAudioMatch::find_named_link
 */
class SermonAudioSpeakerSeriesMatchTest extends TestCase {

	/**
	 * @var SermonAudio
	 */
	private $adapter;

	public function set_up() {
		parent::set_up();
		$this->adapter = new SermonAudio();
	}

	public function tear_down() {
		remove_filter( 'cpl_sermon_audio_match_existing', '__return_false' );

		// ItemType queues series date updates for shutdown. The series in
		// these tests are rolled back by then, so drop the queue.
		$queue = new \ReflectionProperty( \CP_Library\Setup\PostTypes\ItemType::class, '_update_dates' );
		$queue->setAccessible( true );
		$queue->setValue( null, array() );

		parent::tear_down();
	}

	public function test_a_local_speaker_with_the_same_name_is_updated_not_duplicated() {
		$local = $this->local_post( $this->speaker_type(), 'John Smith', 'Local bio' );

		$model = $this->load_speaker( 'sa-spk-1', '  john   SMITH ', 'SermonAudio bio' );

		$this->assertSame( $local, (int) $model->origin_id );
		$this->assertSame( array( $local ), $this->post_ids( $this->speaker_type() ) );
		$this->assertSame( 'sa-spk-1', (string) get_post_meta( $local, 'external_id', true ) );
		$this->assertSame( 'SermonAudio bio', get_post( $local )->post_content );
	}

	public function test_two_local_speakers_with_the_name_insert_a_new_speaker() {
		$first  = $this->local_post( $this->speaker_type(), 'John Smith' );
		$second = $this->local_post( $this->speaker_type(), 'John Smith' );

		$model = $this->load_speaker( 'sa-spk-2', 'John Smith' );

		$this->assertNotContains( (int) $model->origin_id, array( $first, $second ) );
		$this->assertCount( 3, $this->post_ids( $this->speaker_type() ) );
		$this->assertSame( '', (string) get_post_meta( $first, 'external_id', true ) );
		$this->assertSame( '', (string) get_post_meta( $second, 'external_id', true ) );
	}

	public function test_a_speaker_with_another_sermonaudio_id_is_not_taken_over() {
		$other = $this->local_post( $this->speaker_type(), 'John Smith' );
		update_post_meta( $other, 'external_id', 'sa-spk-other' );

		$model = $this->load_speaker( 'sa-spk-3', 'John Smith' );

		$this->assertNotSame( $other, (int) $model->origin_id );
		$this->assertSame( 'sa-spk-other', (string) get_post_meta( $other, 'external_id', true ) );
	}

	public function test_the_match_filter_disables_speaker_linking() {
		add_filter( 'cpl_sermon_audio_match_existing', '__return_false' );
		$local = $this->local_post( $this->speaker_type(), 'John Smith' );

		$model = $this->load_speaker( 'sa-spk-4', 'John Smith' );

		$this->assertNotSame( $local, (int) $model->origin_id );
		$this->assertSame( '', (string) get_post_meta( $local, 'external_id', true ) );
	}

	public function test_a_local_series_with_the_same_name_is_updated_not_duplicated() {
		$local = $this->local_post( $this->series_type(), 'Romans', 'Local description' );

		$model = $this->load_series( 'sa-ser-1', 'romans' );

		$this->assertSame( $local, (int) $model->origin_id );
		$this->assertSame( array( $local ), $this->post_ids( $this->series_type() ) );
		$this->assertSame( 'sa-ser-1', (string) get_post_meta( $local, 'external_id', true ) );
	}

	public function test_a_linked_sermon_has_its_series_replaced_not_added() {
		$no_series = ItemTypeModel::get_instance_from_origin( $this->local_post( $this->series_type(), 'No Series' ) );
		$sermon_id = $this->local_post( ItemModel::get_prop( 'post_type' ), 'Grace Abounds', 'Local notes', '2024-06-02 09:00:00' );
		$sermon    = ItemModel::get_instance_from_origin( $sermon_id );
		$sermon->update_types( array( $no_series->id ) );

		$series = $this->load_series( 'sa-ser-2', 'Romans' );
		$linked = $this->adapter->load_item( $this->adapter->format_item( $this->sermon( 'sa-ser-sermon', 'Grace Abounds', '2024-06-02' ) ), ItemModel::class );

		$this->assertSame( $sermon_id, (int) $linked->origin_id );
		$this->assertTrue( SermonAudioMatch::is_match_linked( $sermon_id ) );

		$this->adapter->process_attachment( $linked, 'sa-ser-2', 'cpl_item_type' );

		$this->assertSame( array( (int) ItemTypeModel::get_instance_from_origin( $series->origin_id )->id ), array_map( 'intval', ItemModel::get_instance_from_origin( $sermon_id )->get_types() ) );
	}

	public function test_a_synced_sermon_still_has_the_series_added() {
		$local_series = ItemTypeModel::get_instance_from_origin( $this->local_post( $this->series_type(), 'Local Extra' ) );
		$series       = $this->load_series( 'sa-ser-3', 'Romans' );
		$created      = $this->adapter->load_item( $this->adapter->format_item( $this->sermon( 'sa-ser-new', 'Brand New Sermon', '2024-06-09' ) ), ItemModel::class );

		$this->assertFalse( SermonAudioMatch::is_match_linked( (int) $created->origin_id ) );

		$created->update_types( array( $local_series->id ) );
		$this->adapter->process_attachment( $created, 'sa-ser-3', 'cpl_item_type' );

		$types = array_map( 'intval', ItemModel::get_instance_from_origin( $created->origin_id )->get_types() );
		sort( $types );
		$expected = array( (int) $local_series->id, (int) ItemTypeModel::get_instance_from_origin( $series->origin_id )->id );
		sort( $expected );

		$this->assertSame( $expected, $types );
	}

	private function load_speaker( $id, $name, $bio = '' ) {
		$speaker = (object) array(
			'speakerID'   => $id,
			'displayName' => $name,
			'bio'         => $bio,
			'portraitURL' => '',
		);

		return $this->adapter->load_item( $this->adapter->format_speaker( $speaker ), SpeakerModel::class );
	}

	private function load_series( $id, $title ) {
		$series = (object) array(
			'seriesID'    => $id,
			'title'       => $title,
			'description' => '',
			'image'       => '',
			'broadcaster' => (object) array( 'imageURL' => '' ),
		);

		return $this->adapter->load_item( $this->adapter->format_item_type( $series ), ItemTypeModel::class );
	}

	private function sermon( $external_id, $title, $preach_date ) {
		return (object) array(
			'sermonID'         => $external_id,
			'displayTitle'     => $title,
			'fullTitle'        => $title,
			'preachDate'       => $preach_date,
			'publishTimestamp' => 1700000000,
			'moreInfoText'     => 'From SermonAudio',
			'hasAudio'         => false,
			'hasVideo'         => false,
			'bibleText'        => null,
			'eventType'        => null,
		);
	}

	private function local_post( $post_type, $title, $content = '', $date = '' ) {
		$args = array(
			'post_type'    => $post_type,
			'post_title'   => $title,
			'post_status'  => 'publish',
			'post_content' => $content,
		);

		if ( '' !== $date ) {
			$args['post_date'] = $date;
		}

		$id = wp_insert_post( $args, true );
		$this->assertNotWPError( $id );

		return (int) $id;
	}

	private function post_ids( $post_type ) {
		$ids = get_posts(
			array(
				'post_type'   => $post_type,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
				'orderby'     => 'ID',
				'order'       => 'ASC',
			)
		);

		return array_map( 'intval', $ids );
	}

	private function speaker_type() {
		return cp_library()->setup->post_types->speaker->post_type;
	}

	private function series_type() {
		return cp_library()->setup->post_types->item_type->post_type;
	}
}
