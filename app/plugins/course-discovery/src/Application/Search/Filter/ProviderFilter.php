<?php
/**
 * Course Provider filter.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Application\Search\Filter;

use OxfordInternational\CourseDiscovery\Application\Search\Condition\ProviderCondition;
use OxfordInternational\CourseDiscovery\Application\Search\CourseFilterInterface;
use OxfordInternational\CourseDiscovery\Application\Search\CourseQuery;
use OxfordInternational\CourseDiscovery\Application\Search\SearchCriteria;

/**
 * Contributes one OR group for selected Providers.
 */
final class ProviderFilter implements CourseFilterInterface {
	/**
	 * Return the stable filter key.
	 */
	public function key(): string {
		return ProviderCondition::KEY;
	}

	/**
	 * Provider filtering is meaningful only when Providers are selected.
	 *
	 * @param SearchCriteria $criteria Typed Course search criteria.
	 */
	public function supports( SearchCriteria $criteria ): bool {
		return array() !== $criteria->providers();
	}

	/**
	 * Add the Provider condition when supported.
	 *
	 * @param CourseQuery    $query    Query composed so far.
	 * @param SearchCriteria $criteria Typed Course search criteria.
	 */
	public function apply( CourseQuery $query, SearchCriteria $criteria ): CourseQuery {
		return $this->supports( $criteria )
			? $query->with_condition( new ProviderCondition( $criteria->providers() ) )
			: $query;
	}
}
