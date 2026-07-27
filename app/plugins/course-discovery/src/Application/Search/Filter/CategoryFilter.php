<?php
/**
 * Course Category filter.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Application\Search\Filter;

use OxfordInternational\CourseDiscovery\Application\Search\Condition\CategoryCondition;
use OxfordInternational\CourseDiscovery\Application\Search\CourseFilterInterface;
use OxfordInternational\CourseDiscovery\Application\Search\CourseQuery;
use OxfordInternational\CourseDiscovery\Application\Search\SearchCriteria;

/**
 * Contributes hierarchical Course Category intent without taxonomy execution.
 */
final class CategoryFilter implements CourseFilterInterface {
	/**
	 * Return the stable filter key.
	 */
	public function key(): string {
		return CategoryCondition::KEY;
	}

	/**
	 * Category filtering is meaningful only when Categories are selected.
	 *
	 * @param SearchCriteria $criteria Typed Course search criteria.
	 */
	public function supports( SearchCriteria $criteria ): bool {
		return array() !== $criteria->categories();
	}

	/**
	 * Add the Category condition when supported.
	 *
	 * @param CourseQuery    $query    Query composed so far.
	 * @param SearchCriteria $criteria Typed Course search criteria.
	 */
	public function apply( CourseQuery $query, SearchCriteria $criteria ): CourseQuery {
		return $this->supports( $criteria )
			? $query->with_condition( new CategoryCondition( $criteria->categories() ) )
			: $query;
	}
}
