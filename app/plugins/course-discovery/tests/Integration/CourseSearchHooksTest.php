<?php
/**
 * Integration tests for Course search extension hooks.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Tests\Integration;

use LogicException;
use OxfordInternational\CourseDiscovery\Plugin;
use OxfordInternational\CourseDiscovery\Application\Search\Condition\ProviderCondition;
use OxfordInternational\CourseDiscovery\Application\Search\CourseFilterRegistry;
use OxfordInternational\CourseDiscovery\Application\Search\CourseQuery;
use OxfordInternational\CourseDiscovery\Application\Search\CourseQueryConditionInterface;
use OxfordInternational\CourseDiscovery\Application\Search\Filter\ProviderFilter;
use OxfordInternational\CourseDiscovery\Application\Search\ResultOrder;
use OxfordInternational\CourseDiscovery\Application\Search\SearchCriteria;
use OxfordInternational\CourseDiscovery\Domain\Provider\ProviderId;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\WordPressCourseSearchExtensions;
use OxfordInternational\CourseDiscovery\Tests\Unit\Search\Support\CustomCondition;
use OxfordInternational\CourseDiscovery\Tests\Unit\Search\Support\DifficultyCondition;
use OxfordInternational\CourseDiscovery\Tests\Unit\Search\Support\DifficultyCriterion;
use OxfordInternational\CourseDiscovery\Tests\Unit\Search\Support\DifficultyFilter;
use WP_UnitTestCase;

/**
 * Verifies the public search hooks against the real WordPress hook runtime.
 */
final class CourseSearchHooksTest extends WP_UnitTestCase {
	/**
	 * Third parties can extend every typed pipeline stage through WordPress hooks.
	 */
	public function test_wordpress_hooks_extend_the_typed_filter_pipeline(): void {
		$pipeline      = ( new Plugin() )->course_filter_pipeline();
		$custom_filter = new DifficultyFilter();
		$criteria      = new SearchCriteria();

		$register_filter = static function ( CourseFilterRegistry $registry ) use ( $custom_filter ): void {
			$registry->register( $custom_filter );
		};
		$criteria_filter = static fn ( SearchCriteria $current ): SearchCriteria => $current
			->with_providers( new ProviderId( 7 ) )
			->with_custom_criterion( new DifficultyCriterion( 'advanced' ) );
		$query_filter    = static function ( CourseQuery $query, SearchCriteria $current ): CourseQuery {
			unset( $current );

			return $query
				->with_replaced_condition( new ProviderCondition( array( new ProviderId( 9 ) ) ) )
				->with_condition( new CustomCondition( 'query_extension' ) );
		};
		$order_filter    = static function (
			ResultOrder $order,
			SearchCriteria $current,
			CourseQuery $query
		): ResultOrder {
			unset( $order, $current, $query );

			return new ResultOrder( 'third_party_order' );
		};

		add_action( WordPressCourseSearchExtensions::REGISTER_FILTERS_ACTION, $register_filter );
		add_filter( WordPressCourseSearchExtensions::SEARCH_CRITERIA_FILTER, $criteria_filter );
		add_filter( WordPressCourseSearchExtensions::COURSE_QUERY_FILTER, $query_filter, 10, 2 );
		add_filter( WordPressCourseSearchExtensions::RESULT_ORDER_FILTER, $order_filter, 10, 3 );

		try {
			$query        = $pipeline->compose( $criteria );
			$second_query = $pipeline->compose( $criteria );
		} finally {
			remove_action( WordPressCourseSearchExtensions::REGISTER_FILTERS_ACTION, $register_filter );
			remove_filter( WordPressCourseSearchExtensions::SEARCH_CRITERIA_FILTER, $criteria_filter );
			remove_filter( WordPressCourseSearchExtensions::COURSE_QUERY_FILTER, $query_filter );
			remove_filter( WordPressCourseSearchExtensions::RESULT_ORDER_FILTER, $order_filter );
		}

		self::assertTrue( $criteria->is_empty() );
		self::assertSame(
			array( ProviderCondition::KEY, DifficultyCondition::KEY, 'query_extension' ),
			array_map(
				static fn ( CourseQueryConditionInterface $condition ): string => $condition->key(),
				$query->conditions()
			)
		);
		$provider_condition = $query->condition( ProviderCondition::KEY );

		self::assertInstanceOf( ProviderCondition::class, $provider_condition );
		self::assertSame(
			array( 9 ),
			array_map(
				static fn ( ProviderId $provider ): int => $provider->value(),
				$provider_condition->providers()
			)
		);
		self::assertSame( 'third_party_order', $query->result_order()->key() );
		$difficulty_condition = $query->condition( DifficultyCondition::KEY );

		self::assertInstanceOf( DifficultyCondition::class, $difficulty_condition );
		self::assertSame( 'advanced', $difficulty_condition->level() );
		self::assertSame(
			array( ProviderCondition::KEY, DifficultyCondition::KEY, 'query_extension' ),
			array_map(
				static fn ( CourseQueryConditionInterface $condition ): string => $condition->key(),
				$second_query->conditions()
			)
		);
	}

	/**
	 * Extension filters cannot replace a core filter with the same key.
	 */
	public function test_extension_filter_cannot_replace_core_filter(): void {
		$pipeline = ( new Plugin() )->course_filter_pipeline();
		$callback = static function ( CourseFilterRegistry $registry ): void {
			$registry->register( new ProviderFilter() );
		};

		add_action( WordPressCourseSearchExtensions::REGISTER_FILTERS_ACTION, $callback );

		try {
			$this->expectException( LogicException::class );

			$pipeline->compose( new SearchCriteria() );
		} finally {
			remove_action( WordPressCourseSearchExtensions::REGISTER_FILTERS_ACTION, $callback );
		}
	}

	/**
	 * Invalid criteria hook output reports misuse and falls back safely.
	 */
	public function test_invalid_search_criteria_hook_return_falls_back(): void {
		$extensions = new WordPressCourseSearchExtensions();
		$criteria   = new SearchCriteria();
		$callback   = static function ( SearchCriteria $current ): string {
			unset( $current );

			return 'invalid';
		};

		$this->setExpectedIncorrectUsage( WordPressCourseSearchExtensions::class . '::search_criteria' );
		add_filter( WordPressCourseSearchExtensions::SEARCH_CRITERIA_FILTER, $callback );

		try {
			$result = $extensions->search_criteria( $criteria );
		} finally {
			remove_filter( WordPressCourseSearchExtensions::SEARCH_CRITERIA_FILTER, $callback );
		}

		self::assertSame( $criteria, $result );
	}

	/**
	 * Invalid query hook output reports misuse and falls back safely.
	 */
	public function test_invalid_course_query_hook_return_falls_back(): void {
		$extensions = new WordPressCourseSearchExtensions();
		$criteria   = new SearchCriteria();
		$query      = CourseQuery::empty( ResultOrder::default() );
		$callback   = static function ( CourseQuery $current ): string {
			unset( $current );

			return 'invalid';
		};

		$this->setExpectedIncorrectUsage( WordPressCourseSearchExtensions::class . '::course_query' );
		add_filter( WordPressCourseSearchExtensions::COURSE_QUERY_FILTER, $callback );

		try {
			$result = $extensions->course_query( $query, $criteria );
		} finally {
			remove_filter( WordPressCourseSearchExtensions::COURSE_QUERY_FILTER, $callback );
		}

		self::assertSame( $query, $result );
	}

	/**
	 * Invalid order hook output reports misuse and falls back safely.
	 */
	public function test_invalid_result_order_hook_return_falls_back(): void {
		$extensions = new WordPressCourseSearchExtensions();
		$criteria   = new SearchCriteria();
		$order      = ResultOrder::default();
		$query      = CourseQuery::empty( $order );
		$callback   = static function ( ResultOrder $current ): string {
			unset( $current );

			return 'invalid';
		};

		$this->setExpectedIncorrectUsage( WordPressCourseSearchExtensions::class . '::result_order' );
		add_filter( WordPressCourseSearchExtensions::RESULT_ORDER_FILTER, $callback );

		try {
			$result = $extensions->result_order( $order, $criteria, $query );
		} finally {
			remove_filter( WordPressCourseSearchExtensions::RESULT_ORDER_FILTER, $callback );
		}

		self::assertSame( $order, $result );
	}
}
