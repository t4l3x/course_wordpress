<?php
/**
 * Course filter contract.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Application\Search;

/**
 * Contributes one independent condition to a backend-independent Course query.
 */
interface CourseFilterInterface {
	/**
	 * Return the stable filter key.
	 */
	public function key(): string;

	/**
	 * Determine whether the filter has meaningful criteria to apply.
	 *
	 * @param SearchCriteria $criteria Typed Course search criteria.
	 */
	public function supports( SearchCriteria $criteria ): bool;

	/**
	 * Return a query containing this filter's condition.
	 *
	 * @param CourseQuery    $query    Query composed so far.
	 * @param SearchCriteria $criteria Typed Course search criteria.
	 */
	public function apply( CourseQuery $query, SearchCriteria $criteria ): CourseQuery;
}
