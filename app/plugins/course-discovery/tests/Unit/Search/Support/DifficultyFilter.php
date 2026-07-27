<?php
/**
 * Test-only Difficulty Course filter.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Tests\Unit\Search\Support;

use OxfordInternational\CourseDiscovery\Application\Search\CourseFilterInterface;
use OxfordInternational\CourseDiscovery\Application\Search\CourseQuery;
use OxfordInternational\CourseDiscovery\Application\Search\SearchCriteria;

/**
 * Proves a third party can consume typed custom criteria through composition.
 */
final class DifficultyFilter implements CourseFilterInterface {
	/**
	 * Return the stable filter key.
	 */
	public function key(): string {
		return DifficultyCriterion::KEY;
	}

	/**
	 * Determine whether typed Difficulty criteria are present.
	 *
	 * @param SearchCriteria $criteria Typed Course search criteria.
	 */
	public function supports( SearchCriteria $criteria ): bool {
		return $criteria->custom_criterion( DifficultyCriterion::KEY ) instanceof DifficultyCriterion;
	}

	/**
	 * Add the third-party Difficulty condition when supported.
	 *
	 * @param CourseQuery    $query    Query composed so far.
	 * @param SearchCriteria $criteria Typed Course search criteria.
	 */
	public function apply( CourseQuery $query, SearchCriteria $criteria ): CourseQuery {
		$criterion = $criteria->custom_criterion( DifficultyCriterion::KEY );

		return $criterion instanceof DifficultyCriterion
			? $query->with_condition( new DifficultyCondition( $criterion->level() ) )
			: $query;
	}
}
