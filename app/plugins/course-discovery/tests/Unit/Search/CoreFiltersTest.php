<?php
/**
 * Tests for core Course filters.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Tests\Unit\Search;

use OxfordInternational\CourseDiscovery\Application\Search\Condition\CategoryCondition;
use OxfordInternational\CourseDiscovery\Application\Search\Condition\LocationCondition;
use OxfordInternational\CourseDiscovery\Application\Search\Condition\ProviderCondition;
use OxfordInternational\CourseDiscovery\Application\Search\Condition\StartDateCondition;
use OxfordInternational\CourseDiscovery\Application\Search\Condition\TextCondition;
use OxfordInternational\CourseDiscovery\Application\Search\CourseFilterInterface;
use OxfordInternational\CourseDiscovery\Application\Search\CourseQuery;
use OxfordInternational\CourseDiscovery\Application\Search\ResultOrder;
use OxfordInternational\CourseDiscovery\Application\Search\SearchCriteria;
use OxfordInternational\CourseDiscovery\Application\Search\SearchTerm;
use OxfordInternational\CourseDiscovery\Application\Search\Filter\CategoryFilter;
use OxfordInternational\CourseDiscovery\Application\Search\Filter\LocationFilter;
use OxfordInternational\CourseDiscovery\Application\Search\Filter\ProviderFilter;
use OxfordInternational\CourseDiscovery\Application\Search\Filter\StartDateFilter;
use OxfordInternational\CourseDiscovery\Application\Search\Filter\TextFilter;
use OxfordInternational\CourseDiscovery\Domain\Course\StartDate;
use OxfordInternational\CourseDiscovery\Domain\Category\CategoryId;
use OxfordInternational\CourseDiscovery\Domain\Location\LocationId;
use OxfordInternational\CourseDiscovery\Domain\Provider\ProviderId;
use PHPUnit\Framework\TestCase;

/**
 * Verifies core filter support and AND/OR composition semantics.
 */
final class CoreFiltersTest extends TestCase {
	/**
	 * Empty criteria never add meaningless filter conditions.
	 *
	 * @dataProvider core_filter_provider
	 *
	 * @param CourseFilterInterface $filter Core filter.
	 */
	public function test_empty_criteria_do_not_add_conditions( CourseFilterInterface $filter ): void {
		$criteria = new SearchCriteria();
		$query    = self::empty_query();

		self::assertFalse( $filter->supports( $criteria ) );
		self::assertSame( $query, $filter->apply( $query, $criteria ) );
		self::assertSame( 0, $query->count() );
	}

	/**
	 * Text intent is represented without WordPress query concepts.
	 */
	public function test_text_filter_adds_course_text_condition(): void {
		$criteria  = new SearchCriteria( new SearchTerm( 'graphic design' ) );
		$condition = ( new TextFilter() )->apply( self::empty_query(), $criteria )->condition( TextCondition::KEY );

		self::assertInstanceOf( TextCondition::class, $condition );
		self::assertSame( 'graphic design', $condition->search_term()->value() );
	}

	/**
	 * One Provider produces one Provider group containing one alternative.
	 */
	public function test_one_provider_produces_one_provider_group(): void {
		$criteria  = new SearchCriteria( null, array( new ProviderId( 1 ) ) );
		$condition = ( new ProviderFilter() )->apply( self::empty_query(), $criteria )->condition( ProviderCondition::KEY );

		self::assertInstanceOf( ProviderCondition::class, $condition );
		self::assertSame( array( 1 ), self::provider_values( $condition ) );
	}

	/**
	 * Multiple Providers remain alternatives inside one Provider group.
	 */
	public function test_multiple_providers_use_or_inside_one_group(): void {
		$criteria  = new SearchCriteria( null, array( new ProviderId( 1 ), new ProviderId( 2 ) ) );
		$query     = ( new ProviderFilter() )->apply( self::empty_query(), $criteria );
		$condition = $query->condition( ProviderCondition::KEY );

		self::assertSame( 1, $query->count() );
		self::assertInstanceOf( ProviderCondition::class, $condition );
		self::assertSame( array( 1, 2 ), self::provider_values( $condition ) );
	}

	/**
	 * Multiple Locations remain alternatives inside one Location group.
	 */
	public function test_multiple_locations_use_or_inside_one_group(): void {
		$criteria  = new SearchCriteria(
			null,
			array(),
			array( new LocationId( 10 ), new LocationId( 20 ) )
		);
		$condition = ( new LocationFilter() )->apply( self::empty_query(), $criteria )->condition( LocationCondition::KEY );

		self::assertInstanceOf( LocationCondition::class, $condition );
		self::assertSame(
			array( 10, 20 ),
			array_map(
				static fn ( LocationId $location ): int => $location->value(),
				$condition->locations()
			)
		);
	}

	/**
	 * Multiple start months remain alternatives inside one start-date group.
	 */
	public function test_multiple_start_dates_use_or_inside_one_group(): void {
		$criteria  = new SearchCriteria(
			null,
			array(),
			array(),
			array( new StartDate( '2026-09' ), new StartDate( '2027-01' ) )
		);
		$condition = ( new StartDateFilter() )->apply( self::empty_query(), $criteria )->condition( StartDateCondition::KEY );

		self::assertInstanceOf( StartDateCondition::class, $condition );
		self::assertSame(
			array( '2026-09', '2027-01' ),
			array_map(
				static fn ( StartDate $start_date ): string => $start_date->value(),
				$condition->start_dates()
			)
		);
	}

	/**
	 * Multiple Categories remain alternatives inside one Category group.
	 */
	public function test_multiple_categories_use_or_inside_one_group(): void {
		$criteria  = new SearchCriteria(
			null,
			array(),
			array(),
			array(),
			array( new CategoryId( 5 ), new CategoryId( 6 ) )
		);
		$condition = ( new CategoryFilter() )->apply( self::empty_query(), $criteria )->condition( CategoryCondition::KEY );

		self::assertInstanceOf( CategoryCondition::class, $condition );
		self::assertSame(
			array( 5, 6 ),
			array_map(
				static fn ( CategoryId $category ): int => $category->value(),
				$condition->categories()
			)
		);
	}

	/**
	 * Independent filters append separate AND-ed groups without overwriting.
	 */
	public function test_independent_filters_compose_as_top_level_and_groups(): void {
		$criteria = new SearchCriteria(
			null,
			array( new ProviderId( 1 ), new ProviderId( 2 ) ),
			array( new LocationId( 10 ), new LocationId( 20 ) ),
			array(),
			array( new CategoryId( 5 ) )
		);
		$query    = self::empty_query();

		foreach ( array( new ProviderFilter(), new LocationFilter(), new CategoryFilter() ) as $filter ) {
			$query = $filter->apply( $query, $criteria );
		}

		self::assertSame( 3, $query->count() );
		self::assertSame(
			array( ProviderCondition::KEY, LocationCondition::KEY, CategoryCondition::KEY ),
			array_map(
				static fn ( object $condition ): string => $condition->key(),
				$query->conditions()
			)
		);
	}

	/**
	 * Every core filter implementation.
	 *
	 * @return array<string, array{CourseFilterInterface}>
	 */
	public static function core_filter_provider(): array {
		return array(
			'text'       => array( new TextFilter() ),
			'provider'   => array( new ProviderFilter() ),
			'location'   => array( new LocationFilter() ),
			'start date' => array( new StartDateFilter() ),
			'category'   => array( new CategoryFilter() ),
		);
	}

	/**
	 * Create an unconstrained Course query.
	 */
	private static function empty_query(): CourseQuery {
		return CourseQuery::empty( ResultOrder::default() );
	}

	/**
	 * Return Provider scalar values.
	 *
	 * @param ProviderCondition $condition Provider query condition.
	 *
	 * @return list<int>
	 */
	private static function provider_values( ProviderCondition $condition ): array {
		return array_map(
			static fn ( ProviderId $provider ): int => $provider->value(),
			$condition->providers()
		);
	}
}
