<?php
/**
 * A variation title had to match a service type exactly.
 *
 * array_search() compared the CSV cell to the source name as stored, so
 * "sermon" missed "Sermon" and "Faith & Works" missed "Faith &amp; Works".
 * The exception named only the source label. The sermon post was already
 * saved, and the log did not say which row, which value, or which names
 * would have matched.
 *
 * get_source_items() is source id => name. get_source_items( true ) is those
 * ids, and update_variant() rejects a variation_id that is not in that list.
 * The match has to return the id key.
 *
 * @package CP_Library
 */

namespace CP_Library\Tests\Unit;

use ChurchPlugins\Exception;
use CP_Library\Admin\Import\ImportSermons;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Library\Admin\Import\ImportSermons::match_variation_id
 */
class VariationMatchTest extends TestCase {

	public function test_names_match_after_case_space_and_entities_and_the_id_is_the_key() {
		$options = array(
			12 => 'Sermon',
			4  => 'Presentation',
			9  => 'Faith &amp; Works',
		);

		// Same list update_variant() checks with get_source_items( true ).
		$ids = array_keys( $options );

		$this->assertSame( 12, ImportSermons::match_variation_id( 'sermon', $options ) );
		$this->assertContains( ImportSermons::match_variation_id( 'sermon', $options ), $ids );

		$this->assertSame( 4, ImportSermons::match_variation_id( '  Presentation ', $options ) );
		$this->assertSame( 9, ImportSermons::match_variation_id( 'Faith & Works', $options ) );
		$this->assertSame( 9, ImportSermons::match_variation_id( 'faith &amp; works', $options, 3 ) );
	}

	public function test_a_miss_names_the_row_the_value_and_the_valid_names() {
		$options = array(
			12 => 'Sermon',
			4  => 'Presentation',
		);

		try {
			ImportSermons::match_variation_id( 'Youth', $options, 7 );
			$this->fail( 'a missing variation must throw' );
		} catch ( Exception $e ) {
			$message = $e->getMessage();
		}

		$this->assertStringContainsString( 'Row 7', $message );
		$this->assertStringContainsString( 'Youth', $message );
		$this->assertStringContainsString( 'could not be found', $message );
		$this->assertStringContainsString( 'Sermon', $message );
		$this->assertStringContainsString( 'Presentation', $message );
	}

	public function test_a_miss_without_a_row_number_still_lists_the_value_and_names() {
		try {
			ImportSermons::match_variation_id( 'Youth', array( 1 => 'Sermon' ) );
			$this->fail( 'a missing variation must throw' );
		} catch ( Exception $e ) {
			$message = $e->getMessage();
		}

		$this->assertStringNotContainsString( 'Row ', $message );
		$this->assertStringContainsString( '"Youth"', $message );
		$this->assertStringContainsString( 'Sermon', $message );
	}
}
