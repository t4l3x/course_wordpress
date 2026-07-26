<?php
/**
 * WordPress course metadata persistence.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content;

use InvalidArgumentException;
use OxfordInternational\CourseDiscovery\Domain\Course\CourseId;
use OxfordInternational\CourseDiscovery\Domain\Course\Price;
use OxfordInternational\CourseDiscovery\Domain\Course\StartDate;
use OxfordInternational\CourseDiscovery\Domain\Instructor\InstructorId;
use OxfordInternational\CourseDiscovery\Domain\Provider\ProviderId;
use RuntimeException;
use UnexpectedValueException;

/**
 * Isolates WordPress metadata details from callers.
 */
final class CourseMetadataStore {
	/**
	 * Persist a course's single price.
	 *
	 * @param CourseId $course_id Course identifier.
	 * @param Price    $price     Course price.
	 *
	 * @throws RuntimeException When WordPress cannot persist the price.
	 */
	public function save_price( CourseId $course_id, Price $price ): void {
		$post_id = $course_id->value();
		$decimal = $price->decimal();

		$updated = update_post_meta(
			$post_id,
			CourseMeta::PRICE_KEY,
			$decimal
		);

		if ( false !== $updated ) {
			return;
		}

		$stored = get_post_meta(
			$post_id,
			CourseMeta::PRICE_KEY,
			true
		);

		if ( $stored !== $decimal ) {
			throw new RuntimeException(
				'WordPress could not persist the course price.'
			);
		}
	}

	/**
	 * Remove a course's price.
	 *
	 * @param CourseId $course_id Course identifier.
	 *
	 * @throws RuntimeException When WordPress cannot remove the price.
	 */
	public function remove_price( CourseId $course_id ): void {
		$post_id = $course_id->value();
		$deleted = delete_post_meta( $post_id, CourseMeta::PRICE_KEY );

		if ( false === $deleted && metadata_exists( 'post', $post_id, CourseMeta::PRICE_KEY ) ) {
			throw new RuntimeException( 'WordPress could not remove the course price.' );
		}
	}

	/**
	 * Read a course's price.
	 *
	 * @param CourseId $course_id Course identifier.
	 *
	 * @throws UnexpectedValueException When the stored value is invalid.
	 */
	public function price( CourseId $course_id ): ?Price {
		if ( ! metadata_exists( 'post', $course_id->value(), CourseMeta::PRICE_KEY ) ) {
			return null;
		}

		$value = get_post_meta( $course_id->value(), CourseMeta::PRICE_KEY, true );

		if ( ! is_string( $value ) ) {
			throw new UnexpectedValueException( 'Stored course price must be a string.' );
		}

		try {
			return Price::from_decimal( $value );
		} catch ( InvalidArgumentException $exception ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Preserved validation cause is not output.
			throw new UnexpectedValueException( 'Stored course price is invalid.', 0, $exception );
		}
	}

	/**
	 * Replace a course's provider relationships.
	 *
	 * @param CourseId   $course_id Course identifier.
	 * @param ProviderId ...$providers Related providers.
	 *
	 * @throws RuntimeException When WordPress cannot persist a relationship.
	 */
	public function replace_providers( CourseId $course_id, ProviderId ...$providers ): void {
		$values = array_values(
			array_map(
				static fn ( ProviderId $provider ): int => $provider->value(),
				$providers
			)
		);

		$this->replace_multiple_values( $course_id, CourseMeta::PROVIDER_ID_KEY, $values );
	}

	/**
	 * Read a course's provider relationships.
	 *
	 * @param CourseId $course_id Course identifier.
	 *
	 * @return list<ProviderId>
	 *
	 * @throws UnexpectedValueException When a stored identifier is invalid.
	 */
	public function providers( CourseId $course_id ): array {
		return array_map(
			static fn ( int $provider_id ): ProviderId => new ProviderId( $provider_id ),
			$this->positive_integer_values( $course_id, CourseMeta::PROVIDER_ID_KEY )
		);
	}

	/**
	 * Replace a course's instructor relationships.
	 *
	 * @param CourseId     $course_id Course identifier.
	 * @param InstructorId ...$instructors Related instructors.
	 *
	 * @throws RuntimeException When WordPress cannot persist a relationship.
	 */
	public function replace_instructors( CourseId $course_id, InstructorId ...$instructors ): void {
		$values = array_values(
			array_map(
				static fn ( InstructorId $instructor ): int => $instructor->value(),
				$instructors
			)
		);

		$this->replace_multiple_values( $course_id, CourseMeta::INSTRUCTOR_ID_KEY, $values );
	}

	/**
	 * Read a course's instructor relationships.
	 *
	 * @param CourseId $course_id Course identifier.
	 *
	 * @return list<InstructorId>
	 *
	 * @throws UnexpectedValueException When a stored identifier is invalid.
	 */
	public function instructors( CourseId $course_id ): array {
		return array_map(
			static fn ( int $instructor_id ): InstructorId => new InstructorId( $instructor_id ),
			$this->positive_integer_values( $course_id, CourseMeta::INSTRUCTOR_ID_KEY )
		);
	}

	/**
	 * Replace a course's start dates.
	 *
	 * Dates are stored separately, de-duplicated and ordered chronologically.
	 *
	 * @param CourseId  $course_id Course identifier.
	 * @param StartDate ...$dates  Course start dates.
	 *
	 * @throws RuntimeException When WordPress cannot persist a start date.
	 */
	public function replace_start_dates( CourseId $course_id, StartDate ...$dates ): void {
		$values = array_map(
			static fn ( StartDate $date ): string => $date->value(),
			$dates
		);

		sort( $values, SORT_STRING );
		$this->replace_multiple_values( $course_id, CourseMeta::START_DATE_KEY, $values );
	}

	/**
	 * Read a course's chronologically ordered start dates.
	 *
	 * @param CourseId $course_id Course identifier.
	 *
	 * @return list<StartDate>
	 *
	 * @throws UnexpectedValueException When a stored date is invalid.
	 */
	public function start_dates( CourseId $course_id ): array {
		$values = get_post_meta( $course_id->value(), CourseMeta::START_DATE_KEY, false );
		$dates  = array();

		foreach ( $values as $value ) {
			if ( ! is_string( $value ) ) {
				throw new UnexpectedValueException( 'Stored course start date must be a string.' );
			}

			try {
				$dates[] = new StartDate( $value );
			} catch ( InvalidArgumentException $exception ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Preserved validation cause is not output.
				throw new UnexpectedValueException( 'Stored course start date is invalid.', 0, $exception );
			}
		}

		usort(
			$dates,
			static fn ( StartDate $left, StartDate $right ): int => $left->compare_to( $right )
		);

		return $dates;
	}

	/**
	 * Replace all rows for one multi-value metadata contract.
	 *
	 * WordPress performs this as a non-transactional delete/add sequence. Values
	 * are normalized before deletion, but concurrent writes can still race and
	 * an add failure can leave a partial replacement.
	 *
	 * @param CourseId         $course_id Course identifier.
	 * @param string           $meta_key  Registered metadata key.
	 * @param list<int|string> $values    Scalar values.
	 *
	 * @throws RuntimeException When WordPress cannot persist a metadata row.
	 */
	private function replace_multiple_values( CourseId $course_id, string $meta_key, array $values ): void {
		$course_id_value = $course_id->value();
		$unique_values   = array_values( array_unique( $values, SORT_REGULAR ) );
		$deleted         = delete_post_meta( $course_id_value, $meta_key );

		if ( false === $deleted && metadata_exists( 'post', $course_id_value, $meta_key ) ) {
			throw new RuntimeException( 'WordPress could not replace course metadata.' );
		}

		foreach ( $unique_values as $value ) {
			if ( false === add_post_meta( $course_id_value, $meta_key, $value, false ) ) {
				throw new RuntimeException( 'WordPress could not persist course metadata.' );
			}
		}
	}

	/**
	 * Read and validate positive integer metadata rows.
	 *
	 * @param CourseId $course_id Course identifier.
	 * @param string   $meta_key  Registered metadata key.
	 *
	 * @return list<int>
	 *
	 * @throws UnexpectedValueException When a stored identifier is invalid.
	 */
	private function positive_integer_values( CourseId $course_id, string $meta_key ): array {
		$values      = get_post_meta( $course_id->value(), $meta_key, false );
		$identifiers = array();

		foreach ( $values as $value ) {
			if ( ! is_string( $value ) ) {
				throw new UnexpectedValueException( 'Stored course relationship identifier is invalid.' );
			}

			$identifier = filter_var(
				$value,
				FILTER_VALIDATE_INT,
				array(
					'options' => array(
						'min_range' => 1,
					),
				)
			);
			if ( false === $identifier ) {
				throw new UnexpectedValueException(
					'Stored course relationship identifier is outside the supported range.'
				);
			}

			$identifiers[] = $identifier;
		}

		return $identifiers;
	}
}
