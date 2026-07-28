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
use RuntimeException;
use UnexpectedValueException;
use WP_UnitTestCase;

/**
 * Verifies WordPress persistence behind the domain-oriented metadata boundary.
 */
final class CourseMetadataStoreTest extends WP_UnitTestCase {
	/**
	 * Price amount and currency round-trip through independent metadata keys.
	 */
	public function test_price_round_trips_as_independent_amount_and_currency_values(): void {
		$course_id = new CourseId( $this->create_post( CoursePostType::POST_TYPE ) );
		$store     = new CourseMetadataStore();

		self::assertNull( $store->price( $course_id ) );

		$price = Price::from_decimal( '125.50', Currency::EUR );

		$store->save_price( $course_id, $price );
		$store->save_price( $course_id, $price );

		$stored_price = $store->price( $course_id );

		self::assertInstanceOf( Price::class, $stored_price );
		self::assertSame( '125.5', $stored_price->amount() );
		self::assertSame( Currency::EUR, $stored_price->currency() );
		self::assertSame(
			'125.5',
			get_post_meta( $course_id->value(), CourseMeta::PRICE_AMOUNT_KEY, true )
		);
		self::assertSame(
			'EUR',
			get_post_meta( $course_id->value(), CourseMeta::PRICE_CURRENCY_KEY, true )
		);
		self::assertFalse( metadata_exists( 'post', $course_id->value(), CourseMeta::LEGACY_PRICE_KEY ) );
	}

	/**
	 * A Course price can be removed and repeated removal remains safe.
	 */
	public function test_price_can_be_removed(): void {
		$course_id = new CourseId( $this->create_post( CoursePostType::POST_TYPE ) );
		$store     = new CourseMetadataStore();

		$store->save_price( $course_id, Price::from_decimal( '125.5', Currency::GBP ) );
		$store->remove_price( $course_id );
		$store->remove_price( $course_id );

		self::assertNull( $store->price( $course_id ) );
		self::assertFalse( metadata_exists( 'post', $course_id->value(), CourseMeta::PRICE_AMOUNT_KEY ) );
		self::assertFalse( metadata_exists( 'post', $course_id->value(), CourseMeta::PRICE_CURRENCY_KEY ) );
	}

	/**
	 * Failure of the second deletion restores the complete existing pair.
	 */
	public function test_failed_currency_removal_restores_the_existing_price_pair(): void {
		$course_id               = new CourseId( $this->create_post( CoursePostType::POST_TYPE ) );
		$store                   = new CourseMetadataStore();
		$reject_currency_removal = static function ( mixed $check, int $object_id, string $meta_key ): mixed {
			unset( $object_id );

			return CourseMeta::PRICE_CURRENCY_KEY === $meta_key ? false : $check;
		};

		$store->save_price( $course_id, Price::from_decimal( '125.5', Currency::USD ) );
		add_filter( 'delete_post_metadata', $reject_currency_removal, 10, 3 );

		try {
			$store->remove_price( $course_id );
			self::fail( 'A rejected price deletion should raise an exception.' );
		} catch ( RuntimeException $exception ) {
			self::assertSame( 'WordPress could not remove the course price.', $exception->getMessage() );
		} finally {
			remove_filter( 'delete_post_metadata', $reject_currency_removal );
		}

		$stored_price = $store->price( $course_id );

		self::assertInstanceOf( Price::class, $stored_price );
		self::assertSame( '125.5', $stored_price->amount() );
		self::assertSame( Currency::USD, $stored_price->currency() );
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

		$store->replace_providers( $course_id, $provider_one, $provider_two, $provider_one );
		$store->replace_instructors( $course_id, $instructor_one, $instructor_two, $instructor_one );
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
	 * A rejected price update is distinct from WordPress reporting no change.
	 */
	public function test_rejected_price_update_is_reported_and_preserves_the_existing_value(): void {
		$course_id     = new CourseId( $this->create_post( CoursePostType::POST_TYPE ) );
		$store         = new CourseMetadataStore();
		$reject_update = static fn (): bool => false;

		$store->save_price( $course_id, Price::from_decimal( '10', Currency::GBP ) );
		add_filter( 'update_post_metadata', $reject_update );

		try {
			$store->save_price( $course_id, Price::from_decimal( '20', Currency::EUR ) );
			self::fail( 'A rejected metadata update should raise an exception.' );
		} catch ( RuntimeException $exception ) {
			self::assertSame( 'WordPress could not persist the course price.', $exception->getMessage() );
		} finally {
			remove_filter( 'update_post_metadata', $reject_update );
		}

		$stored_price = $store->price( $course_id );

		self::assertInstanceOf( Price::class, $stored_price );
		self::assertSame( '10', $stored_price->amount() );
		self::assertSame( Currency::GBP, $stored_price->currency() );
	}

	/**
	 * Failure of the second write restores the complete existing pair.
	 */
	public function test_rejected_currency_update_restores_the_existing_price_pair(): void {
		$course_id              = new CourseId( $this->create_post( CoursePostType::POST_TYPE ) );
		$store                  = new CourseMetadataStore();
		$reject_currency_update = static function ( mixed $check, int $object_id, string $meta_key ): mixed {
			unset( $object_id );

			return CourseMeta::PRICE_CURRENCY_KEY === $meta_key ? false : $check;
		};

		$store->save_price( $course_id, Price::from_decimal( '10', Currency::GBP ) );
		add_filter( 'update_post_metadata', $reject_currency_update, 10, 3 );

		try {
			$store->save_price( $course_id, Price::from_decimal( '20', Currency::EUR ) );
			self::fail( 'A rejected currency update should raise an exception.' );
		} catch ( RuntimeException $exception ) {
			self::assertSame( 'WordPress could not persist the course price.', $exception->getMessage() );
		} finally {
			remove_filter( 'update_post_metadata', $reject_currency_update );
		}

		$stored_price = $store->price( $course_id );

		self::assertInstanceOf( Price::class, $stored_price );
		self::assertSame( '10', $stored_price->amount() );
		self::assertSame( Currency::GBP, $stored_price->currency() );
	}

	/**
	 * Legacy amount-only metadata is never assigned an implicit currency.
	 */
	public function test_legacy_only_price_is_ignored_without_currency_inference(): void {
		$course_id = new CourseId( $this->create_post( CoursePostType::POST_TYPE ) );
		$store     = new CourseMetadataStore();

		self::assertNotFalse( add_post_meta( $course_id->value(), CourseMeta::LEGACY_PRICE_KEY, '125.5' ) );

		self::assertNull( $store->price( $course_id ) );
		self::assertFalse( metadata_exists( 'post', $course_id->value(), CourseMeta::PRICE_AMOUNT_KEY ) );
		self::assertFalse( metadata_exists( 'post', $course_id->value(), CourseMeta::PRICE_CURRENCY_KEY ) );
	}

	/**
	 * A partially stored new price fails explicitly.
	 *
	 * @dataProvider incomplete_price_metadata_provider
	 *
	 * @param string $meta_key Price metadata key.
	 * @param string $value    Valid scalar without its pair.
	 */
	public function test_incomplete_price_metadata_is_rejected( string $meta_key, string $value ): void {
		$course_id = new CourseId( $this->create_post( CoursePostType::POST_TYPE ) );
		$store     = new CourseMetadataStore();

		self::assertNotFalse( add_post_meta( $course_id->value(), $meta_key, $value ) );
		$this->expectException( UnexpectedValueException::class );
		$this->expectExceptionMessage( 'Stored course price metadata is incomplete.' );

		$store->price( $course_id );
	}

	/**
	 * Incomplete new price examples.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function incomplete_price_metadata_provider(): array {
		return array(
			'amount only'   => array( CourseMeta::PRICE_AMOUNT_KEY, '125.5' ),
			'currency only' => array( CourseMeta::PRICE_CURRENCY_KEY, 'GBP' ),
		);
	}

	/**
	 * Invalid stored amount syntax is translated without losing its cause.
	 */
	public function test_invalid_stored_price_amount_preserves_validation_context(): void {
		$course_id = new CourseId( $this->create_post( CoursePostType::POST_TYPE ) );
		$store     = new CourseMetadataStore();

		$this->add_unvalidated_metadata( $course_id, CourseMeta::PRICE_AMOUNT_KEY, 'not-a-price' );
		self::assertNotFalse( add_post_meta( $course_id->value(), CourseMeta::PRICE_CURRENCY_KEY, 'GBP' ) );

		try {
			$store->price( $course_id );
			self::fail( 'An invalid stored price amount should raise an exception.' );
		} catch ( UnexpectedValueException $exception ) {
			self::assertInstanceOf( InvalidArgumentException::class, $exception->getPrevious() );
		}
	}

	/**
	 * Invalid stored amount types are rejected directly rather than coerced.
	 */
	public function test_non_string_stored_price_amount_is_rejected_directly(): void {
		$course_id = new CourseId( $this->create_post( CoursePostType::POST_TYPE ) );
		$store     = new CourseMetadataStore();

		$this->add_unvalidated_metadata( $course_id, CourseMeta::PRICE_AMOUNT_KEY, array( '12' ) );
		self::assertNotFalse( add_post_meta( $course_id->value(), CourseMeta::PRICE_CURRENCY_KEY, 'GBP' ) );

		try {
			$store->price( $course_id );
			self::fail( 'A non-string stored price amount should raise an exception.' );
		} catch ( UnexpectedValueException $exception ) {
			self::assertSame( 'Stored course price amount must be a string.', $exception->getMessage() );
			self::assertNull( $exception->getPrevious() );
		}
	}

	/**
	 * Unsupported stored currencies are rejected rather than inferred.
	 */
	public function test_unsupported_stored_price_currency_is_rejected(): void {
		$course_id = new CourseId( $this->create_post( CoursePostType::POST_TYPE ) );
		$store     = new CourseMetadataStore();

		self::assertNotFalse( add_post_meta( $course_id->value(), CourseMeta::PRICE_AMOUNT_KEY, '12' ) );
		$this->add_unvalidated_metadata( $course_id, CourseMeta::PRICE_CURRENCY_KEY, 'CAD' );
		$this->expectException( UnexpectedValueException::class );
		$this->expectExceptionMessage( 'Stored course price currency is unsupported.' );

		$store->price( $course_id );
	}

	/**
	 * Invalid stored currency types are rejected directly rather than coerced.
	 */
	public function test_non_string_stored_price_currency_is_rejected_directly(): void {
		$course_id = new CourseId( $this->create_post( CoursePostType::POST_TYPE ) );
		$store     = new CourseMetadataStore();

		self::assertNotFalse( add_post_meta( $course_id->value(), CourseMeta::PRICE_AMOUNT_KEY, '12' ) );
		$this->add_unvalidated_metadata( $course_id, CourseMeta::PRICE_CURRENCY_KEY, array( 'GBP' ) );
		$this->expectException( UnexpectedValueException::class );
		$this->expectExceptionMessage( 'Stored course price currency must be a string.' );

		$store->price( $course_id );
	}

	/**
	 * Invalid stored provider identifiers fail explicitly.
	 */
	public function test_invalid_stored_provider_identifier_is_rejected(): void {
		$course_id = new CourseId( $this->create_post( CoursePostType::POST_TYPE ) );
		$store     = new CourseMetadataStore();

		$this->add_unvalidated_metadata( $course_id, CourseMeta::PROVIDER_ID_KEY, '0' );
		$this->expectException( UnexpectedValueException::class );

		$store->providers( $course_id );
	}

	/**
	 * Invalid stored instructor identifiers fail explicitly.
	 */
	public function test_invalid_stored_instructor_identifier_is_rejected(): void {
		$course_id = new CourseId( $this->create_post( CoursePostType::POST_TYPE ) );
		$store     = new CourseMetadataStore();

		$this->add_unvalidated_metadata( $course_id, CourseMeta::INSTRUCTOR_ID_KEY, 'not-an-id' );
		$this->expectException( UnexpectedValueException::class );

		$store->instructors( $course_id );
	}

	/**
	 * Invalid stored date syntax is translated without losing its cause.
	 */
	public function test_invalid_stored_start_date_preserves_validation_context(): void {
		$course_id = new CourseId( $this->create_post( CoursePostType::POST_TYPE ) );
		$store     = new CourseMetadataStore();

		$this->add_unvalidated_metadata( $course_id, CourseMeta::START_DATE_KEY, '2026-13' );

		try {
			$store->start_dates( $course_id );
			self::fail( 'An invalid stored start date should raise an exception.' );
		} catch ( UnexpectedValueException $exception ) {
			self::assertInstanceOf( InvalidArgumentException::class, $exception->getPrevious() );
		}
	}

	/**
	 * Reads remain deterministic even when existing metadata rows are unsorted.
	 */
	public function test_start_dates_are_sorted_when_read(): void {
		$course_id = new CourseId( $this->create_post( CoursePostType::POST_TYPE ) );
		$store     = new CourseMetadataStore();

		self::assertNotFalse( add_post_meta( $course_id->value(), CourseMeta::START_DATE_KEY, '2027-01' ) );
		self::assertNotFalse( add_post_meta( $course_id->value(), CourseMeta::START_DATE_KEY, '2026-09' ) );

		self::assertSame(
			array( '2026-09', '2027-01' ),
			array_map(
				static fn ( StartDate $start_date ): string => $start_date->value(),
				$store->start_dates( $course_id )
			)
		);
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
	 * Empty replacement removes all rows and remains safe when repeated.
	 */
	public function test_empty_replacement_removes_existing_values(): void {
		$course_id  = new CourseId( $this->create_post( CoursePostType::POST_TYPE ) );
		$provider   = new ProviderId( $this->create_post( ProviderPostType::POST_TYPE ) );
		$instructor = new InstructorId( $this->create_post( InstructorPostType::POST_TYPE ) );
		$store      = new CourseMetadataStore();

		$store->replace_providers( $course_id, $provider );
		$store->replace_instructors( $course_id, $instructor );
		$store->replace_start_dates( $course_id, new StartDate( '2026-09' ) );

		$store->replace_providers( $course_id );
		$store->replace_instructors( $course_id );
		$store->replace_start_dates( $course_id );
		$store->replace_providers( $course_id );
		$store->replace_instructors( $course_id );
		$store->replace_start_dates( $course_id );

		self::assertSame( array(), $store->providers( $course_id ) );
		self::assertSame( array(), $store->instructors( $course_id ) );
		self::assertSame( array(), $store->start_dates( $course_id ) );
	}

	/**
	 * A rejected delete leaves the old collection intact and adds nothing.
	 */
	public function test_failed_delete_preserves_existing_relationships(): void {
		$course_id       = new CourseId( $this->create_post( CoursePostType::POST_TYPE ) );
		$provider_one    = new ProviderId( $this->create_post( ProviderPostType::POST_TYPE ) );
		$provider_two    = new ProviderId( $this->create_post( ProviderPostType::POST_TYPE ) );
		$store           = new CourseMetadataStore();
		$reject_deletion = static fn (): bool => false;

		$store->replace_providers( $course_id, $provider_one );
		add_filter( 'delete_post_metadata', $reject_deletion );

		try {
			$store->replace_providers( $course_id, $provider_two );
			self::fail( 'A rejected metadata deletion should raise an exception.' );
		} catch ( RuntimeException $exception ) {
			self::assertSame( 'WordPress could not replace course metadata.', $exception->getMessage() );
		} finally {
			remove_filter( 'delete_post_metadata', $reject_deletion );
		}

		self::assertSame(
			array( $provider_one->value() ),
			array_map(
				static fn ( ProviderId $provider_id ): int => $provider_id->value(),
				$store->providers( $course_id )
			)
		);
	}

	/**
	 * Create a post of the requested content type.
	 *
	 * @param string $post_type Registered post type.
	 */
	private function create_post( string $post_type ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_status' => 'publish',
			)
		);

		return $post_id;
	}

	/**
	 * Bypass a registered sanitizer to simulate corrupted persistence data.
	 *
	 * @param CourseId $course_id Course identifier.
	 * @param string   $meta_key  Registered metadata key.
	 * @param mixed    $value     Corrupted stored value.
	 */
	private function add_unvalidated_metadata( CourseId $course_id, string $meta_key, mixed $value ): void {
		$sanitize_callbacks = array(
			CourseMeta::PRICE_AMOUNT_KEY   => array( CourseMeta::class, 'sanitize_price_amount' ),
			CourseMeta::PRICE_CURRENCY_KEY => array( CourseMeta::class, 'sanitize_price_currency' ),
			CourseMeta::PROVIDER_ID_KEY    => array( CourseMeta::class, 'sanitize_provider_id' ),
			CourseMeta::INSTRUCTOR_ID_KEY  => array( CourseMeta::class, 'sanitize_instructor_id' ),
			CourseMeta::START_DATE_KEY     => array( CourseMeta::class, 'sanitize_start_date' ),
		);
		$sanitize_hook      = sprintf(
			'sanitize_post_meta_%s_for_%s',
			$meta_key,
			CoursePostType::POST_TYPE
		);

		self::assertArrayHasKey( $meta_key, $sanitize_callbacks );
		$sanitize_callback = $sanitize_callbacks[ $meta_key ];
		self::assertTrue( remove_filter( $sanitize_hook, $sanitize_callback ) );

		try {
			$meta_id = add_post_meta( $course_id->value(), $meta_key, $value );
		} finally {
			add_filter( $sanitize_hook, $sanitize_callback );
		}

		self::assertNotFalse( $meta_id );
	}
}
