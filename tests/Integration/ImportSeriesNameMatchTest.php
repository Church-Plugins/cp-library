<?php
/**
 * Importing a series, speaker, or service type named "Hope" must not attach
 * the sermon to an existing "Hope for Today".
 *
 * The series lookup used a LIKE search and kept the first hit. An exact name
 * in any case, including surrounding whitespace, reuses the existing term.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Integration;

use CP_Library\Admin\Import\ImportSermons;
use CP_Library\Models\Item as ItemModel;
use CP_Library\Models\ItemType as ItemTypeModel;
use CP_Library\Models\ServiceType as ServiceTypeModel;
use CP_Library\Models\Speaker as SpeakerModel;
use ReflectionClass;

/**
 * Calls the protected importer without scheduling a background process.
 */
class RowImporter extends ImportSermons {

	public function __construct() {
		// The parent constructor reads saved config and registers cron hooks.
	}

	/**
	 * @param array $item Mapped CSV row.
	 */
	public function import_row( array $item ) {
		$this->import_item(
			$item,
			array(
				'sideload_audio'     => false,
				'stop_on_error'      => true,
				'sideload_downloads' => false,
			)
		);
	}
}

/**
 * @covers \CP_Library\Admin\Import\ImportSermons::import_item
 */
class ImportSeriesNameMatchTest extends TestCase {

	public function set_up() {
		parent::set_up();
		$this->reset_import_caches();
	}

	public function tear_down() {
		remove_filter( 'cpl_enable_service_type', '__return_true' );
		$this->reset_import_caches();
		parent::tear_down();
	}

	public function test_hope_creates_a_new_series_when_hope_for_today_exists() {
		$longer = $this->make_series( 'Hope for Today' );

		$this->importer()->import_row( $this->row( 'Sunday Hope', 'Hope' ) );

		$hope_ids = $this->ids_named( ItemTypeModel::class, 'cpl_item_type', 'Hope' );
		$this->assertCount( 1, $hope_ids, 'Hope is its own series' );

		$item = $this->sermon_named( 'Sunday Hope' );
		$this->assertSame( array( $hope_ids[0] ), $this->ints( $item->get_types() ) );
		$this->assertNotContains( (int) $longer->id, $this->ints( $item->get_types() ) );
	}

	public function test_lowercase_hope_reuses_hope_when_a_longer_series_also_exists() {
		$longer = $this->make_series( 'Hope for Today' );
		$hope   = $this->make_series( 'Hope' );

		$this->importer()->import_row( $this->row( 'Lower Hope', 'hope' ) );

		$item = $this->sermon_named( 'Lower Hope' );
		$this->assertSame( array( (int) $hope->id ), $this->ints( $item->get_types() ) );
		$this->assertNotContains( (int) $longer->id, $this->ints( $item->get_types() ) );
		$this->assertCount( 1, $this->ids_named( ItemTypeModel::class, 'cpl_item_type', 'Hope' ) );
	}

	public function test_exact_series_name_reuses_the_existing_term() {
		$existing = $this->make_series( 'Hope for Today' );

		$this->importer()->import_row( $this->row( 'Case Sermon', '  HOPE FOR TODAY  ' ) );

		$this->assertCount( 1, $this->ids_named( ItemTypeModel::class, 'cpl_item_type', 'hope for today' ) );

		$item = $this->sermon_named( 'Case Sermon' );
		$this->assertSame( array( (int) $existing->id ), $this->ints( $item->get_types() ) );
	}

	public function test_hope_creates_a_new_speaker_when_hope_for_today_exists() {
		$longer = $this->make_speaker( 'Hope for Today' );

		$this->importer()->import_row( $this->row( 'Speaker Sermon', '', 'Hope' ) );

		$hope_ids = $this->ids_named( SpeakerModel::class, 'cpl_speaker', 'Hope' );
		$this->assertCount( 1, $hope_ids );

		$item = $this->sermon_named( 'Speaker Sermon' );
		$this->assertSame( array( $hope_ids[0] ), $this->ints( $item->get_speakers() ) );
		$this->assertNotContains( (int) $longer->id, $this->ints( $item->get_speakers() ) );
	}

	public function test_speaker_match_ignores_surrounding_space_on_the_stored_name() {
		$existing = $this->make_speaker( 'Hope for Today' );
		$this->set_source_title( $existing, 'Hope for Today ' );

		$this->importer()->import_row( $this->row( 'Trimmed Speaker', '', 'Hope for Today' ) );

		$this->assertCount( 1, $this->ids_named( SpeakerModel::class, 'cpl_speaker', 'Hope for Today' ) );

		$item = $this->sermon_named( 'Trimmed Speaker' );
		$this->assertSame( array( (int) $existing->id ), $this->ints( $item->get_speakers() ) );
	}

	public function test_exact_speaker_name_reuses_the_existing_term() {
		$existing = $this->make_speaker( 'Hope for Today' );

		$this->importer()->import_row( $this->row( 'Speaker Case', '', '  HOPE FOR TODAY  ' ) );

		$this->assertCount( 1, $this->ids_named( SpeakerModel::class, 'cpl_speaker', 'hope for today' ) );

		$item = $this->sermon_named( 'Speaker Case' );
		$this->assertSame( array( (int) $existing->id ), $this->ints( $item->get_speakers() ) );
	}

	public function test_hope_creates_a_new_service_type_when_hope_for_today_exists() {
		add_filter( 'cpl_enable_service_type', '__return_true' );
		$longer = $this->make_service_type( 'Hope for Today' );

		$this->importer()->import_row( $this->row( 'Service Sermon', '', '', 'Hope' ) );

		$hope_ids = $this->ids_named( ServiceTypeModel::class, 'cpl_service_type', 'Hope' );
		$this->assertCount( 1, $hope_ids );

		$item = $this->sermon_named( 'Service Sermon' );
		$this->assertSame( array( $hope_ids[0] ), $this->ints( $item->get_service_types() ) );
		$this->assertNotContains( (int) $longer->id, $this->ints( $item->get_service_types() ) );
	}

	public function test_stored_encoded_series_name_matches_the_raw_import_name() {
		$existing = $this->make_series( 'Faith and Works' );
		$this->set_series_title( $existing, 'Faith &amp; Works' );

		$this->importer()->import_row( $this->row( 'Encoded Series', 'Faith & Works' ) );

		$item = $this->sermon_named( 'Encoded Series' );
		$this->assertSame( array( (int) $existing->id ), $this->ints( $item->get_types() ) );
		$this->assertCount( 1, $this->ids_named_normalized( ItemTypeModel::class, 'cpl_item_type', 'Faith & Works' ) );
	}

	public function test_importing_faith_and_works_twice_under_kses_creates_one_series() {
		$this->import_as_guest();

		$importer = $this->importer();
		$importer->import_row( $this->row( 'Amp One', 'Faith & Works' ) );
		$importer->import_row( $this->row( 'Amp Two', 'Faith & Works' ) );

		$ids = $this->ids_named_normalized( ItemTypeModel::class, 'cpl_item_type', 'Faith & Works' );
		$this->assertCount( 1, $ids );

		$item = $this->sermon_named( 'Amp Two' );
		$this->assertSame( array( $ids[0] ), $this->ints( $item->get_types() ) );
	}

	public function test_importing_a_speaker_with_ampersand_twice_under_kses_creates_one_speaker() {
		$this->import_as_guest();

		$importer = $this->importer();
		$importer->import_row( $this->row( 'Speaker Amp One', '', 'Grace & Peace' ) );
		// The speaker list is cached from the name in the file. Drop it so the
		// second row reads the title kses stored.
		$this->reset_import_caches();
		$importer->import_row( $this->row( 'Speaker Amp Two', '', 'Grace & Peace' ) );

		$ids = $this->ids_named_normalized( SpeakerModel::class, 'cpl_speaker', 'Grace & Peace' );
		$this->assertCount( 1, $ids );

		$item = $this->sermon_named( 'Speaker Amp Two' );
		$this->assertSame( array( $ids[0] ), $this->ints( $item->get_speakers() ) );
	}

	public function test_raw_ampersand_and_apostrophe_names_still_match_when_imported() {
		$series  = $this->make_series( 'Series Placeholder' );
		$speaker = $this->make_speaker( 'Speaker Placeholder' );
		$this->set_series_title( $series, 'Faith & Works' );
		$this->set_source_title( $speaker, 'O\'Brien' );
		$this->force_post_title( $speaker->origin_id, 'O\'Brien' );
		$this->reset_import_caches();

		$this->importer()->import_row( $this->row( 'Raw Punctuation', 'Faith & Works', 'O\'Brien' ) );

		$this->assertCount( 1, $this->ids_named_normalized( ItemTypeModel::class, 'cpl_item_type', 'Faith & Works' ) );
		$this->assertCount( 1, $this->ids_named_normalized( SpeakerModel::class, 'cpl_speaker', 'O\'Brien' ) );

		$item = $this->sermon_named( 'Raw Punctuation' );
		$this->assertSame( array( (int) $series->id ), $this->ints( $item->get_types() ) );
		$this->assertSame( array( (int) $speaker->id ), $this->ints( $item->get_speakers() ) );
	}

	public function test_quote_name_still_matches_when_imported() {
		$series = $this->make_series( 'Series Placeholder' );
		$this->set_series_title( $series, 'Say "Hello"' );

		$this->importer()->import_row( $this->row( 'Quoted Series', 'Say "Hello"' ) );

		$item = $this->sermon_named( 'Quoted Series' );
		$this->assertSame( array( (int) $series->id ), $this->ints( $item->get_types() ) );
		$this->assertCount( 1, $this->ids_named_normalized( ItemTypeModel::class, 'cpl_item_type', 'Say "Hello"' ) );
	}

	public function test_exact_service_type_name_reuses_the_existing_term() {
		add_filter( 'cpl_enable_service_type', '__return_true' );
		$existing = $this->make_service_type( 'Hope for Today' );

		$this->importer()->import_row( $this->row( 'Service Case', '', '', '  HOPE FOR TODAY  ' ) );

		$this->assertCount( 1, $this->ids_named( ServiceTypeModel::class, 'cpl_service_type', 'hope for today' ) );

		$item = $this->sermon_named( 'Service Case' );
		$this->assertSame( array( (int) $existing->id ), $this->ints( $item->get_service_types() ) );
	}

	/**
	 * @return RowImporter
	 */
	private function importer() {
		return new RowImporter();
	}

	/**
	 * @param string $title        Sermon title.
	 * @param string $series       Series cell.
	 * @param string $speaker      Speaker cell.
	 * @param string $service_type Service type cell.
	 * @return array
	 */
	private function row( $title, $series = '', $speaker = '', $service_type = '' ) {
		return array(
			'title'        => $title,
			'description'  => '',
			'transcript'   => '',
			'series'       => $series,
			'date'         => '2024-01-07',
			'location'     => '',
			'service_type' => $service_type,
			'speaker'      => $speaker,
			'topics'       => '',
			'season'       => '',
			'scripture'    => '',
			'thumbnail'    => '',
			'video'        => '',
			'audio'        => '',
			'variation'    => '',
			'downloads'    => '',
		);
	}

	/**
	 * @param string $title Sermon title.
	 * @return ItemModel
	 */
	private function sermon_named( $title ) {
		global $wpdb;

		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s ORDER BY ID DESC LIMIT 1",
				'cpl_item',
				$title
			)
		);

		$this->assertNotEmpty( $post_id );

		return ItemModel::get_instance_from_origin( (int) $post_id );
	}

	/**
	 * Model ids whose post title matches $name, ignoring case and surrounding space.
	 *
	 * @param string $model     Model class with get_instance_from_origin().
	 * @param string $post_type Post type.
	 * @param string $name      Expected name.
	 * @return int[]
	 */
	private function ids_named( $model, $post_type, $name ) {
		global $wpdb;

		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
				$post_type
			)
		);

		$needle = strtolower( trim( $name ) );
		$ids    = array();

		foreach ( $posts as $post ) {
			if ( strtolower( trim( $post->post_title ) ) !== $needle ) {
				continue;
			}

			$ids[] = (int) $model::get_instance_from_origin( (int) $post->ID )->id;
		}

		return $ids;
	}

	/**
	 * @param array $values Ids from the model, often strings.
	 * @return int[]
	 */
	private function ints( $values ) {
		return array_map( 'intval', (array) $values );
	}

	/**
	 * @param object $model Speaker or service type model.
	 * @param string $title Title stored on the custom table, including any space under test.
	 */
	private function force_post_title( $post_id, $title ) {
		global $wpdb;

		$wpdb->update( $wpdb->posts, array( 'post_title' => $title ), array( 'ID' => $post_id ) );
		clean_post_cache( $post_id );
	}

	private function set_source_title( $model, $title ) {
		global $wpdb;

		$table = SpeakerModel::get_prop( 'table_name' );
		$wpdb->update( $table, array( 'title' => $title ), array( 'id' => $model->id ) );
	}

	/**
	 * @param object $model Series model.
	 * @param string $title Title stored on the series table.
	 */
	private function set_series_title( $model, $title ) {
		global $wpdb;

		$table = ItemTypeModel::get_prop( 'table_name' );
		$wpdb->update( $table, array( 'title' => $title ), array( 'id' => $model->id ) );
		$wpdb->update( $wpdb->posts, array( 'post_title' => $title ), array( 'ID' => $model->origin_id ) );
		clean_post_cache( $model->origin_id );
	}

	/**
	 * No user, so kses encodes "&" in titles on the way into the database.
	 */
	private function import_as_guest() {
		wp_set_current_user( 0 );
		kses_init();
	}

	/**
	 * Model ids whose post title matches $name after the importer's normalization.
	 *
	 * @param string $model     Model class with get_instance_from_origin().
	 * @param string $post_type Post type.
	 * @param string $name      Expected name.
	 * @return int[]
	 */
	private function ids_named_normalized( $model, $post_type, $name ) {
		global $wpdb;

		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
				$post_type
			)
		);

		$needle = ImportSermons::normalize_term_name( $name );
		$ids    = array();

		foreach ( $posts as $post ) {
			if ( ImportSermons::normalize_term_name( $post->post_title ) !== $needle ) {
				continue;
			}

			$ids[] = (int) $model::get_instance_from_origin( (int) $post->ID )->id;
		}

		return $ids;
	}

	private function reset_import_caches() {
		$import = new ReflectionClass( ImportSermons::class );

		foreach ( array( '_speakers', '_service_types' ) as $name ) {
			$property = $import->getProperty( $name );
			$property->setAccessible( true );
			$property->setValue( null, false );
		}
	}
}
