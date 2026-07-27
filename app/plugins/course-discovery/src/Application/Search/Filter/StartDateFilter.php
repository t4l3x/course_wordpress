<?php
/**
 * Course start-date filter.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Application\Search\Filter;

use OxfordInternational\CourseDiscovery\Application\Search\Condition\StartDateCondition;
use OxfordInternational\CourseDiscovery\Application\Search\CourseFilterInterface;
use OxfordInternational\CourseDiscovery\Application\Search\CourseQuery;
use OxfordInternational\CourseDiscovery\Application\Search\SearchCriteria;

/**
 * Contributes one OR group for selected start months.
 */
final class StartDateFilter implements CourseFilterInterface {
	/**
	 * Return the stable filter key.
	 */
	public function key(): string {
		return StartDateCondition::KEY;
	}

	/**
	 * Start-date filtering is meaningful only when dates are selected.
	 *
	 * @param SearchCriteria $criteria Typed Course search criteria.
	 */
	public function supports( SearchCriteria $criteria ): bool {
		return array() !== $criteria->start_dates();
	}

	/**
	 * Add the start-date condition when supported.
	 *
	 * @param CourseQuery    $query    Query composed so far.
	 * @param SearchCriteria $criteria Typed Course search criteria.
	 */
	public function apply( CourseQuery $query, SearchCriteria $criteria ): CourseQuery {
		return $this->supports( $criteria )
			? $query->with_condition( new StartDateCondition( $criteria->start_dates() ) )
			: $query;
	}
}
