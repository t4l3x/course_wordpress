<?php
/**
 * Course Location filter.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Application\Search\Filter;

use OxfordInternational\CourseDiscovery\Application\Search\Condition\LocationCondition;
use OxfordInternational\CourseDiscovery\Application\Search\CourseFilterInterface;
use OxfordInternational\CourseDiscovery\Application\Search\CourseQuery;
use OxfordInternational\CourseDiscovery\Application\Search\SearchCriteria;

/**
 * Contributes Location intent without resolving Provider relationships.
 */
final class LocationFilter implements CourseFilterInterface {
	/**
	 * Return the stable filter key.
	 */
	public function key(): string {
		return LocationCondition::KEY;
	}

	/**
	 * Location filtering is meaningful only when Locations are selected.
	 *
	 * @param SearchCriteria $criteria Typed Course search criteria.
	 */
	public function supports( SearchCriteria $criteria ): bool {
		return array() !== $criteria->locations();
	}

	/**
	 * Add the Location condition when supported.
	 *
	 * @param CourseQuery    $query    Query composed so far.
	 * @param SearchCriteria $criteria Typed Course search criteria.
	 */
	public function apply( CourseQuery $query, SearchCriteria $criteria ): CourseQuery {
		return $this->supports( $criteria )
			? $query->with_condition( new LocationCondition( $criteria->locations() ) )
			: $query;
	}
}
