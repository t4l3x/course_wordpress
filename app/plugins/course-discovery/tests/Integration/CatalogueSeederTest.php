<?php
/**
 * Integration tests for the development catalogue seeder.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Tests\Integration;

use OxfordInternational\CourseDiscovery\Domain\Course\CourseId;
use OxfordInternational\CourseDiscovery\Domain\Course\Currency;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseCategoryTaxonomy;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseMetadataStore;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CoursePostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\InstructorPostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\LocationTaxonomy;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\ProviderPostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Development\CatalogueSeeder;
use WP_Error;
use WP_Post;
use WP_Term;
use WP_UnitTestCase;

/**
 * Verifies repeatable demo generation through real WordPress persistence.
 */
final class CatalogueSeederTest extends WP_UnitTestCase {
	/**
	 * Repeated seeding updates the same complete catalogue and reset removes only it.
	 */
	public function test_seed_is_repeatable_varied_and_resettable(): void {
		$store    = new CourseMetadataStore();
		$seeder   = new CatalogueSeeder( $store );
		$first    = $seeder->seed( 30 );
		$second   = $seeder->seed( 30 );
		$course   = get_page_by_path(
			'course-discovery-demo-course-01',
			OBJECT,
			CoursePostType::POST_TYPE
		);
		$provider = get_page_by_path(
			'course-discovery-demo-oxford-global-learning',
			OBJECT,
			ProviderPostType::POST_TYPE
		);

		self::assertSame( $first, $second );
		self::assertSame(
			array(
				'courses'     => 30,
				'providers'   => 8,
				'instructors' => 12,
				'locations'   => 8,
				'categories'  => 20,
			),
			$first
		);
		self::assertCount( 30, $this->post_ids( CoursePostType::POST_TYPE ) );
		self::assertCount( 8, $this->post_ids( ProviderPostType::POST_TYPE ) );
		self::assertCount( 12, $this->post_ids( InstructorPostType::POST_TYPE ) );
		self::assertInstanceOf( WP_Post::class, $course );
		self::assertInstanceOf( WP_Post::class, $provider );

		$seeded_currencies = array();

		foreach ( $this->post_ids( CoursePostType::POST_TYPE ) as $seeded_course_id ) {
			$seeded_price = $store->price( new CourseId( $seeded_course_id ) );

			self::assertNotNull( $seeded_price );
			$seeded_currencies[ $seeded_price->currency()->value ] = true;
		}

		ksort( $seeded_currencies );

		self::assertSame(
			array(
				Currency::EUR->value => true,
				Currency::GBP->value => true,
				Currency::USD->value => true,
			),
			$seeded_currencies
		);

		$course_id = new CourseId( $course->ID );

		self::assertNotNull( $store->price( $course_id ) );
		self::assertGreaterThanOrEqual( 2, count( $store->providers( $course_id ) ) );
		self::assertGreaterThanOrEqual( 2, count( $store->instructors( $course_id ) ) );
		self::assertGreaterThanOrEqual( 2, count( $store->start_dates( $course_id ) ) );

		$course_terms   = wp_get_object_terms( $course->ID, CourseCategoryTaxonomy::TAXONOMY );
		$provider_terms = wp_get_object_terms( $provider->ID, LocationTaxonomy::TAXONOMY );

		if ( $course_terms instanceof WP_Error ) {
			self::fail( $course_terms->get_error_message() );
		}

		if ( $provider_terms instanceof WP_Error ) {
			self::fail( $provider_terms->get_error_message() );
		}

		self::assertGreaterThanOrEqual( 2, count( $course_terms ) );
		self::assertGreaterThanOrEqual( 2, count( $provider_terms ) );

		$child = get_term_by(
			'slug',
			'course-discovery-demo-management',
			CourseCategoryTaxonomy::TAXONOMY
		);

		self::assertInstanceOf( WP_Term::class, $child );
		self::assertGreaterThan( 0, $child->parent );

		$deleted = $seeder->reset();

		self::assertSame(
			array(
				'posts' => 50,
				'terms' => 28,
			),
			$deleted
		);
		self::assertCount( 0, $this->post_ids( CoursePostType::POST_TYPE ) );
		self::assertCount( 0, $this->post_ids( ProviderPostType::POST_TYPE ) );
		self::assertCount( 0, $this->post_ids( InstructorPostType::POST_TYPE ) );
	}

	/**
	 * Return published post IDs for a plugin content type.
	 *
	 * @param string $post_type Plugin post type.
	 *
	 * @return list<int>
	 */
	private function post_ids( string $post_type ): array {
		$identifiers = get_posts(
			array(
				'fields'         => 'ids',
				'post_status'    => 'publish',
				'post_type'      => $post_type,
				'posts_per_page' => -1,
			)
		);

		return array_values( $identifiers );
	}
}
