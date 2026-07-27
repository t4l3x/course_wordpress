<?php
/**
 * Course search execution contract.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Application\Search;

/**
 * Executes backend-independent Course query intent.
 */
interface CourseSearchInterface {
	/**
	 * Search Courses using one page of results.
	 *
	 * @param CourseQuery $query    Backend-independent query.
	 * @param int         $page     One-based result page.
	 * @param int         $per_page Maximum results per page.
	 */
	public function search( CourseQuery $query, int $page = 1, int $per_page = 20 ): CourseSearchResult;
}
