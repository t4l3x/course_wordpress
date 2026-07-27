<?php
/**
 * Paginated Course search result.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Application\Search;

use InvalidArgumentException;
use OxfordInternational\CourseDiscovery\Domain\Course\CourseId;

/**
 * Carries backend-independent Course IDs and pagination metadata.
 */
final readonly class CourseSearchResult {
	/**
	 * Course identifiers on the requested page.
	 *
	 * @var list<CourseId>
	 */
	private array $course_ids;

	/**
	 * Create a Course search result.
	 *
	 * @param array $course_ids    Course identifiers on this page.
	 * @param int   $total_results Total matching Courses.
	 * @param int   $current_page  One-based current page.
	 * @param int   $per_page      Maximum results per page.
	 *
	 * @phpstan-param list<CourseId> $course_ids
	 *
	 * @throws InvalidArgumentException When pagination metadata is invalid.
	 */
	public function __construct(
		array $course_ids,
		private int $total_results,
		private int $current_page,
		private int $per_page
	) {
		if ( 0 > $total_results ) {
			throw new InvalidArgumentException( 'A Course search total cannot be negative.' );
		}

		if ( 1 > $current_page || 1 > $per_page ) {
			throw new InvalidArgumentException( 'Course search pagination values must be positive integers.' );
		}

		$this->course_ids = $course_ids;
	}

	/**
	 * Return Course identifiers on the requested page.
	 *
	 * @return list<CourseId>
	 */
	public function course_ids(): array {
		return $this->course_ids;
	}

	/**
	 * Return the number of matching Courses across all pages.
	 */
	public function total_results(): int {
		return $this->total_results;
	}

	/**
	 * Return the one-based current page.
	 */
	public function current_page(): int {
		return $this->current_page;
	}

	/**
	 * Return the maximum results per page.
	 */
	public function per_page(): int {
		return $this->per_page;
	}

	/**
	 * Return the number of available result pages.
	 */
	public function total_pages(): int {
		return 0 === $this->total_results
			? 0
			: intdiv( $this->total_results + $this->per_page - 1, $this->per_page );
	}
}
