<?php
/**
 * Variation player rows must carry the variation sermon's post ID.
 *
 * The share menu downloads audio with ?item_id={originID}. Variation rows
 * sent `id` as the service type and left originID unset, so the request
 * went out as item_id=undefined. Download cast that to 0 and stopped with
 * "File download error".
 *
 * originID is the variation sermon's own post ID, the same field
 * get_api_data() and get_player_data() set. `id` stays the service type.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Library\Controllers\Item;
use PHPUnit\Framework\TestCase;

/**
 * Parent sermon whose variation list is supplied by the test.
 */
class VariationParentDouble extends Item {

	/** @var bool */
	public $variations_enabled = true;

	/** @var array<int, Item> */
	public $children = [];

	/** @var string */
	public $sermon_title = 'Hope';

	/** @var string */
	public $sermon_permalink = 'https://example.test/hope';

	public function __construct() {
		// The real constructor loads a model. These tests supply the children.
	}

	public function has_variations() {
		return $this->variations_enabled;
	}

	public function get_variations() {
		return array_keys( $this->children );
	}

	public function get_title() {
		return $this->sermon_title;
	}

	public function get_permalink() {
		return $this->sermon_permalink;
	}

	protected function variation_item( $id ) {
		return $this->children[ $id ];
	}
}

/**
 * One variation sermon with its service type and audio already resolved.
 */
class VariationChildDouble extends Item {

	/** @var int|false */
	public $source_id = 3;

	/** @var int */
	public $variant_parent = 10;

	/** @var string */
	public $source_label = 'Sunday Morning';

	/** @var int */
	public $source_origin_id = 55;

	/** @var string */
	public $audio_url = 'https://cdn.example.test/sunday.mp3';

	/** @var array */
	public $video_data = [];

	/** @var array */
	public $speaker_data = [];

	public function __construct() {
		// Populated by the test. No model lookup.
	}

	public function get_variation_source_id() {
		return $this->source_id;
	}

	public function is_variant() {
		return $this->variant_parent;
	}

	public function get_variation_source_label() {
		return $this->source_label;
	}

	public function get_variation_source_origin_id() {
		return $this->source_origin_id;
	}

	public function get_audio() {
		return $this->audio_url;
	}

	public function get_video() {
		return $this->video_data;
	}

	public function get_speakers() {
		return $this->speaker_data;
	}
}

/**
 * @covers \CP_Library\Controllers\Item::get_variation_data
 */
class VariationOriginIdTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'get_the_post_thumbnail_url' )->justReturn( 'https://example.test/thumb.jpg' );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_each_variation_origin_id_is_that_sermons_post_id() {
		$sunday = $this->child( 88, 12, 55, 'Sunday Morning', 'https://cdn.example.test/sunday.mp3' );
		$youth  = $this->child( 91, 13, 60, 'Youth', 'https://cdn.example.test/youth.mp3' );

		$parent           = new VariationParentDouble();
		$parent->children = [
			12 => $sunday,
			13 => $youth,
		];

		$variations = array_values( $parent->get_variation_data() );

		$this->assertCount( 2, $variations );

		$this->assertSame( 88, $variations[0]['originID'] );
		$this->assertSame( 55, $variations[0]['id'] );
		$this->assertSame( 12, $variations[0]['logId'] );
		$this->assertSame( 'https://cdn.example.test/sunday.mp3', $variations[0]['audio'] );
		$this->assertSame( 'Hope: Sunday Morning', $variations[0]['title'] );

		$this->assertSame( 91, $variations[1]['originID'] );
		$this->assertSame( 60, $variations[1]['id'] );
		$this->assertSame( 'https://cdn.example.test/youth.mp3', $variations[1]['audio'] );
	}

	public function test_sermon_without_variations_returns_false() {
		$parent                       = new VariationParentDouble();
		$parent->variations_enabled   = false;

		$this->assertFalse( $parent->get_variation_data() );
	}

	public function test_rows_without_a_source_or_parent_are_left_out() {
		$kept    = $this->child( 88, 12, 55, 'Sunday Morning', 'https://cdn.example.test/sunday.mp3' );
		$no_source = $this->child( 70, 14, 55, 'Missing', 'https://cdn.example.test/missing.mp3' );
		$no_source->source_id = false;

		$not_variant = $this->child( 71, 15, 55, 'Original', 'https://cdn.example.test/original.mp3' );
		$not_variant->variant_parent = 0;

		$parent           = new VariationParentDouble();
		$parent->children = [
			12 => $kept,
			14 => $no_source,
			15 => $not_variant,
		];

		$variations = array_values( $parent->get_variation_data() );

		$this->assertCount( 1, $variations );
		$this->assertSame( 88, $variations[0]['originID'] );
	}

	/**
	 * @param int    $post_id
	 * @param int    $model_id
	 * @param int    $service_type_origin_id
	 * @param string $label
	 * @param string $audio
	 * @return VariationChildDouble
	 */
	private function child( $post_id, $model_id, $service_type_origin_id, $label, $audio ) {
		$child                    = new VariationChildDouble();
		$child->post              = (object) [ 'ID' => $post_id ];
		$child->model             = (object) [ 'id' => $model_id ];
		$child->source_origin_id  = $service_type_origin_id;
		$child->source_label      = $label;
		$child->audio_url         = $audio;

		return $child;
	}
}
