<?php
/**
 * Parsed Course Discovery request.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Frontend;

use InvalidArgumentException;
use OxfordInternational\CourseDiscovery\Application\Search\SearchCriteria;

/**
 * Carries typed search criteria and validated pagination input.
 */
final readonly class CourseSearchRequest {
	/**
	 * Create a parsed Course Discovery request.
	 *
	 * @param SearchCriteria $criteria Typed search criteria.
	 * @param int            $page     One-based result page.
	 * @param int            $per_page Results per page.
	 *
	 * @throws InvalidArgumentException When pagination is invalid.
	 */
	public function __construct(
		private SearchCriteria $criteria,
		private int $page,
		private int $per_page
	) {
		if ( 1 > $page || 1 > $per_page ) {
			throw new InvalidArgumentException( 'Course Discovery pagination values must be positive integers.' );
		}
	}

	/**
	 * Return typed search criteria.
	 */
	public function criteria(): SearchCriteria {
		return $this->criteria;
	}

	/**
	 * Return the one-based result page.
	 */
	public function page(): int {
		return $this->page;
	}

	/**
	 * Return the requested page size.
	 */
	public function per_page(): int {
		return $this->per_page;
	}
}
