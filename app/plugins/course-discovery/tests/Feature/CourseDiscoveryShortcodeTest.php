<?php
/**
 * Feature tests for the public Course Discovery shortcode.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Tests\Feature;

use OxfordInternational\CourseDiscovery\Application\Search\Condition\ProviderCondition;
use OxfordInternational\CourseDiscovery\Domain\Course\CourseId;
use OxfordInternational\CourseDiscovery\Domain\Course\Currency;
use OxfordInternational\CourseDiscovery\Domain\Course\Price;
use OxfordInternational\CourseDiscovery\Domain\Course\StartDate;
use OxfordInternational\CourseDiscovery\Domain\Instructor\InstructorId;
use OxfordInternational\CourseDiscovery\Domain\Provider\ProviderId;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseCategoryTaxonomy;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseMetadataStore;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CoursePostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\InstructorPostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\LocationTaxonomy;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\ProviderPostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Frontend\CourseDiscoveryShortcode;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Frontend\CourseFilterOption;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Frontend\CourseSearchRequestParser;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\WordPressCourseSearchExtensions;
use WP_UnitTestCase;

/**
 * Verifies the complete server-rendered frontend flow through real WordPress.
 */
final class CourseDiscoveryShortcodeTest extends WP_UnitTestCase {
	/**
	 * The registered shortcode renders the plugin-owned empty state.
	 */
	public function test_shortcode_is_registered_and_renders_no_courses_state(): void {
		self::assertTrue( shortcode_exists( CourseDiscoveryShortcode::SHORTCODE ) );

		$output = $this->render_shortcode();

		self::assertStringContainsString( 'class="course-discovery alignfull"', $output );
		self::assertStringContainsString( 'Discover your next course', $output );
		self::assertStringContainsString(
			'maxlength="' . CourseSearchRequestParser::MAX_SEARCH_TERM_LENGTH . '"',
			$output
		);
		self::assertStringContainsString( '0 courses found', $output );
		self::assertStringContainsString( 'No courses are available yet.', $output );
	}

	/**
	 * Results render modeled fields, selected controls, escaped labels, and assets.
	 */
	public function test_shortcode_renders_selected_results_and_escapes_output(): void {
		$catalogue = $this->create_catalogue( 1 );
		$hook      = WordPressCourseSearchExtensions::FILTER_OPTIONS_PREFIX . ProviderCondition::KEY;
		$callback  = static function ( array $options ): array {
			$options[] = new CourseFilterOption( '999', '<script>Unsafe option</script>' );

			return $options;
		};

		add_filter( $hook, $callback );

		try {
			$output = $this->render_shortcode(
				array(
					'provider' => array( (string) $catalogue['provider'] ),
				)
			);
		} finally {
			remove_filter( $hook, $callback );
		}

		self::assertStringContainsString( '1 course found', $output );
		self::assertStringContainsString( 'Shared Course', $output );
		self::assertStringContainsString( 'Useful short description', $output );
		self::assertStringContainsString( '$1,250.50', $output );
		self::assertStringContainsString( '(USD)', $output );
		self::assertStringContainsString( 'Published Provider', $output );
		self::assertStringContainsString( 'India', $output );
		self::assertStringContainsString( 'Course Instructor', $output );
		self::assertStringContainsString( 'January 2027', $output );
		self::assertStringContainsString( 'Design', $output );
		self::assertStringContainsString( 'checked=', $output );
		self::assertStringContainsString( 'value="' . $catalogue['provider'] . '"', $output );
		self::assertStringContainsString( 'class="course-discovery__active-filter"', $output );
		self::assertStringContainsString( 'Remove Published Provider filter', $output );
		self::assertStringNotContainsString( '<script>', $output );
		self::assertStringContainsString( '&lt;script&gt;Unsafe option&lt;/script&gt;', $output );
		self::assertTrue( wp_style_is( CourseDiscoveryShortcode::STYLE_HANDLE, 'enqueued' ) );
		self::assertTrue( wp_script_is( CourseDiscoveryShortcode::SCRIPT_HANDLE, 'enqueued' ) );
	}

	/**
	 * A valid search with no matches renders the filtered empty state and reset link.
	 */
	public function test_shortcode_renders_no_matching_courses_state(): void {
		$this->create_catalogue( 1 );

		$output = $this->render_shortcode( array( 'q' => 'no-such-course-keyword' ) );

		self::assertStringContainsString( '0 courses found', $output );
		self::assertStringContainsString( 'No courses match your current filters.', $output );
		self::assertStringContainsString( 'Clear all filters', $output );
	}

	/**
	 * Pagination links retain every active canonical filter value.
	 */
	public function test_pagination_preserves_active_filters(): void {
		$catalogue = $this->create_catalogue( 2 );
		$output    = $this->render_shortcode(
			array(
				'q'          => 'sharedkeyword',
				'provider'   => array( (string) $catalogue['provider'] ),
				'location'   => array( (string) $catalogue['location'] ),
				'start_date' => array( '2027-01' ),
				'category'   => array( (string) $catalogue['category'] ),
				'per_page'   => '1',
			)
		);

		self::assertSame( 1, preg_match( '/href="([^"]*course_page=2[^"]*)"/', $output, $matches ) );

		$url   = html_entity_decode( $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$query = wp_parse_url( $url, PHP_URL_QUERY );
		self::assertIsString( $query );
		parse_str( $query, $arguments );

		self::assertSame( 'sharedkeyword', $arguments['q'] );
		self::assertSame( array( (string) $catalogue['provider'] ), $arguments['provider'] );
		self::assertSame( array( (string) $catalogue['location'] ), $arguments['location'] );
		self::assertSame( array( '2027-01' ), $arguments['start_date'] );
		self::assertSame( array( (string) $catalogue['category'] ), $arguments['category'] );
		self::assertSame( '1', $arguments['per_page'] );
		self::assertSame( '2', $arguments['course_page'] );
	}

	/**
	 * Create a complete catalogue shared by frontend feature tests.
	 *
	 * @param int $course_count Number of matching Courses to create.
	 *
	 * @return array{provider: int, location: int, category: int}
	 */
	private function create_catalogue( int $course_count ): array {
		$location_id   = $this->term_id( 'India', LocationTaxonomy::TAXONOMY );
		$category_id   = $this->term_id( 'Design', CourseCategoryTaxonomy::TAXONOMY );
		$provider_id   = self::factory()->post->create(
			array(
				'post_type'   => ProviderPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Published Provider',
			)
		);
		$instructor_id = self::factory()->post->create(
			array(
				'post_type'   => InstructorPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Course Instructor',
			)
		);
		$assigned      = wp_set_object_terms( $provider_id, array( $location_id ), LocationTaxonomy::TAXONOMY );

		self::assertFalse( is_wp_error( $assigned ) );

		for ( $index = 1; $index <= $course_count; ++$index ) {
			$course_id = self::factory()->post->create(
				array(
					'post_type'    => CoursePostType::POST_TYPE,
					'post_status'  => 'publish',
					'post_title'   => 1 === $index
						? 'Shared Course <script>alert(1)</script>'
						: 'Shared Course ' . $index,
					'post_excerpt' => '<strong>Useful short description</strong>',
					'post_content' => 'Long sharedkeyword course description.',
				)
			);
			$store     = new CourseMetadataStore();

			$store->save_price(
				new CourseId( $course_id ),
				Price::from_decimal( '1250.50', Currency::USD )
			);
			$store->replace_providers( new CourseId( $course_id ), new ProviderId( $provider_id ) );
			$store->replace_instructors( new CourseId( $course_id ), new InstructorId( $instructor_id ) );
			$store->replace_start_dates( new CourseId( $course_id ), new StartDate( '2027-01' ) );

			$assigned = wp_set_object_terms( $course_id, array( $category_id ), CourseCategoryTaxonomy::TAXONOMY );
			self::assertFalse( is_wp_error( $assigned ) );
		}

		return array(
			'provider' => $provider_id,
			'location' => $location_id,
			'category' => $category_id,
		);
	}

	/**
	 * Render the registered shortcode with one synthetic public GET request.
	 *
	 * @param array<string, mixed> $query Public query values.
	 */
	private function render_shortcode( array $query = array() ): string {
		$_GET = $query;

		try {
			return do_shortcode( '[' . CourseDiscoveryShortcode::SHORTCODE . ']' );
		} finally {
			$_GET = array();
		}
	}

	/**
	 * Create one taxonomy term and return its ID.
	 *
	 * @param string $name     Term name.
	 * @param string $taxonomy Registered taxonomy.
	 */
	private function term_id( string $name, string $taxonomy ): int {
		$term = wp_insert_term( $name, $taxonomy );

		if ( is_wp_error( $term ) ) {
			self::fail( $term->get_error_message() );
		}

		return $term['term_id'];
	}
}
