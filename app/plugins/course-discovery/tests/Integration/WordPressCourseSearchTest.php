<?php
/**
 * Integration tests for WordPress Course search execution.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Tests\Integration;

use InvalidArgumentException;
use LogicException;
use OxfordInternational\CourseDiscovery\Plugin;
use OxfordInternational\CourseDiscovery\Application\Search\Condition\ProviderCondition;
use OxfordInternational\CourseDiscovery\Application\Search\CourseQuery;
use OxfordInternational\CourseDiscovery\Application\Search\CourseQueryConditionInterface;
use OxfordInternational\CourseDiscovery\Application\Search\CourseSearchResult;
use OxfordInternational\CourseDiscovery\Application\Search\ResultOrder;
use OxfordInternational\CourseDiscovery\Application\Search\SearchCriteria;
use OxfordInternational\CourseDiscovery\Application\Search\SearchTerm;
use OxfordInternational\CourseDiscovery\Domain\Category\CategoryId;
use OxfordInternational\CourseDiscovery\Domain\Course\CourseId;
use OxfordInternational\CourseDiscovery\Domain\Course\StartDate;
use OxfordInternational\CourseDiscovery\Domain\Location\LocationId;
use OxfordInternational\CourseDiscovery\Domain\Provider\ProviderId;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseCategoryTaxonomy;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseMetadataStore;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CoursePostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\LocationTaxonomy;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\ProviderPostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\WordPressConditionTranslatorInterface;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\WordPressConditionTranslatorRegistry;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\WordPressCourseSearch;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\WordPressQueryConstraint;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\Translator\ProviderConditionTranslator;
use OxfordInternational\CourseDiscovery\Tests\Unit\Search\Support\CustomCondition;
use WP_UnitTestCase;

/**
 * Verifies Course search semantics against real WordPress storage and WP_Query.
 */
final class WordPressCourseSearchTest extends WP_UnitTestCase {
	/**
	 * Empty criteria return published Courses with deterministic pagination.
	 */
	public function test_empty_query_uses_default_ordering_and_pagination(): void {
		$catalogue = $this->create_catalogue();

		$first_page  = $this->search( new SearchCriteria(), 1, 2 );
		$second_page = $this->search( new SearchCriteria(), 2, 2 );

		self::assertSame(
			array( $catalogue['courses']['alpha'], $catalogue['courses']['beta'] ),
			self::course_values( $first_page )
		);
		self::assertSame(
			array( $catalogue['courses']['gamma'], $catalogue['courses']['zeta'] ),
			self::course_values( $second_page )
		);
		self::assertSame( 4, $first_page->total_results() );
		self::assertSame( 2, $first_page->total_pages() );
		self::assertSame( 1, $first_page->current_page() );
		self::assertSame( 2, $first_page->per_page() );
	}

	/**
	 * Provider values are OR-ed inside one Provider condition.
	 */
	public function test_provider_filter_supports_single_and_multiple_values(): void {
		$catalogue = $this->create_catalogue();

		$india          = $this->search(
			new SearchCriteria(
				null,
				array( new ProviderId( $catalogue['providers']['india'] ) )
			)
		);
		$india_or_china = $this->search(
			new SearchCriteria(
				null,
				array(
					new ProviderId( $catalogue['providers']['india'] ),
					new ProviderId( $catalogue['providers']['china'] ),
				)
			)
		);

		self::assertSame(
			array( $catalogue['courses']['alpha'], $catalogue['courses']['gamma'] ),
			self::course_values( $india )
		);
		self::assertSame(
			array(
				$catalogue['courses']['alpha'],
				$catalogue['courses']['beta'],
				$catalogue['courses']['gamma'],
			),
			self::course_values( $india_or_china )
		);
	}

	/**
	 * Locations resolve through Provider taxonomy terms and Course relationships.
	 */
	public function test_location_filter_is_derived_from_providers_with_or_semantics(): void {
		$catalogue = $this->create_catalogue();

		$india          = $this->search(
			new SearchCriteria(
				null,
				array(),
				array( new LocationId( $catalogue['locations']['india'] ) )
			)
		);
		$india_or_china = $this->search(
			new SearchCriteria(
				null,
				array(),
				array(
					new LocationId( $catalogue['locations']['india'] ),
					new LocationId( $catalogue['locations']['china'] ),
				)
			)
		);

		self::assertSame(
			array( $catalogue['courses']['alpha'], $catalogue['courses']['gamma'] ),
			self::course_values( $india )
		);
		self::assertSame(
			array(
				$catalogue['courses']['alpha'],
				$catalogue['courses']['beta'],
				$catalogue['courses']['gamma'],
			),
			self::course_values( $india_or_china )
		);
	}

	/**
	 * A selected Location without any Provider produces no Course matches.
	 */
	public function test_location_without_matching_provider_returns_no_courses(): void {
		$this->create_catalogue();
		$location_id = $this->create_term( 'Unassigned Location', LocationTaxonomy::TAXONOMY );
		$result      = $this->search(
			new SearchCriteria(
				null,
				array(),
				array( new LocationId( $location_id ) )
			)
		);

		self::assertSame( array(), self::course_values( $result ) );
		self::assertSame( 0, $result->total_results() );
	}

	/**
	 * Start-date values are OR-ed inside one metadata condition.
	 */
	public function test_start_date_filter_supports_single_and_multiple_values(): void {
		$catalogue = $this->create_catalogue();

		$year_2027   = $this->search(
			new SearchCriteria(
				null,
				array(),
				array(),
				array( new StartDate( '2027-01' ) )
			)
		);
		$either_date = $this->search(
			new SearchCriteria(
				null,
				array(),
				array(),
				array( new StartDate( '2026-09' ), new StartDate( '2027-01' ) )
			)
		);

		self::assertSame(
			array( $catalogue['courses']['beta'], $catalogue['courses']['gamma'] ),
			self::course_values( $year_2027 )
		);
		self::assertSame(
			array(
				$catalogue['courses']['alpha'],
				$catalogue['courses']['beta'],
				$catalogue['courses']['gamma'],
			),
			self::course_values( $either_date )
		);
	}

	/**
	 * Hierarchical Categories include descendants and OR multiple selections.
	 */
	public function test_category_filter_supports_hierarchy_and_multiple_values(): void {
		$catalogue = $this->create_catalogue();

		$design                = $this->search(
			new SearchCriteria(
				null,
				array(),
				array(),
				array(),
				array( new CategoryId( $catalogue['categories']['design'] ) )
			)
		);
		$design_or_engineering = $this->search(
			new SearchCriteria(
				null,
				array(),
				array(),
				array(),
				array(
					new CategoryId( $catalogue['categories']['design'] ),
					new CategoryId( $catalogue['categories']['engineering'] ),
				)
			)
		);

		self::assertSame(
			array(
				$catalogue['courses']['alpha'],
				$catalogue['courses']['gamma'],
				$catalogue['courses']['zeta'],
			),
			self::course_values( $design )
		);
		self::assertSame(
			array(
				$catalogue['courses']['alpha'],
				$catalogue['courses']['beta'],
				$catalogue['courses']['gamma'],
				$catalogue['courses']['zeta'],
			),
			self::course_values( $design_or_engineering )
		);
	}

	/**
	 * Native WordPress search covers Course title, excerpt, and content.
	 */
	public function test_text_search_matches_all_required_native_fields(): void {
		$catalogue = $this->create_catalogue();

		self::assertSame(
			array( $catalogue['courses']['alpha'] ),
			self::course_values( $this->search( new SearchCriteria( new SearchTerm( 'titleneedle' ) ) ) )
		);
		self::assertSame(
			array( $catalogue['courses']['beta'] ),
			self::course_values( $this->search( new SearchCriteria( new SearchTerm( 'excerptneedle' ) ) ) )
		);
		self::assertSame(
			array( $catalogue['courses']['gamma'] ),
			self::course_values( $this->search( new SearchCriteria( new SearchTerm( 'contentneedle' ) ) ) )
		);
	}

	/**
	 * Different filter conditions are AND-ed into the expected intersection.
	 */
	public function test_combined_filters_return_only_the_cross_condition_intersection(): void {
		$catalogue = $this->create_catalogue();
		$criteria  = new SearchCriteria(
			null,
			array( new ProviderId( $catalogue['providers']['india'] ) ),
			array( new LocationId( $catalogue['locations']['china'] ) ),
			array( new StartDate( '2027-01' ) ),
			array( new CategoryId( $catalogue['categories']['design'] ) )
		);

		self::assertSame(
			array( $catalogue['courses']['gamma'] ),
			self::course_values( $this->search( $criteria ) )
		);
	}

	/**
	 * Provider is AND-ed independently with Location and Category conditions.
	 */
	public function test_provider_is_anded_with_location_and_category(): void {
		$catalogue = $this->create_catalogue();

		$provider_and_location = new SearchCriteria(
			null,
			array( new ProviderId( $catalogue['providers']['india'] ) ),
			array( new LocationId( $catalogue['locations']['china'] ) )
		);
		$provider_and_category = new SearchCriteria(
			null,
			array( new ProviderId( $catalogue['providers']['china'] ) ),
			array(),
			array(),
			array( new CategoryId( $catalogue['categories']['design'] ) )
		);

		self::assertSame(
			array( $catalogue['courses']['gamma'] ),
			self::course_values( $this->search( $provider_and_location ) )
		);
		self::assertSame(
			array( $catalogue['courses']['gamma'] ),
			self::course_values( $this->search( $provider_and_category ) )
		);
	}

	/**
	 * Semantic result-order keys are translated only inside WordPress.
	 */
	public function test_custom_result_order_is_translated_through_wordpress_hook(): void {
		$catalogue    = $this->create_catalogue();
		$order_filter = static function (
			array $arguments,
			ResultOrder $result_order,
			CourseQuery $query
		): array {
			unset( $query );

			return 'title_descending' === $result_order->key()
				? array(
					'orderby' => array(
						'title' => 'DESC',
						'ID'    => 'DESC',
					),
				)
				: $arguments;
		};

		add_filter( WordPressCourseSearch::RESULT_ORDER_ARGS_FILTER, $order_filter, 10, 3 );

		try {
			$result = $this->search(
				( new SearchCriteria() )->with_result_order( new ResultOrder( 'title_descending' ) )
			);
		} finally {
			remove_filter( WordPressCourseSearch::RESULT_ORDER_ARGS_FILTER, $order_filter );
		}

		self::assertSame(
			array(
				$catalogue['courses']['zeta'],
				$catalogue['courses']['gamma'],
				$catalogue['courses']['beta'],
				$catalogue['courses']['alpha'],
			),
			self::course_values( $result )
		);
	}

	/**
	 * Invalid WordPress ordering hook output reports misuse and falls back safely.
	 */
	public function test_invalid_wordpress_result_order_hook_falls_back(): void {
		$catalogue = $this->create_catalogue();
		$callback  = static function ( array $arguments ): string {
			unset( $arguments );

			return 'invalid';
		};

		$this->setExpectedIncorrectUsage( WordPressCourseSearch::class . '::result_order_arguments' );
		add_filter( WordPressCourseSearch::RESULT_ORDER_ARGS_FILTER, $callback );

		try {
			$result = $this->search( new SearchCriteria() );
		} finally {
			remove_filter( WordPressCourseSearch::RESULT_ORDER_ARGS_FILTER, $callback );
		}

		self::assertSame(
			array(
				$catalogue['courses']['alpha'],
				$catalogue['courses']['beta'],
				$catalogue['courses']['gamma'],
				$catalogue['courses']['zeta'],
			),
			self::course_values( $result )
		);
	}

	/**
	 * Third parties can register a custom condition translator through WordPress.
	 */
	public function test_custom_condition_translator_registration_is_extensible_and_isolated(): void {
		$catalogue  = $this->create_catalogue();
		$translator = new class( $catalogue['courses']['gamma'] ) implements WordPressConditionTranslatorInterface {
			/**
			 * Create the test-only custom translator.
			 *
			 * @param int $course_id Course to match.
			 */
			public function __construct(
				private readonly int $course_id
			) {
			}

			/**
			 * Return the handled custom condition key.
			 */
			public function key(): string {
				return '1_custom';
			}

			/**
			 * Restrict results to the configured Course.
			 *
			 * @param CourseQueryConditionInterface $condition Backend-independent condition.
			 *
			 * @throws InvalidArgumentException When the condition type does not match.
			 */
			public function translate( CourseQueryConditionInterface $condition ): WordPressQueryConstraint {
				if ( ! $condition instanceof CustomCondition ) {
					throw new InvalidArgumentException( 'The test translator requires CustomCondition.' );
				}

				return new WordPressQueryConstraint(
					query_arguments: array(
						'post__in' => array( $this->course_id ),
					)
				);
			}
		};
		$register   = static function (
			WordPressConditionTranslatorRegistry $registry
		) use ( $translator ): void {
			$registry->register( $translator );
		};
		$query      = CourseQuery::empty( ResultOrder::default() )
			->with_condition(
				new ProviderCondition(
					array( new ProviderId( $catalogue['providers']['india'] ) )
				)
			)
			->with_condition( new CustomCondition( '1_custom' ) );
		$search     = ( new Plugin() )->course_search();

		add_action( WordPressCourseSearch::REGISTER_TRANSLATORS_ACTION, $register );

		try {
			$first  = $search->search( $query );
			$second = $search->search( $query );
		} finally {
			remove_action( WordPressCourseSearch::REGISTER_TRANSLATORS_ACTION, $register );
		}

		self::assertSame( array( $catalogue['courses']['gamma'] ), self::course_values( $first ) );
		self::assertSame( array( $catalogue['courses']['gamma'] ), self::course_values( $second ) );
	}

	/**
	 * Extension translators cannot replace a core translator with the same key.
	 */
	public function test_extension_translator_cannot_replace_core_translator(): void {
		$callback = static function ( WordPressConditionTranslatorRegistry $registry ): void {
			$registry->register( new ProviderConditionTranslator() );
		};

		add_action( WordPressCourseSearch::REGISTER_TRANSLATORS_ACTION, $callback );

		try {
			$this->expectException( LogicException::class );

			( new Plugin() )->course_search()->search(
				CourseQuery::empty( ResultOrder::default() )
			);
		} finally {
			remove_action( WordPressCourseSearch::REGISTER_TRANSLATORS_ACTION, $callback );
		}
	}

	/**
	 * Custom conditions without backend translation fail instead of being ignored.
	 */
	public function test_untranslated_custom_condition_is_rejected(): void {
		$this->create_catalogue();
		$query = CourseQuery::empty( ResultOrder::default() )
			->with_condition( new CustomCondition( 'untranslated' ) );

		$this->expectException( LogicException::class );

		( new Plugin() )->course_search()->search( $query );
	}

	/**
	 * Compose and execute typed criteria through the real extension pipeline.
	 *
	 * @param SearchCriteria $criteria Typed Course search criteria.
	 * @param int            $page     One-based result page.
	 * @param int            $per_page Maximum results per page.
	 */
	private function search(
		SearchCriteria $criteria,
		int $page = 1,
		int $per_page = 20
	): CourseSearchResult {
		$plugin = new Plugin();

		return $plugin->course_search()->search(
			$plugin->course_filter_pipeline()->compose( $criteria ),
			$page,
			$per_page
		);
	}

	/**
	 * Create a small catalogue covering each filter dimension.
	 *
	 * @return array{
	 *     courses: array{alpha: int, beta: int, gamma: int, zeta: int},
	 *     providers: array{india: int, china: int, uk: int},
	 *     locations: array{india: int, china: int, uk: int},
	 *     categories: array{design: int, graphic_design: int, engineering: int}
	 * }
	 */
	private function create_catalogue(): array {
		$locations  = array(
			'india' => $this->create_term( 'India', LocationTaxonomy::TAXONOMY ),
			'china' => $this->create_term( 'China', LocationTaxonomy::TAXONOMY ),
			'uk'    => $this->create_term( 'United Kingdom', LocationTaxonomy::TAXONOMY ),
		);
		$providers  = array(
			'india' => $this->create_provider( 'India Provider', $locations['india'] ),
			'china' => $this->create_provider( 'China Provider', $locations['china'] ),
			'uk'    => $this->create_provider( 'UK Provider', $locations['uk'] ),
		);
		$design     = $this->create_term( 'Design', CourseCategoryTaxonomy::TAXONOMY );
		$categories = array(
			'design'         => $design,
			'graphic_design' => $this->create_term(
				'Graphic Design',
				CourseCategoryTaxonomy::TAXONOMY,
				array( 'parent' => $design )
			),
			'engineering'    => $this->create_term( 'Engineering', CourseCategoryTaxonomy::TAXONOMY ),
		);
		$courses    = array(
			'alpha' => $this->create_course(
				'Alpha Titleneedle Course',
				'Creative visual practice.',
				'Long-form design study.',
				array( $providers['india'] ),
				array( '2026-09' ),
				array( $categories['graphic_design'] )
			),
			'beta'  => $this->create_course(
				'Beta Engineering Course',
				'Includes excerptneedle for learners.',
				'Long-form engineering study.',
				array( $providers['china'] ),
				array( '2027-01' ),
				array( $categories['engineering'] )
			),
			'gamma' => $this->create_course(
				'Gamma Leadership Course',
				'Leadership short description.',
				'An immersive contentneedle programme.',
				array( $providers['india'], $providers['china'] ),
				array( '2027-01' ),
				array( $categories['design'] )
			),
			'zeta'  => $this->create_course(
				'Zeta Design Course',
				'Advanced design short description.',
				'Advanced design long description.',
				array( $providers['uk'] ),
				array( '2028-02' ),
				array( $categories['design'] )
			),
		);

		self::factory()->post->create(
			array(
				'post_type'   => CoursePostType::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => 'Draft Hidden Course',
			)
		);

		return array(
			'courses'    => $courses,
			'providers'  => $providers,
			'locations'  => $locations,
			'categories' => $categories,
		);
	}

	/**
	 * Create and assign one Provider Location.
	 *
	 * @param string $title       Provider title.
	 * @param int    $location_id Location term identifier.
	 */
	private function create_provider( string $title, int $location_id ): int {
		$provider_id = self::factory()->post->create(
			array(
				'post_type'   => ProviderPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);
		$assigned    = wp_set_object_terms(
			$provider_id,
			array( $location_id ),
			LocationTaxonomy::TAXONOMY
		);

		self::assertFalse( is_wp_error( $assigned ) );

		return $provider_id;
	}

	/**
	 * Create one Course with native content, relationships, dates, and categories.
	 *
	 * @param string $title        Course title.
	 * @param string $excerpt      Course short description.
	 * @param string $content      Course long description.
	 * @param array  $provider_ids Related Provider IDs.
	 * @param array  $start_dates  Canonical start months.
	 * @param array  $category_ids Course Category IDs.
	 *
	 * @phpstan-param list<int> $provider_ids
	 * @phpstan-param list<string> $start_dates
	 * @phpstan-param list<int> $category_ids
	 */
	private function create_course(
		string $title,
		string $excerpt,
		string $content,
		array $provider_ids,
		array $start_dates,
		array $category_ids
	): int {
		$course_id = self::factory()->post->create(
			array(
				'post_type'    => CoursePostType::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_excerpt' => $excerpt,
				'post_content' => $content,
			)
		);
		$store     = new CourseMetadataStore();

		$store->replace_providers(
			new CourseId( $course_id ),
			...array_map(
				static fn ( int $provider_id ): ProviderId => new ProviderId( $provider_id ),
				$provider_ids
			)
		);
		$store->replace_start_dates(
			new CourseId( $course_id ),
			...array_map(
				static fn ( string $start_date ): StartDate => new StartDate( $start_date ),
				$start_dates
			)
		);

		$assigned = wp_set_object_terms(
			$course_id,
			$category_ids,
			CourseCategoryTaxonomy::TAXONOMY
		);
		self::assertFalse( is_wp_error( $assigned ) );

		return $course_id;
	}

	/**
	 * Create one taxonomy term.
	 *
	 * @param string               $name     Term name.
	 * @param string               $taxonomy Registered taxonomy.
	 * @param array<string, mixed> $arguments Optional term arguments.
	 */
	private function create_term( string $name, string $taxonomy, array $arguments = array() ): int {
		$term = wp_insert_term( $name, $taxonomy, $arguments );

		if ( is_wp_error( $term ) ) {
			self::fail( $term->get_error_message() );
		}

		return $term['term_id'];
	}

	/**
	 * Return scalar Course IDs from a search result.
	 *
	 * @param CourseSearchResult $result Search result.
	 *
	 * @return list<int>
	 */
	private static function course_values( CourseSearchResult $result ): array {
		return array_map(
			static fn ( CourseId $course_id ): int => $course_id->value(),
			$result->course_ids()
		);
	}
}
