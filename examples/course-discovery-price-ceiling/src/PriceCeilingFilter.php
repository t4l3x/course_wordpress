<?php
/**
 * Maximum-price Course filter.
 *
 * @package CourseDiscoveryPriceCeilingExample
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscoveryExample\PriceCeiling;

use OxfordInternational\CourseDiscovery\Application\Search\CourseFilterInterface;
use OxfordInternational\CourseDiscovery\Application\Search\CourseQuery;
use OxfordInternational\CourseDiscovery\Application\Search\SearchCriteria;

/**
 * Converts typed maximum-price input to one independent query condition.
 */
final class PriceCeilingFilter implements CourseFilterInterface {
	/**
	 * Return the stable filter key.
	 */
	public function key(): string {
		return PriceCeilingCriterion::KEY;
	}

	/**
	 * Determine whether a typed maximum-price criterion is present.
	 *
	 * @param SearchCriteria $criteria Typed Course search criteria.
	 */
	public function supports( SearchCriteria $criteria ): bool {
		return $criteria->custom_criterion( PriceCeilingCriterion::KEY ) instanceof PriceCeilingCriterion;
	}

	/**
	 * Add the third-party maximum-price condition.
	 *
	 * @param CourseQuery    $query    Query composed so far.
	 * @param SearchCriteria $criteria Typed Course search criteria.
	 */
	public function apply( CourseQuery $query, SearchCriteria $criteria ): CourseQuery {
		$criterion = $criteria->custom_criterion( PriceCeilingCriterion::KEY );

		return $criterion instanceof PriceCeilingCriterion
			? $query->with_condition( new PriceCeilingCondition( $criterion->maximum() ) )
			: $query;
	}
}
