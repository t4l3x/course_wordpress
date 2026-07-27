<?php
/**
 * Tests for the Course filter registry and pipeline.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Tests\Unit\Search;

use InvalidArgumentException;
use LogicException;
use OxfordInternational\CourseDiscovery\Application\Search\Condition\ProviderCondition;
use OxfordInternational\CourseDiscovery\Application\Search\CourseFilterPipeline;
use OxfordInternational\CourseDiscovery\Application\Search\CourseFilterRegistry;
use OxfordInternational\CourseDiscovery\Application\Search\CourseQuery;
use OxfordInternational\CourseDiscovery\Application\Search\ResultOrder;
use OxfordInternational\CourseDiscovery\Application\Search\SearchCriteria;
use OxfordInternational\CourseDiscovery\Application\Search\Filter\CategoryFilter;
use OxfordInternational\CourseDiscovery\Application\Search\Filter\LocationFilter;
use OxfordInternational\CourseDiscovery\Application\Search\Filter\ProviderFilter;
use OxfordInternational\CourseDiscovery\Application\Search\Filter\StartDateFilter;
use OxfordInternational\CourseDiscovery\Application\Search\Filter\TextFilter;
use OxfordInternational\CourseDiscovery\Domain\Provider\ProviderId;
use OxfordInternational\CourseDiscovery\Tests\Unit\Search\Support\CustomCondition;
use OxfordInternational\CourseDiscovery\Tests\Unit\Search\Support\CustomFilter;
use OxfordInternational\CourseDiscovery\Tests\Unit\Search\Support\DifficultyCondition;
use OxfordInternational\CourseDiscovery\Tests\Unit\Search\Support\DifficultyCriterion;
use OxfordInternational\CourseDiscovery\Tests\Unit\Search\Support\DifficultyFilter;
use OxfordInternational\CourseDiscovery\Tests\Unit\Search\Support\PassThroughSearchExtensions;
use PHPUnit\Framework\TestCase;

/**
 * Verifies deterministic registration and contract-only pipeline composition.
 */
final class FilterRegistryPipelineTest extends TestCase {
	/**
	 * Core and third-party filters share the same small registry contract.
	 */
	public function test_core_and_additional_filters_can_be_registered(): void {
		$registry = new CourseFilterRegistry(
			new TextFilter(),
			new ProviderFilter(),
			new LocationFilter(),
			new StartDateFilter(),
			new CategoryFilter()
		);

		$registry->register( new CustomFilter() );

		self::assertTrue( $registry->has( 'text' ) );
		self::assertTrue( $registry->has( 'provider' ) );
		self::assertTrue( $registry->has( 'location' ) );
		self::assertTrue( $registry->has( 'start_date' ) );
		self::assertTrue( $registry->has( 'category' ) );
		self::assertTrue( $registry->has( 'custom' ) );
		self::assertCount( 6, $registry->filters() );
	}

	/**
	 * Duplicate filter keys fail deterministically instead of being overwritten.
	 */
	public function test_duplicate_filter_registration_is_rejected(): void {
		$registry = new CourseFilterRegistry( new ProviderFilter() );

		$this->expectException( LogicException::class );

		$registry->register( new CustomFilter( ProviderCondition::KEY ) );
	}

	/**
	 * Filter keys must be safe stable identifiers.
	 */
	public function test_invalid_filter_key_is_rejected(): void {
		$registry = new CourseFilterRegistry();

		$this->expectException( InvalidArgumentException::class );

		$registry->register( new CustomFilter( 'invalid key' ) );
	}

	/**
	 * Stable filter and condition keys may begin with a number.
	 */
	public function test_stable_filter_and_condition_keys_may_begin_with_number(): void {
		$registry = new CourseFilterRegistry( new CustomFilter( '1_custom' ) );
		$query    = ( new CourseFilterPipeline(
			$registry,
			new PassThroughSearchExtensions()
		) )->compose( new SearchCriteria() );

		self::assertTrue( $registry->has( '1_custom' ) );
		self::assertInstanceOf( CustomCondition::class, $query->condition( '1_custom' ) );
	}

	/**
	 * The pipeline applies custom filters through the contract, not concrete types.
	 */
	public function test_pipeline_operates_on_filter_contract(): void {
		$pipeline = new CourseFilterPipeline(
			new CourseFilterRegistry( new ProviderFilter(), new CustomFilter() ),
			new PassThroughSearchExtensions()
		);
		$criteria = new SearchCriteria( null, array( new ProviderId( 3 ) ) );

		$query = $pipeline->compose( $criteria );

		self::assertSame( 2, $query->count() );
		self::assertInstanceOf( ProviderCondition::class, $query->condition( ProviderCondition::KEY ) );
		self::assertInstanceOf( CustomCondition::class, $query->condition( 'custom' ) );
		self::assertSame( ResultOrder::DEFAULT_KEY, $query->result_order()->key() );
	}

	/**
	 * A third-party filter can consume typed criteria without core modifications.
	 */
	public function test_custom_filter_consumes_typed_custom_criterion(): void {
		$registry = new CourseFilterRegistry( new ProviderFilter() );
		$registry->register( new DifficultyFilter() );

		$pipeline = new CourseFilterPipeline(
			$registry,
			new PassThroughSearchExtensions()
		);
		$criteria = ( new SearchCriteria( null, array( new ProviderId( 3 ) ) ) )
			->with_custom_criterion( new DifficultyCriterion( 'advanced' ) );

		$query = $pipeline->compose( $criteria );

		self::assertInstanceOf( ProviderCondition::class, $query->condition( ProviderCondition::KEY ) );
		$difficulty = $query->condition( DifficultyCondition::KEY );

		self::assertInstanceOf( DifficultyCondition::class, $difficulty );
		self::assertSame( 'advanced', $difficulty->level() );
		self::assertSame(
			array( ProviderCondition::KEY, DifficultyCondition::KEY ),
			self::condition_keys( $query )
		);
	}

	/**
	 * A custom filter adds nothing when its typed criterion is absent.
	 */
	public function test_custom_filter_ignores_absent_custom_criterion(): void {
		$filter   = new DifficultyFilter();
		$criteria = new SearchCriteria();
		$query    = CourseQuery::empty( ResultOrder::default() );

		self::assertFalse( $filter->supports( $criteria ) );
		self::assertSame( $query, $filter->apply( $query, $criteria ) );
	}

	/**
	 * Every composition run receives a fresh registry and stable result.
	 */
	public function test_pipeline_composition_is_repeatable(): void {
		$pipeline = new CourseFilterPipeline(
			new CourseFilterRegistry( new CustomFilter() ),
			new PassThroughSearchExtensions()
		);

		$first  = $pipeline->compose( new SearchCriteria() );
		$second = $pipeline->compose( new SearchCriteria() );

		self::assertSame( array( 'custom' ), self::condition_keys( $first ) );
		self::assertSame( array( 'custom' ), self::condition_keys( $second ) );
	}

	/**
	 * Query conditions cannot silently overwrite an existing filter group.
	 */
	public function test_query_rejects_duplicate_condition_keys(): void {
		$query = CourseQuery::empty( ResultOrder::default() )
			->with_condition( new CustomCondition() );

		$this->expectException( LogicException::class );

		$query->with_condition( new CustomCondition() );
	}

	/**
	 * Conditions can be removed without mutating the original query.
	 */
	public function test_query_removes_conditions_immutably(): void {
		$query = CourseQuery::empty( ResultOrder::default() )
			->with_condition( new CustomCondition( 'first' ) )
			->with_condition( new CustomCondition( 'second' ) );

		$without_first = $query->without_condition( 'first' );

		self::assertSame( array( 'first', 'second' ), self::condition_keys( $query ) );
		self::assertSame( array( 'second' ), self::condition_keys( $without_first ) );
		self::assertSame( $without_first, $without_first->without_condition( 'first' ) );
	}

	/**
	 * Existing conditions can be replaced without changing registration order.
	 */
	public function test_query_replaces_conditions_immutably(): void {
		$original    = new CustomCondition( 'first' );
		$replacement = new CustomCondition( 'first' );
		$query       = CourseQuery::empty( ResultOrder::default() )
			->with_condition( $original )
			->with_condition( new CustomCondition( 'second' ) );

		$replaced = $query->with_replaced_condition( $replacement );

		self::assertSame( $original, $query->condition( 'first' ) );
		self::assertSame( $replacement, $replaced->condition( 'first' ) );
		self::assertSame( array( 'first', 'second' ), self::condition_keys( $replaced ) );
	}

	/**
	 * Replacement cannot silently add a condition that is not present.
	 */
	public function test_query_rejects_replacement_for_absent_condition(): void {
		$query = CourseQuery::empty( ResultOrder::default() );

		$this->expectException( LogicException::class );

		$query->with_replaced_condition( new CustomCondition() );
	}

	/**
	 * Return condition keys from a Course query.
	 *
	 * @param CourseQuery $query Composed Course query.
	 *
	 * @return list<string>
	 */
	private static function condition_keys( CourseQuery $query ): array {
		return array_map(
			static fn ( object $condition ): string => $condition->key(),
			$query->conditions()
		);
	}
}
