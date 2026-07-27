<?php
/**
 * Course text filter.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Application\Search\Filter;

use OxfordInternational\CourseDiscovery\Application\Search\Condition\TextCondition;
use OxfordInternational\CourseDiscovery\Application\Search\CourseFilterInterface;
use OxfordInternational\CourseDiscovery\Application\Search\CourseQuery;
use OxfordInternational\CourseDiscovery\Application\Search\SearchCriteria;

/**
 * Contributes Course text-search intent when a term is present.
 */
final class TextFilter implements CourseFilterInterface {
	/**
	 * Return the stable filter key.
	 */
	public function key(): string {
		return TextCondition::KEY;
	}

	/**
	 * Text filtering is meaningful only when a term is present.
	 *
	 * @param SearchCriteria $criteria Typed Course search criteria.
	 */
	public function supports( SearchCriteria $criteria ): bool {
		return null !== $criteria->search_term();
	}

	/**
	 * Add the Course text condition when supported.
	 *
	 * @param CourseQuery    $query    Query composed so far.
	 * @param SearchCriteria $criteria Typed Course search criteria.
	 */
	public function apply( CourseQuery $query, SearchCriteria $criteria ): CourseQuery {
		$search_term = $criteria->search_term();

		return null === $search_term
			? $query
			: $query->with_condition( new TextCondition( $search_term ) );
	}
}
