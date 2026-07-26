<?php
/**
 * Integration tests for Course metadata persistence.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Tests\Integration;

use InvalidArgumentException;
use OxfordInternational\CourseDiscovery\Domain\Course\CourseId;
use OxfordInternational\CourseDiscovery\Domain\Course\Price;
use OxfordInternational\CourseDiscovery\Domain\Course\StartDate;
use OxfordInternational\CourseDiscovery\Domain\Instructor\InstructorId;
use OxfordInternational\CourseDiscovery\Domain\Provider\ProviderId;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseMeta;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseMetadataStore;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CoursePostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\InstructorPostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\ProviderPostType;
use WP_UnitTestCase;

/**
 * Verifies WordPress persistence behind the domain-oriented metadata boundary.
 */
final class CourseMetadataStoreTest extends WP_UnitTestCase {
	/**
	 * Price round-trips without introducing floating-point conversion.
	 */
	public function test_price_round_trips_as_a_decimal_string(): void {
		$course_id = new CourseId( $this->create_post( CoursePostType::POST_TYPE ) );
		$store     = new CourseMetadataStore();

		self::assertNull( $store->price( $course_id ) );

		$store->save_price( $course_id, new Price( '125.50' ) );

		$stored_price = $store->price( $course_id );

		self::assertInstanceOf( Price::class, $stored_price );
		self::assertSame( '125.5', $stored_price->value() );
		self::assertSame(
			'125.5',
			get_post_meta( $course_id->value(), CourseMeta::PRICE_KEY, true )
		);
	}

	/**
	 * Relationships and dates use independent metadata rows and typed reads.
	 */
	public function test_multiple_relationships_and_start_dates_are_representable(): void {
		$course_id      = new CourseId( $this->create_post( CoursePostType::POST_TYPE ) );
		$provider_one   = new ProviderId( $this->create_post( ProviderPostType::POST_TYPE ) );
		$provider_two   = new ProviderId( $this->create_post( ProviderPostType::POST_TYPE ) );
		$instructor_one = new InstructorId( $this->create_post( InstructorPostType::POST_TYPE ) );
		$instructor_two = new InstructorId( $this->create_post( InstructorPostType::POST_TYPE ) );
		$start_one      = new StartDate( '2026-09' );
		$start_two      = new StartDate( '2027-01' );
		$store          = new CourseMetadataStore();

		$store->replace_providers( $course_id, $provider_one, $provider_two );
		$store->replace_instructors( $course_id, $instructor_one, $instructor_two );
		$store->replace_start_dates(
			$course_id,
			$start_two,
			$start_one,
			new StartDate( '2026-09' )
		);

		self::assertSame(
			array( $provider_one->value(), $provider_two->value() ),
			array_map(
				static fn ( ProviderId $provider_id ): int => $provider_id->value(),
				$store->providers( $course_id )
			)
		);
		self::assertSame(
			array( $instructor_one->value(), $instructor_two->value() ),
			array_map(
				static fn ( InstructorId $instructor_id ): int => $instructor_id->value(),
				$store->instructors( $course_id )
			)
		);
		self::assertSame(
			array( '2026-09', '2027-01' ),
			array_map(
				static fn ( StartDate $start_date ): string => $start_date->value(),
				$store->start_dates( $course_id )
			)
		);

		self::assertSame(
			array( $provider_one->value(), $provider_two->value() ),
			array_map(
				'intval',
				get_post_meta( $course_id->value(), CourseMeta::PROVIDER_ID_KEY, false )
			)
		);
		self::assertSame(
			array( $instructor_one->value(), $instructor_two->value() ),
			array_map(
				'intval',
				get_post_meta( $course_id->value(), CourseMeta::INSTRUCTOR_ID_KEY, false )
			)
		);
		self::assertSame(
			array( '2026-09', '2027-01' ),
			get_post_meta( $course_id->value(), CourseMeta::START_DATE_KEY, false )
		);
	}

	/**
	 * The registered metadata boundary rejects non-canonical dates.
	 */
	public function test_registered_start_date_contract_rejects_invalid_values(): void {
		$course_id = $this->create_post( CoursePostType::POST_TYPE );

		( new CourseMeta() )->register();
		$this->expectException( InvalidArgumentException::class );

		add_post_meta( $course_id, CourseMeta::START_DATE_KEY, '2026-13' );
	}

	/**
	 * Replacing a collection removes stale rows.
	 */
	public function test_replacing_relationships_and_dates_removes_stale_values(): void {
		$course_id      = new CourseId( $this->create_post( CoursePostType::POST_TYPE ) );
		$provider_one   = new ProviderId( $this->create_post( ProviderPostType::POST_TYPE ) );
		$provider_two   = new ProviderId( $this->create_post( ProviderPostType::POST_TYPE ) );
		$instructor_one = new InstructorId( $this->create_post( InstructorPostType::POST_TYPE ) );
		$instructor_two = new InstructorId( $this->create_post( InstructorPostType::POST_TYPE ) );
		$store          = new CourseMetadataStore();

		$store->replace_providers( $course_id, $provider_one, $provider_two );
		$store->replace_instructors( $course_id, $instructor_one, $instructor_two );
		$store->replace_start_dates(
			$course_id,
			new StartDate( '2026-09' ),
			new StartDate( '2027-01' )
		);

		$store->replace_providers( $course_id, $provider_two );
		$store->replace_instructors( $course_id, $instructor_two );
		$store->replace_start_dates( $course_id, new StartDate( '2027-01' ) );

		self::assertSame(
			array( $provider_two->value() ),
			array_map(
				static fn ( ProviderId $provider_id ): int => $provider_id->value(),
				$store->providers( $course_id )
			)
		);
		self::assertSame(
			array( $instructor_two->value() ),
			array_map(
				static fn ( InstructorId $instructor_id ): int => $instructor_id->value(),
				$store->instructors( $course_id )
			)
		);
		self::assertSame(
			array( '2027-01' ),
			array_map(
				static fn ( StartDate $start_date ): string => $start_date->value(),
				$store->start_dates( $course_id )
			)
		);
	}

	/**
	 * Create a post of the requested content type.
	 *
	 * @param string $post_type Registered post type.
	 */
	private function create_post( string $post_type ): int {
		return self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_status' => 'publish',
			)
		);
	}
}
