<?php
/**
 * Test-only custom Course filter.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Tests\Unit\Search\Support;

use OxfordInternational\CourseDiscovery\Application\Search\CourseFilterInterface;
use OxfordInternational\CourseDiscovery\Application\Search\CourseQuery;
use OxfordInternational\CourseDiscovery\Application\Search\SearchCriteria;

/**
 * Proves third-party filters can use only the public filter contract.
 */
final readonly class CustomFilter implements CourseFilterInterface {
	/**
	 * Create a custom filter.
	 *
	 * @param string $key Stable test filter key.
	 */
	public function __construct(
		private string $key = 'custom'
	) {
	}

	/**
	 * Return the stable filter key.
	 */
	public function key(): string {
		return $this->key;
	}

	/**
	 * The test filter always contributes its condition.
	 *
	 * @param SearchCriteria $criteria Typed Course search criteria.
	 */
	public function supports( SearchCriteria $criteria ): bool {
		unset( $criteria );

		return true;
	}

	/**
	 * Add the test-only custom condition.
	 *
	 * @param CourseQuery    $query    Query composed so far.
	 * @param SearchCriteria $criteria Typed Course search criteria.
	 */
	public function apply( CourseQuery $query, SearchCriteria $criteria ): CourseQuery {
		unset( $criteria );

		return $query->with_condition( new CustomCondition( $this->key ) );
	}
}
