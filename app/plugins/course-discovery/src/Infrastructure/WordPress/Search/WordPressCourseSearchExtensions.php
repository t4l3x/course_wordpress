<?php
/**
 * WordPress Course search extension hooks.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search;

use OxfordInternational\CourseDiscovery\Application\Search\CourseFilterRegistry;
use OxfordInternational\CourseDiscovery\Application\Search\CourseQuery;
use OxfordInternational\CourseDiscovery\Application\Search\CourseSearchExtensionsInterface;
use OxfordInternational\CourseDiscovery\Application\Search\ResultOrder;
use OxfordInternational\CourseDiscovery\Application\Search\SearchCriteria;

/**
 * Adapts the typed Course search pipeline to a small set of WordPress hooks.
 */
final class WordPressCourseSearchExtensions implements CourseSearchExtensionsInterface {
	public const string REGISTER_FILTERS_ACTION = 'course_discovery/register_filters';
	public const string SEARCH_CRITERIA_FILTER  = 'course_discovery/search_criteria';
	public const string COURSE_QUERY_FILTER     = 'course_discovery/course_query';
	public const string RESULT_ORDER_FILTER     = 'course_discovery/result_order';
	public const string FILTER_OPTIONS_PREFIX   = 'course_discovery/filter_options/';

	/**
	 * Allow core and third-party code to register Course filters.
	 *
	 * @param CourseFilterRegistry $registry Registry for the current composition run.
	 */
	public function register_filters( CourseFilterRegistry $registry ): void {
		/**
		 * Fires when the Course filter registry is being assembled.
		 *
		 * Call CourseFilterRegistry::register() with each additional filter.
		 *
		 * @param CourseFilterRegistry $registry Mutable registry for this composition run.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- The declared constant contains the plugin-prefixed public hook name.
		do_action( self::REGISTER_FILTERS_ACTION, $registry );
	}

	/**
	 * Allow immutable criteria transformation before filters run.
	 *
	 * @param SearchCriteria $criteria Requested Course search criteria.
	 */
	public function search_criteria( SearchCriteria $criteria ): SearchCriteria {
		/**
		 * Filters typed Course search criteria before query composition.
		 *
		 * Transformations must return a new or existing SearchCriteria instance.
		 *
		 * @param SearchCriteria $criteria Requested Course search criteria.
		 */
		/**
		 * Runtime hook output is untrusted.
		 *
		 * @var mixed $filtered_criteria
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- The declared constant contains the plugin-prefixed public hook name.
		$filtered_criteria = apply_filters( self::SEARCH_CRITERIA_FILTER, $criteria );

		if ( ! $filtered_criteria instanceof SearchCriteria ) {
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Diagnostic arguments are passed to _doing_it_wrong(), not rendered here.
			_doing_it_wrong(
				__METHOD__,
				__( 'The Course search criteria filter must return SearchCriteria.', 'course-discovery' ),
				COURSE_DISCOVERY_VERSION
			);
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

			return $criteria;
		}

		return $filtered_criteria;
	}

	/**
	 * Allow transformation of the composed backend-independent query.
	 *
	 * @param CourseQuery    $query    Composed Course query.
	 * @param SearchCriteria $criteria Transformed search criteria.
	 */
	public function course_query( CourseQuery $query, SearchCriteria $criteria ): CourseQuery {
		/**
		 * Filters the composed backend-independent Course query.
		 *
		 * @param CourseQuery    $query    Composed Course query.
		 * @param SearchCriteria $criteria Transformed criteria used for composition.
		 */
		/**
		 * Runtime hook output is untrusted.
		 *
		 * @var mixed $filtered_query
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- The declared constant contains the plugin-prefixed public hook name.
		$filtered_query = apply_filters( self::COURSE_QUERY_FILTER, $query, $criteria );

		if ( ! $filtered_query instanceof CourseQuery ) {
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Diagnostic arguments are passed to _doing_it_wrong(), not rendered here.
			_doing_it_wrong(
				__METHOD__,
				__( 'The Course query filter must return CourseQuery.', 'course-discovery' ),
				COURSE_DISCOVERY_VERSION
			);
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

			return $query;
		}

		return $filtered_query;
	}

	/**
	 * Allow result-order customization after query composition.
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
		/**
		 * Filters the typed Course result order after query composition.
		 *
		 * The returned key expresses application intent, not a WP_Query orderby value.
		 *
		 * @param ResultOrder    $result_order Current result order.
		 * @param SearchCriteria $criteria     Transformed search criteria.
		 * @param CourseQuery    $query        Composed backend-independent query.
		 */
		/**
		 * Runtime hook output is untrusted.
		 *
		 * @var mixed $filtered_order
		 */
		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- The declared constant contains the plugin-prefixed public hook name.
		$filtered_order = apply_filters(
			self::RESULT_ORDER_FILTER,
			$result_order,
			$criteria,
			$query
		);
		// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound

		if ( ! $filtered_order instanceof ResultOrder ) {
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Diagnostic arguments are passed to _doing_it_wrong(), not rendered here.
			_doing_it_wrong(
				__METHOD__,
				__( 'The Course result order filter must return ResultOrder.', 'course-discovery' ),
				COURSE_DISCOVERY_VERSION
			);
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

			return $result_order;
		}

		return $filtered_order;
	}
}
