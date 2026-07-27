<?php
/**
 * Integration tests for Course Discovery request and option boundaries.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Tests\Integration;

use OxfordInternational\CourseDiscovery\Application\Search\Condition\ProviderCondition;
use OxfordInternational\CourseDiscovery\Application\Search\ResultOrder;
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
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Frontend\CourseFilterOption;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Frontend\CourseFilterOptions;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Frontend\CourseSearchRequestParser;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\WordPressCourseSearchExtensions;
use WP_UnitTestCase;

/**
 * Verifies public input parsing and finite filter-option loading in WordPress.
 */
final class CourseDiscoveryFrontendBoundaryTest extends WP_UnitTestCase {
	/**
	 * Valid scalar and multi-value GET inputs become typed criteria.
	 */
	public function test_valid_request_builds_typed_criteria_and_pagination(): void {
		$request  = ( new CourseSearchRequestParser() )->parse(
			array(
				'q'           => '  design  ',
				'provider'    => array( '7', '12', '7' ),
				'location'    => array( '3', '5' ),
				'start_date'  => array( '2027-01', '2026-09' ),
				'category'    => array( '8', '9' ),
				'order'       => ResultOrder::DEFAULT_KEY,
				'course_page' => '2',
				'per_page'    => '24',
			)
		);
		$criteria = $request->criteria();

		self::assertSame( 'design', $criteria->search_term()?->value() );
		self::assertSame( array( 7, 12 ), self::provider_values( $criteria->providers() ) );
		self::assertSame( array( 3, 5 ), self::location_values( $criteria->locations() ) );
		self::assertSame( array( '2027-01', '2026-09' ), self::date_values( $criteria->start_dates() ) );
		self::assertSame( array( 8, 9 ), self::category_values( $criteria->categories() ) );
		self::assertSame( ResultOrder::DEFAULT_KEY, $criteria->result_order()->key() );
		self::assertSame( 2, $request->page() );
		self::assertSame( 24, $request->per_page() );
	}

	/**
	 * Malformed values are ignored without producing invalid domain objects.
	 */
	public function test_invalid_request_values_are_ignored_safely(): void {
		$request  = ( new CourseSearchRequestParser() )->parse(
			array(
				'q'           => array( 'not scalar' ),
				'provider'    => array( '0', '-2', 'invalid', array( '4' ) ),
				'location'    => array( '' ),
				'start_date'  => array( '01-2027', '2027-13', array( '2027-01' ) ),
				'category'    => false,
				'order'       => 'post_title',
				'course_page' => '0',
				'per_page'    => '1000',
			)
		);
		$criteria = $request->criteria();

		self::assertTrue( $criteria->is_empty() );
		self::assertSame( ResultOrder::DEFAULT_KEY, $criteria->result_order()->key() );
		self::assertSame( 1, $request->page() );
		self::assertSame( CourseSearchRequestParser::DEFAULT_PER_PAGE, $request->per_page() );
	}

	/**
	 * Core options use published Providers, all terms, and distinct ordered dates.
	 */
	public function test_filter_options_are_typed_deduplicated_and_hierarchical(): void {
		$published_provider = self::factory()->post->create(
			array(
				'post_type'   => ProviderPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Alpha Provider',
			)
		);
		self::factory()->post->create(
			array(
				'post_type'   => ProviderPostType::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => 'Hidden Provider',
			)
		);
		$location_id = $this->term_id( 'India', LocationTaxonomy::TAXONOMY );
		$parent_id   = $this->term_id( 'Design', CourseCategoryTaxonomy::TAXONOMY );
		$child_id    = $this->term_id(
			'Graphic Design',
			CourseCategoryTaxonomy::TAXONOMY,
			array( 'parent' => $parent_id )
		);
		$first       = $this->course_with_dates( 'First Course', '2027-01', '2026-09' );
		$second      = $this->course_with_dates( 'Second Course', '2028-02', '2027-01' );
		$draft       = self::factory()->post->create(
			array(
				'post_type'   => CoursePostType::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => 'Draft Course',
			)
		);
		( new CourseMetadataStore() )->replace_start_dates( new CourseId( $draft ), new StartDate( '2025-01' ) );

		self::assertNotSame( $first, $second );

		$options = ( new CourseFilterOptions() )->all();

		self::assertSame( array( (string) $published_provider ), self::option_values( $options['providers'] ) );
		self::assertSame( array( 'Alpha Provider' ), self::option_labels( $options['providers'] ) );
		self::assertSame( array( (string) $location_id ), self::option_values( $options['locations'] ) );
		self::assertSame( array( '2026-09', '2027-01', '2028-02' ), self::option_values( $options['start_dates'] ) );
		self::assertSame( array( 'September 2026', 'January 2027', 'February 2028' ), self::option_labels( $options['start_dates'] ) );
		self::assertSame( array( (string) $parent_id, (string) $child_id ), self::option_values( $options['categories'] ) );
		self::assertSame( array( 0, 1 ), self::option_depths( $options['categories'] ) );
	}

	/**
	 * Extensions can add typed options through the existing option-hook convention.
	 */
	public function test_filter_option_hook_extends_core_options(): void {
		$callback = static function ( array $options ): array {
			$options[] = new CourseFilterOption( '99', 'Extension Provider' );

			return $options;
		};
		$hook     = WordPressCourseSearchExtensions::FILTER_OPTIONS_PREFIX . ProviderCondition::KEY;

		add_filter( $hook, $callback );

		try {
			$options = ( new CourseFilterOptions() )->providers();
		} finally {
			remove_filter( $hook, $callback );
		}

		self::assertSame( array( '99' ), self::option_values( $options ) );
		self::assertSame( array( 'Extension Provider' ), self::option_labels( $options ) );
	}

	/**
	 * Create one published Course with canonical start months.
	 *
	 * @param string $title       Course title.
	 * @param string ...$date_values Canonical start months.
	 */
	private function course_with_dates( string $title, string ...$date_values ): int {
		$course_id = self::factory()->post->create(
			array(
				'post_type'   => CoursePostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);
		( new CourseMetadataStore() )->replace_start_dates(
			new CourseId( $course_id ),
			...array_map(
				static fn ( string $value ): StartDate => new StartDate( $value ),
				$date_values
			)
		);

		return $course_id;
	}

	/**
	 * Create one taxonomy term and return its ID.
	 *
	 * @param string               $name      Term name.
	 * @param string               $taxonomy  Registered taxonomy.
	 * @param array<string, mixed> $arguments Optional term arguments.
	 */
	private function term_id( string $name, string $taxonomy, array $arguments = array() ): int {
		$term = wp_insert_term( $name, $taxonomy, $arguments );

		if ( is_wp_error( $term ) ) {
			self::fail( $term->get_error_message() );
		}

		return $term['term_id'];
	}

	/**
	 * Return Provider scalar values.
	 *
	 * @param array $providers Provider identifiers.
	 *
	 * @return list<int>
	 *
	 * @phpstan-param list<ProviderId> $providers
	 */
	private static function provider_values( array $providers ): array {
		return array_map( static fn ( ProviderId $provider ): int => $provider->value(), $providers );
	}

	/**
	 * Return Location scalar values.
	 *
	 * @param array $locations Location identifiers.
	 *
	 * @return list<int>
	 *
	 * @phpstan-param list<LocationId> $locations
	 */
	private static function location_values( array $locations ): array {
		return array_map( static fn ( LocationId $location ): int => $location->value(), $locations );
	}

	/**
	 * Return start-date scalar values.
	 *
	 * @param array $dates Canonical start months.
	 *
	 * @return list<string>
	 *
	 * @phpstan-param list<StartDate> $dates
	 */
	private static function date_values( array $dates ): array {
		return array_map( static fn ( StartDate $date ): string => $date->value(), $dates );
	}

	/**
	 * Return Category scalar values.
	 *
	 * @param array $categories Category identifiers.
	 *
	 * @return list<int>
	 *
	 * @phpstan-param list<CategoryId> $categories
	 */
	private static function category_values( array $categories ): array {
		return array_map( static fn ( CategoryId $category ): int => $category->value(), $categories );
	}

	/**
	 * Return option values.
	 *
	 * @param array $options Typed filter options.
	 *
	 * @return list<string>
	 *
	 * @phpstan-param list<CourseFilterOption> $options
	 */
	private static function option_values( array $options ): array {
		return array_map( static fn ( CourseFilterOption $option ): string => $option->value(), $options );
	}

	/**
	 * Return option labels.
	 *
	 * @param array $options Typed filter options.
	 *
	 * @return list<string>
	 *
	 * @phpstan-param list<CourseFilterOption> $options
	 */
	private static function option_labels( array $options ): array {
		return array_map( static fn ( CourseFilterOption $option ): string => $option->label(), $options );
	}

	/**
	 * Return option hierarchy depths.
	 *
	 * @param array $options Typed filter options.
	 *
	 * @return list<int>
	 *
	 * @phpstan-param list<CourseFilterOption> $options
	 */
	private static function option_depths( array $options ): array {
		return array_map( static fn ( CourseFilterOption $option ): int => $option->depth(), $options );
	}
}
