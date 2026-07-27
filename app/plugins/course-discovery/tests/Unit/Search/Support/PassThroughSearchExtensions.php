<?php
/**
 * Test-only pass-through search extensions.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Tests\Unit\Search\Support;

use OxfordInternational\CourseDiscovery\Application\Search\CourseFilterRegistry;
use OxfordInternational\CourseDiscovery\Application\Search\CourseQuery;
use OxfordInternational\CourseDiscovery\Application\Search\CourseSearchExtensionsInterface;
use OxfordInternational\CourseDiscovery\Application\Search\ResultOrder;
use OxfordInternational\CourseDiscovery\Application\Search\SearchCriteria;

/**
 * Leaves every pipeline extension value unchanged.
 */
final class PassThroughSearchExtensions implements CourseSearchExtensionsInterface {
	/**
	 * Register no additional filters.
	 *
	 * @param CourseFilterRegistry $registry Registry for the current composition run.
	 */
	public function register_filters( CourseFilterRegistry $registry ): void {
		unset( $registry );
	}

	/**
	 * Return criteria unchanged.
	 *
	 * @param SearchCriteria $criteria Requested Course search criteria.
	 */
	public function search_criteria( SearchCriteria $criteria ): SearchCriteria {
		return $criteria;
	}

	/**
	 * Return the query unchanged.
	 *
	 * @param CourseQuery    $query    Composed Course query.
	 * @param SearchCriteria $criteria Transformed search criteria.
	 */
	public function course_query( CourseQuery $query, SearchCriteria $criteria ): CourseQuery {
		unset( $criteria );

		return $query;
	}

	/**
	 * Return the order unchanged.
	 *
	 * @param ResultOrder    $result_order Current result ordering.
	 * @param SearchCriteria $criteria     Transformed search criteria.
	 * @param CourseQuery    $query        Composed Course query.
	 */
	public function result_order(
		ResultOrder $result_order,
		SearchCriteria $criteria,
		CourseQuery $query
	): ResultOrder {
		unset( $criteria, $query );

		return $result_order;
	}
}
