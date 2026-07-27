<?php
/**
 * Tests for backend-independent Course search results.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Tests\Unit\Search;

use InvalidArgumentException;
use OxfordInternational\CourseDiscovery\Application\Search\CourseSearchResult;
use OxfordInternational\CourseDiscovery\Domain\Course\CourseId;
use PHPUnit\Framework\TestCase;

/**
 * Verifies Course result pagination and validation.
 */
final class CourseSearchResultTest extends TestCase {
	/**
	 * Result pages expose typed IDs and deterministic pagination metadata.
	 */
	public function test_result_exposes_typed_course_ids_and_pagination(): void {
		$result = new CourseSearchResult(
			array( new CourseId( 3 ), new CourseId( 7 ) ),
			5,
			2,
			2
		);

		self::assertSame(
			array( 3, 7 ),
			array_map(
				static fn ( CourseId $course_id ): int => $course_id->value(),
				$result->course_ids()
			)
		);
		self::assertSame( 5, $result->total_results() );
		self::assertSame( 2, $result->current_page() );
		self::assertSame( 2, $result->per_page() );
		self::assertSame( 3, $result->total_pages() );
	}

	/**
	 * Empty results have no available pages.
	 */
	public function test_empty_result_has_zero_pages(): void {
		$result = new CourseSearchResult( array(), 0, 1, 20 );

		self::assertSame( array(), $result->course_ids() );
		self::assertSame( 0, $result->total_pages() );
	}

	/**
	 * Invalid pagination metadata is rejected.
	 *
	 * @dataProvider invalid_pagination_provider
	 *
	 * @param int $total_results Invalid total.
	 * @param int $current_page  Invalid current page.
	 * @param int $per_page      Invalid page size.
	 */
	public function test_invalid_pagination_is_rejected(
		int $total_results,
		int $current_page,
		int $per_page
	): void {
		$this->expectException( InvalidArgumentException::class );

		new CourseSearchResult( array(), $total_results, $current_page, $per_page );
	}

	/**
	 * Invalid pagination values.
	 *
	 * @return array<string, array{int, int, int}>
	 */
	public static function invalid_pagination_provider(): array {
		return array(
			'negative total' => array( -1, 1, 20 ),
			'zero page'      => array( 0, 0, 20 ),
			'zero page size' => array( 0, 1, 0 ),
		);
	}
}
