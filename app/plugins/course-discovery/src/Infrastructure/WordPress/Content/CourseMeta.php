<?php
/**
 * Course metadata registration.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content;

use InvalidArgumentException;
use OxfordInternational\CourseDiscovery\Domain\Course\Price;
use OxfordInternational\CourseDiscovery\Domain\Course\StartDate;
use OxfordInternational\CourseDiscovery\Domain\Instructor\InstructorId;
use OxfordInternational\CourseDiscovery\Domain\Provider\ProviderId;

/**
 * Registers explicit scalar metadata contracts for courses.
 */
final class CourseMeta {
	public const string PRICE_KEY         = '_course_discovery_price';
	public const string PROVIDER_ID_KEY   = '_course_discovery_provider_id';
	public const string INSTRUCTOR_ID_KEY = '_course_discovery_instructor_id';
	public const string START_DATE_KEY    = '_course_discovery_start_date';

	/**
	 * Register all course metadata contracts.
	 */
	public function register(): void {
		register_post_meta(
			CoursePostType::POST_TYPE,
			self::PRICE_KEY,
			array(
				'description'       => __(
					'Canonical decimal course price; no currency is implied.',
					'course-discovery'
				),
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( self::class, 'sanitize_price' ),
				'auth_callback'     => array( self::class, 'can_edit_course' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => 'string',
						'pattern' => '^(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$',
					),
				),
			)
		);

		register_post_meta(
			CoursePostType::POST_TYPE,
			self::PROVIDER_ID_KEY,
			array(
				'description'       => __(
					'One related provider ID. Each relationship uses a separate metadata row.',
					'course-discovery'
				),
				'type'              => 'integer',
				'single'            => false,
				'sanitize_callback' => array( self::class, 'sanitize_provider_id' ),
				'auth_callback'     => array( self::class, 'can_edit_course' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
			)
		);

		register_post_meta(
			CoursePostType::POST_TYPE,
			self::INSTRUCTOR_ID_KEY,
			array(
				'description'       => __(
					'One related instructor ID. Each relationship uses a separate metadata row.',
					'course-discovery'
				),
				'type'              => 'integer',
				'single'            => false,
				'sanitize_callback' => array( self::class, 'sanitize_instructor_id' ),
				'auth_callback'     => array( self::class, 'can_edit_course' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
			)
		);

		register_post_meta(
			CoursePostType::POST_TYPE,
			self::START_DATE_KEY,
			array(
				'description'       => __(
					'One YYYY-MM course start date. Each date uses a separate metadata row.',
					'course-discovery'
				),
				'type'              => 'string',
				'single'            => false,
				'sanitize_callback' => array( self::class, 'sanitize_start_date' ),
				'auth_callback'     => array( self::class, 'can_edit_course' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => 'string',
						'pattern' => '^[1-9][0-9]{3}-(?:0[1-9]|1[0-2])$',
					),
				),
			)
		);
	}

	/**
	 * Canonicalize a price before WordPress stores it.
	 *
	 * @param mixed $value Untrusted metadata value.
	 *
	 * @throws InvalidArgumentException When the value is not a decimal string.
	 */
	public static function sanitize_price( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			throw new InvalidArgumentException( 'A price must be provided as a decimal string.' );
		}

		return Price::from_decimal( $value )->decimal();
	}

	/**
	 * Validate a provider identifier before WordPress stores it.
	 *
	 * @param mixed $value Untrusted metadata value.
	 *
	 * @throws InvalidArgumentException When the value is not a positive integer.
	 */
	public static function sanitize_provider_id( mixed $value ): int {
		return new ProviderId( self::positive_integer( $value ) )->value();
	}

	/**
	 * Validate an instructor identifier before WordPress stores it.
	 *
	 * @param mixed $value Untrusted metadata value.
	 *
	 * @throws InvalidArgumentException When the value is not a positive integer.
	 */
	public static function sanitize_instructor_id( mixed $value ): int {
		return new InstructorId( self::positive_integer( $value ) )->value();
	}

	/**
	 * Validate a start date before WordPress stores it.
	 *
	 * @param mixed $value Untrusted metadata value.
	 *
	 * @throws InvalidArgumentException When the value is not a canonical start month.
	 */
	public static function sanitize_start_date( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			throw new InvalidArgumentException( 'A start date must be provided as a string.' );
		}

		return new StartDate( $value )->value();
	}

	/**
	 * Determine whether the current user may edit a course's metadata.
	 *
	 * @param bool   $allowed  Core's default authorization decision.
	 * @param string $meta_key Metadata key.
	 * @param int    $post_id  Course post identifier.
	 * @param int    $user_id  Evaluated user identifier.
	 */
	public static function can_edit_course( bool $allowed, string $meta_key, int $post_id, int $user_id ): bool {
		unset( $allowed, $meta_key );

		return user_can( $user_id, 'edit_post', $post_id );
	}

	/**
	 * Parse a positive integer from WordPress metadata input.
	 *
	 * @param mixed $value Untrusted value.
	 *
	 * @throws InvalidArgumentException When the value is not a supported positive integer.
	 */
	private static function positive_integer( mixed $value ): int {
		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			throw new InvalidArgumentException( 'A relationship identifier must be a positive integer.' );
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
			throw new InvalidArgumentException( 'A relationship identifier is outside the supported integer range.' );
		}

		return $identifier;
	}
}
