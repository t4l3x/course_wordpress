<?php
/**
 * Integration tests for public Course result presentation.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Tests\Integration;

use OxfordInternational\CourseDiscovery\Application\Search\CourseSearchResult;
use OxfordInternational\CourseDiscovery\Domain\Course\CourseId;
use OxfordInternational\CourseDiscovery\Domain\Course\Currency;
use OxfordInternational\CourseDiscovery\Domain\Course\Price;
use OxfordInternational\CourseDiscovery\Domain\Course\StartDate;
use OxfordInternational\CourseDiscovery\Domain\Instructor\InstructorId;
use OxfordInternational\CourseDiscovery\Domain\Provider\ProviderId;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseMeta;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseMetadataStore;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CoursePostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\InstructorPostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\ProviderPostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Frontend\CourseFilterOptions;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Frontend\CoursePriceFormatter;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Frontend\CourseResultPresenter;
use WP_UnitTestCase;

/**
 * Verifies that typed stored prices become explicit public display values.
 */
final class CourseResultPresenterTest extends WP_UnitTestCase {
	/**
	 * The presenter retains the ISO code alongside the human-friendly symbol amount.
	 */
	public function test_presenter_formats_price_and_retains_currency_code(): void {
		$course_id = self::factory()->post->create(
			array(
				'post_type'    => CoursePostType::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Euro Course',
				'post_excerpt' => 'Course summary.',
			)
		);
		$course    = new CourseId( $course_id );
		$store     = new CourseMetadataStore();

		$store->save_price( $course, Price::from_decimal( '980', Currency::EUR ) );

		$cards = ( new CourseResultPresenter( $store, new CoursePriceFormatter() ) )->prepare(
			new CourseSearchResult( array( $course ), 1, 1, 20 )
		);

		self::assertCount( 1, $cards );
		self::assertSame( '€980.00', $cards[0]['price'] );
		self::assertSame( 'EUR', $cards[0]['price_currency'] );
	}

	/**
	 * Invalid price metadata does not hide valid unrelated Course details.
	 */
	public function test_invalid_price_does_not_erase_relationships_or_start_dates(): void {
		$course_id     = self::factory()->post->create(
			array(
				'post_type'   => CoursePostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Course with an incomplete price',
			)
		);
		$provider_id   = self::factory()->post->create(
			array(
				'post_type'   => ProviderPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Example Provider',
			)
		);
		$instructor_id = self::factory()->post->create(
			array(
				'post_type'   => InstructorPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Example Instructor',
			)
		);
		$course        = new CourseId( $course_id );
		$start_date    = new StartDate( '2027-09' );
		$store         = new CourseMetadataStore();

		$store->replace_providers( $course, new ProviderId( $provider_id ) );
		$store->replace_instructors( $course, new InstructorId( $instructor_id ) );
		$store->replace_start_dates( $course, $start_date );
		add_post_meta( $course_id, CourseMeta::PRICE_AMOUNT_KEY, '1250' );

		$cards = ( new CourseResultPresenter( $store, new CoursePriceFormatter() ) )->prepare(
			new CourseSearchResult( array( $course ), 1, 1, 20 )
		);

		self::assertCount( 1, $cards );
		self::assertNull( $cards[0]['price'] );
		self::assertNull( $cards[0]['price_currency'] );
		self::assertSame( array( 'Example Provider' ), $cards[0]['providers'] );
		self::assertSame( array( 'Example Instructor' ), $cards[0]['instructors'] );
		self::assertSame(
			array( CourseFilterOptions::start_date_label( $start_date ) ),
			$cards[0]['start_dates']
		);
	}
}
