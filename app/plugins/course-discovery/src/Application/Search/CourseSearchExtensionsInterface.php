<?php
/**
 * Course search extension boundary.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Application\Search;

/**
 * Isolates application search composition from extension-system APIs.
 */
interface CourseSearchExtensionsInterface {
	/**
	 * Register filters contributed by the runtime environment.
	 *
	 * @param CourseFilterRegistry $registry Registry for the current composition run.
	 */
	public function register_filters( CourseFilterRegistry $registry ): void;

	/**
	 * Return transformed immutable search criteria.
	 *
	 * @param SearchCriteria $criteria Requested Course search criteria.
	 */
	public function search_criteria( SearchCriteria $criteria ): SearchCriteria;

	/**
	 * Return a transformed backend-independent Course query.
	 *
	 * @param CourseQuery    $query    Composed Course query.
	 * @param SearchCriteria $criteria Transformed search criteria.
	 */
	public function course_query( CourseQuery $query, SearchCriteria $criteria ): CourseQuery;

	/**
	 * Return the final backend-independent result ordering.
	 *
	 * @param ResultOrder    $result_order Current result ordering.
	 * @param SearchCriteria $criteria     Transformed search criteria.
	 * @param CourseQuery    $query        Composed Course query.
	 */
	public function result_order(
		ResultOrder $result_order,
		SearchCriteria $criteria,
		CourseQuery $query
	): ResultOrder;
}
