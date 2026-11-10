<?php
/**
 * The SermonAudio sync hash must ignore the matcher's internal keys.
 *
 * The queue compares md5( serialize( $item ) ) against the hash stored on the
 * last sync. 1.8.2 added BATCH_AMBIGUOUS and TIMESTAMP_DATE to the formatted
 * item, so a sermon was re-queued when another same-title sermon joined or
 * left its page, or simply because it had no preach date. A re-sync writes the
 * feed over local edits on a sermon the sync created (#257).
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use CP_Library\Adapters\SermonAudio;
use CP_Library\Adapters\SermonAudioMatch;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \CP_Library\Adapters\SermonAudio::create_store_key
 * @covers \CP_Library\Adapters\SermonAudioMatch::without_internal_keys
 */
class SermonAudioSyncHashTest extends TestCase {

	private function adapter() {
		return ( new ReflectionClass( SermonAudio::class ) )->newInstanceWithoutConstructor();
	}

	private function item() {
		return array(
			'external_id' => 'sa-1',
			'post_title'  => 'Easter Sunday',
			'post_date'   => '2024-03-31 12:00:00',
			'post_type'   => 'cpl_item',
			'cpl_data'    => array( 'scripture' => 'John 20:1-18' ),
		);
	}

	public function test_internal_keys_do_not_change_the_hash() {
		$plain   = $this->item();
		$flagged = $plain;
		$flagged[ SermonAudioMatch::BATCH_AMBIGUOUS ] = 1;
		$flagged[ SermonAudioMatch::TIMESTAMP_DATE ]  = 1;

		$adapter = $this->adapter();

		$this->assertSame( $adapter->create_store_key( $plain ), $adapter->create_store_key( $flagged ) );
	}

	public function test_the_hash_matches_what_earlier_versions_stored() {
		$flagged = $this->item();
		$flagged[ SermonAudioMatch::TIMESTAMP_DATE ] = 1;

		$this->assertSame( md5( serialize( $this->item() ) ), $this->adapter()->create_store_key( $flagged ) );
	}

	public function test_a_real_change_still_changes_the_hash() {
		$changed               = $this->item();
		$changed['post_title'] = 'Easter Sunday (Part 2)';

		$adapter = $this->adapter();

		$this->assertNotSame( $adapter->create_store_key( $this->item() ), $adapter->create_store_key( $changed ) );
	}

	public function test_without_internal_keys_keeps_everything_else() {
		$flagged = $this->item();
		$flagged[ SermonAudioMatch::BATCH_AMBIGUOUS ] = 1;

		$this->assertSame( $this->item(), SermonAudioMatch::without_internal_keys( $flagged ) );
	}
}
