<?php
/**
 * Integration tests for the optional Course price-ceiling example.
 *
 * @package CourseDiscoveryPriceCeilingExample
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscoveryExample\PriceCeiling\Tests;

require_once dirname( __DIR__ ) . '/src/PriceCeilingCriterion.php';
require_once dirname( __DIR__ ) . '/src/PriceCeilingRequest.php';
require_once dirname( __DIR__ ) . '/src/PriceCeilingCondition.php';
require_once dirname( __DIR__ ) . '/src/PriceCeilingFilter.php';
require_once dirname( __DIR__ ) . '/src/PriceCeilingTranslator.php';

use InvalidArgumentException;
use OxfordInternational\CourseDiscovery\Application\Search\CourseFilterRegistry;
use OxfordInternational\CourseDiscovery\Application\Search\SearchCriteria;
use OxfordInternational\CourseDiscovery\Domain\Course\CourseId;
use OxfordInternational\CourseDiscovery\Domain\Course\Currency;
use OxfordInternational\CourseDiscovery\Domain\Course\Price;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseMetadataStore;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CoursePostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\WordPressConditionTranslatorRegistry;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\WordPressCourseSearch;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\WordPressCourseSearchExtensions;
use OxfordInternational\CourseDiscovery\Plugin;
use OxfordInternational\CourseDiscoveryExample\PriceCeiling\PriceCeilingCondition;
use OxfordInternational\CourseDiscoveryExample\PriceCeiling\PriceCeilingCriterion;
use OxfordInternational\CourseDiscoveryExample\PriceCeiling\PriceCeilingFilter;
use OxfordInternational\CourseDiscoveryExample\PriceCeiling\PriceCeilingRequest;
use OxfordInternational\CourseDiscoveryExample\PriceCeiling\PriceCeilingTranslator;
use WP_UnitTestCase;

/**
 * Exercises request parsing and real WordPress metadata comparison.
 */
final class PriceCeilingExampleTest extends WP_UnitTestCase {
	/**
	 * The request boundary accepts only supported currencies and exact SQL bounds.
	 */
	public function test_request_boundary_rejects_unsupported_or_unrepresentable_input(): void {
		foreach ( array( 'GBP', 'EUR', 'USD' ) as $currency_code ) {
			self::assertInstanceOf(
				PriceCeilingCriterion::class,
				PriceCeilingRequest::criterion( '1250', $currency_code )
			);
		}

		self::assertInstanceOf(
			PriceCeilingCriterion::class,
			PriceCeilingRequest::criterion(
				str_repeat( '9', 35 ) . '.' . str_repeat( '1', 30 ),
				'GBP'
			)
		);
		self::assertNull( PriceCeilingRequest::criterion( '1250', 'CAD' ) );
		self::assertNull( PriceCeilingRequest::criterion( '-1', 'GBP' ) );
		self::assertNull( PriceCeilingRequest::criterion( str_repeat( '9', 36 ), 'GBP' ) );
		self::assertNull(
			PriceCeilingRequest::criterion( '0.' . str_repeat( '1', 31 ), 'GBP' )
		);
		self::assertNull(
			PriceCeilingRequest::criterion(
				str_repeat( '0', PriceCeilingRequest::MAX_AMOUNT_INPUT_LENGTH + 1 ),
				'GBP'
			)
		);
	}

	/**
	 * Programmatic conditions cannot bypass the exact-comparison bound.
	 */
	public function test_condition_rejects_an_unrepresentable_programmatic_ceiling(): void {
		$this->expectException( InvalidArgumentException::class );

		new PriceCeilingCondition(
			Price::from_decimal( '0.' . str_repeat( '1', 31 ), Currency::GBP )
		);
	}

	/**
	 * The translator declares both the representability guard and currency match.
	 */
	public function test_translator_guards_the_decimal_cast_and_currency(): void {
		$clauses = ( new PriceCeilingTranslator() )->translate(
			new PriceCeilingCondition( Price::from_decimal( '1250', Currency::GBP ) )
		)->meta_query_clauses();

		self::assertCount( 3, $clauses );
		self::assertSame( 'REGEXP', $clauses[0]['compare'] );
		self::assertSame( PriceCeilingCriterion::REPRESENTABLE_AMOUNT_PATTERN, $clauses[0]['value'] );
		self::assertSame( '<=', $clauses[1]['compare'] );
		self::assertSame( 'DECIMAL(65,30)', $clauses[1]['type'] );
		self::assertSame( 'GBP', $clauses[2]['value'] );
	}

	/**
	 * A ceiling matches only amounts at or below it in the selected currency.
	 */
	public function test_real_search_matches_same_currency_and_excludes_other_currencies(): void {
		$matching = $this->create_course( 'GBP within ceiling', '100', Currency::GBP );
		$this->create_course( 'GBP above ceiling', '100.01', Currency::GBP );
		$this->create_course( 'EUR below numeric ceiling', '50', Currency::EUR );

		self::assertSame(
			array( $matching ),
			$this->search( Price::from_decimal( '100', Currency::GBP ) )
		);
	}

	/**
	 * Stored core prices outside DECIMAL bounds are excluded, never rounded down.
	 */
	public function test_real_search_excludes_unrepresentable_stored_precision(): void {
		$matching = $this->create_course( 'Exactly at ceiling', '100', Currency::GBP );
		$this->create_course(
			'Beyond SQL fractional precision',
			'100.' . str_repeat( '0', 30 ) . '4',
			Currency::GBP
		);
		$this->create_course(
			'Beyond SQL integer precision',
			str_repeat( '9', 36 ),
			Currency::GBP
		);

		self::assertSame(
			array( $matching ),
			$this->search( Price::from_decimal( '100', Currency::GBP ) )
		);
	}

	/**
	 * Create one published Course with a typed price.
	 */
	private function create_course( string $title, string $amount, Currency $currency ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => CoursePostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);

		( new CourseMetadataStore() )->save_price(
			new CourseId( $post_id ),
			Price::from_decimal( $amount, $currency )
		);

		return $post_id;
	}

	/**
	 * Compose the example criterion and execute it through real WP_Query.
	 *
	 * @return list<int>
	 */
	private function search( Price $maximum ): array {
		$register_filter     = static function ( CourseFilterRegistry $registry ): void {
			$registry->register( new PriceCeilingFilter() );
		};
		$register_translator = static function (
			WordPressConditionTranslatorRegistry $registry
		): void {
			$registry->register( new PriceCeilingTranslator() );
		};
		$criteria            = new SearchCriteria(
			custom_criteria: array( new PriceCeilingCriterion( $maximum ) )
		);

		add_action( WordPressCourseSearchExtensions::REGISTER_FILTERS_ACTION, $register_filter );
		add_action( WordPressCourseSearch::REGISTER_TRANSLATORS_ACTION, $register_translator );

		try {
			$plugin = new Plugin();
			$result = $plugin->course_search()->search(
				$plugin->course_filter_pipeline()->compose( $criteria )
			);
		} finally {
			remove_action( WordPressCourseSearchExtensions::REGISTER_FILTERS_ACTION, $register_filter );
			remove_action( WordPressCourseSearch::REGISTER_TRANSLATORS_ACTION, $register_translator );
		}

		return array_map(
			static fn ( CourseId $course_id ): int => $course_id->value(),
			$result->course_ids()
		);
	}
}
