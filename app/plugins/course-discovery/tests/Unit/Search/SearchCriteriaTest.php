<?php
/**
 * Tests for typed Course search criteria.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Tests\Unit\Search;

use InvalidArgumentException;
use LogicException;
use OxfordInternational\CourseDiscovery\Application\Search\ResultOrder;
use OxfordInternational\CourseDiscovery\Application\Search\SearchCriteria;
use OxfordInternational\CourseDiscovery\Application\Search\SearchCriterionInterface;
use OxfordInternational\CourseDiscovery\Application\Search\SearchTerm;
use OxfordInternational\CourseDiscovery\Domain\Category\CategoryId;
use OxfordInternational\CourseDiscovery\Domain\Course\StartDate;
use OxfordInternational\CourseDiscovery\Domain\Location\LocationId;
use OxfordInternational\CourseDiscovery\Domain\Provider\ProviderId;
use OxfordInternational\CourseDiscovery\Tests\Unit\Search\Support\DifficultyCriterion;
use PHPUnit\Framework\TestCase;

/**
 * Verifies criteria invariants, normalization, and immutability.
 */
final class SearchCriteriaTest extends TestCase {
	/**
	 * Empty criteria add no constraints and use the deterministic default order.
	 */
	public function test_empty_criteria_have_no_constraints(): void {
		$criteria = new SearchCriteria();

		self::assertTrue( $criteria->is_empty() );
		self::assertNull( $criteria->search_term() );
		self::assertSame( array(), $criteria->providers() );
		self::assertSame( array(), $criteria->locations() );
		self::assertSame( array(), $criteria->start_dates() );
		self::assertSame( array(), $criteria->categories() );
		self::assertFalse( $criteria->has_custom_criterion( DifficultyCriterion::KEY ) );
		self::assertNull( $criteria->custom_criterion( DifficultyCriterion::KEY ) );
		self::assertSame( ResultOrder::DEFAULT_KEY, $criteria->result_order()->key() );
	}

	/**
	 * Duplicate selections are normalized independently by semantic value.
	 */
	public function test_duplicate_selections_are_normalized(): void {
		$criteria = new SearchCriteria(
			new SearchTerm( '  design  ' ),
			array( new ProviderId( 2 ), new ProviderId( 1 ), new ProviderId( 2 ) ),
			array( new LocationId( 10 ), new LocationId( 20 ), new LocationId( 10 ) ),
			array( new StartDate( '2026-09' ), new StartDate( '2027-01' ), new StartDate( '2026-09' ) ),
			array( new CategoryId( 5 ), new CategoryId( 5 ) )
		);

		self::assertFalse( $criteria->is_empty() );
		self::assertSame( 'design', $criteria->search_term()?->value() );
		self::assertSame( array( 2, 1 ), self::provider_values( $criteria ) );
		self::assertSame( array( 10, 20 ), self::location_values( $criteria ) );
		self::assertSame( array( '2026-09', '2027-01' ), self::start_date_values( $criteria ) );
		self::assertSame( array( 5 ), self::category_values( $criteria ) );
	}

	/**
	 * With-methods return changed copies without mutating the original criteria.
	 */
	public function test_criteria_transformations_are_immutable(): void {
		$original = new SearchCriteria();
		$changed  = $original
			->with_search_term( new SearchTerm( 'business' ) )
			->with_providers( new ProviderId( 7 ) )
			->with_locations( new LocationId( 8 ) )
			->with_start_dates( new StartDate( '2027-02' ) )
			->with_categories( new CategoryId( 9 ) )
			->with_result_order( new ResultOrder( 'partner_priority' ) );

		self::assertTrue( $original->is_empty() );
		self::assertSame( ResultOrder::DEFAULT_KEY, $original->result_order()->key() );
		self::assertFalse( $changed->is_empty() );
		self::assertSame( 'business', $changed->search_term()?->value() );
		self::assertSame( array( 7 ), self::provider_values( $changed ) );
		self::assertSame( array( 8 ), self::location_values( $changed ) );
		self::assertSame( array( '2027-02' ), self::start_date_values( $changed ) );
		self::assertSame( array( 9 ), self::category_values( $changed ) );
		self::assertSame( 'partner_priority', $changed->result_order()->key() );
	}

	/**
	 * Typed custom criteria participate in empty semantics and immutable copies.
	 */
	public function test_custom_criteria_are_typed_and_immutable(): void {
		$beginner = new DifficultyCriterion( 'beginner' );
		$advanced = new DifficultyCriterion( 'advanced' );
		$original = new SearchCriteria();
		$with     = $original->with_custom_criterion( $beginner );
		$combined = $with->with_providers( new ProviderId( 7 ) );
		$replaced = $combined->with_replaced_custom_criterion( $advanced );

		self::assertTrue( $original->is_empty() );
		self::assertFalse( $original->has_custom_criterion( DifficultyCriterion::KEY ) );
		self::assertFalse( $with->is_empty() );
		self::assertTrue( $with->has_custom_criterion( DifficultyCriterion::KEY ) );
		self::assertSame( $beginner, $with->custom_criterion( DifficultyCriterion::KEY ) );
		self::assertSame( array( 7 ), self::provider_values( $combined ) );
		self::assertSame( $beginner, $combined->custom_criterion( DifficultyCriterion::KEY ) );
		self::assertSame( $advanced, $replaced->custom_criterion( DifficultyCriterion::KEY ) );
		self::assertSame( $beginner, $combined->custom_criterion( DifficultyCriterion::KEY ) );
	}

	/**
	 * Adding the same custom criterion key twice is rejected explicitly.
	 */
	public function test_duplicate_custom_criterion_key_is_rejected(): void {
		$criteria = ( new SearchCriteria() )->with_custom_criterion(
			new DifficultyCriterion( 'beginner' )
		);

		$this->expectException( LogicException::class );

		$criteria->with_custom_criterion( new DifficultyCriterion( 'advanced' ) );
	}

	/**
	 * Replacing a custom criterion that is not present is rejected explicitly.
	 */
	public function test_absent_custom_criterion_cannot_be_replaced(): void {
		$this->expectException( LogicException::class );

		( new SearchCriteria() )->with_replaced_custom_criterion(
			new DifficultyCriterion( 'advanced' )
		);
	}

	/**
	 * Public custom criterion keys must be stable lowercase identifiers.
	 */
	public function test_invalid_custom_criterion_key_is_rejected(): void {
		$criterion = new class() implements SearchCriterionInterface {
			/**
			 * Return an invalid test key.
			 */
			public function key(): string {
				return 'invalid key';
			}
		};

		$this->expectException( InvalidArgumentException::class );

		( new SearchCriteria() )->with_custom_criterion( $criterion );
	}

	/**
	 * Stable criterion and ordering keys may begin with a number.
	 */
	public function test_stable_extension_keys_may_begin_with_number(): void {
		$criterion = new class() implements SearchCriterionInterface {
			/**
			 * Return a stable test key.
			 */
			public function key(): string {
				return '1_custom';
			}
		};
		$criteria  = ( new SearchCriteria() )
			->with_custom_criterion( $criterion )
			->with_result_order( new ResultOrder( '1_priority' ) );

		self::assertSame( $criterion, $criteria->custom_criterion( '1_custom' ) );
		self::assertSame( '1_priority', $criteria->result_order()->key() );
	}

	/**
	 * Search terms containing no searchable text are rejected.
	 *
	 * @dataProvider invalid_search_term_provider
	 *
	 * @param string $value Invalid search term.
	 */
	public function test_invalid_search_terms_are_rejected( string $value ): void {
		$this->expectException( InvalidArgumentException::class );

		new SearchTerm( $value );
	}

	/**
	 * Search taxonomy identifiers must be positive.
	 *
	 * @dataProvider invalid_search_identifier_provider
	 *
	 * @param class-string<LocationId|CategoryId> $identifier_class Search identifier class.
	 * @param int                                 $value            Invalid identifier.
	 */
	public function test_invalid_search_identifiers_are_rejected( string $identifier_class, int $value ): void {
		$this->expectException( InvalidArgumentException::class );

		new $identifier_class( $value );
	}

	/**
	 * Ordering keys are semantic identifiers rather than backend orderby strings.
	 */
	public function test_invalid_result_order_key_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		new ResultOrder( 'post_title ASC' );
	}

	/**
	 * Invalid search terms.
	 *
	 * @return array<string, array{string}>
	 */
	public static function invalid_search_term_provider(): array {
		return array(
			'empty'      => array( '' ),
			'spaces'     => array( '   ' ),
			'line break' => array( "\n\t" ),
		);
	}

	/**
	 * Invalid taxonomy identifiers.
	 *
	 * @return array<string, array{class-string<LocationId|CategoryId>, int}>
	 */
	public static function invalid_search_identifier_provider(): array {
		return array(
			'zero Location'     => array( LocationId::class, 0 ),
			'negative Location' => array( LocationId::class, -1 ),
			'zero Category'     => array( CategoryId::class, 0 ),
			'negative Category' => array( CategoryId::class, -1 ),
		);
	}

	/**
	 * Return Provider scalar values.
	 *
	 * @param SearchCriteria $criteria Typed Course search criteria.
	 *
	 * @return list<int>
	 */
	private static function provider_values( SearchCriteria $criteria ): array {
		return array_map(
			static fn ( ProviderId $provider ): int => $provider->value(),
			$criteria->providers()
		);
	}

	/**
	 * Return Location scalar values.
	 *
	 * @param SearchCriteria $criteria Typed Course search criteria.
	 *
	 * @return list<int>
	 */
	private static function location_values( SearchCriteria $criteria ): array {
		return array_map(
			static fn ( LocationId $location ): int => $location->value(),
			$criteria->locations()
		);
	}

	/**
	 * Return start-date scalar values.
	 *
	 * @param SearchCriteria $criteria Typed Course search criteria.
	 *
	 * @return list<string>
	 */
	private static function start_date_values( SearchCriteria $criteria ): array {
		return array_map(
			static fn ( StartDate $start_date ): string => $start_date->value(),
			$criteria->start_dates()
		);
	}

	/**
	 * Return Category scalar values.
	 *
	 * @param SearchCriteria $criteria Typed Course search criteria.
	 *
	 * @return list<int>
	 */
	private static function category_values( SearchCriteria $criteria ): array {
		return array_map(
			static fn ( CategoryId $category ): int => $category->value(),
			$criteria->categories()
		);
	}
}
