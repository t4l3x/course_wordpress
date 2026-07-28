<?php
/**
 * Course metadata registration.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content;

use InvalidArgumentException;
use OxfordInternational\CourseDiscovery\Domain\Course\Currency;
use OxfordInternational\CourseDiscovery\Domain\Course\Price;
use OxfordInternational\CourseDiscovery\Domain\Course\StartDate;
use OxfordInternational\CourseDiscovery\Domain\Instructor\InstructorId;
use OxfordInternational\CourseDiscovery\Domain\Provider\ProviderId;
use stdClass;
use WP_Error;
use WP_REST_Request;

/**
 * Registers explicit scalar metadata contracts for courses.
 */
final class CourseMeta {
	public const string LEGACY_PRICE_KEY   = '_course_discovery_price';
	public const string PRICE_AMOUNT_KEY   = '_course_discovery_price_amount';
	public const string PRICE_CURRENCY_KEY = '_course_discovery_price_currency';
	public const string PROVIDER_ID_KEY    = '_course_discovery_provider_id';
	public const string INSTRUCTOR_ID_KEY  = '_course_discovery_instructor_id';
	public const string START_DATE_KEY     = '_course_discovery_start_date';

	/**
	 * Register all course metadata contracts.
	 */
	public function register(): void {
		register_post_meta(
			CoursePostType::POST_TYPE,
			self::PRICE_AMOUNT_KEY,
			array(
				'description'       => __(
					'Canonical decimal amount for the Course price.',
					'course-discovery'
				),
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( self::class, 'sanitize_price_amount' ),
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
			self::PRICE_CURRENCY_KEY,
			array(
				'description'       => __(
					'ISO 4217 currency for the Course price.',
					'course-discovery'
				),
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( self::class, 'sanitize_price_currency' ),
				'auth_callback'     => array( self::class, 'can_edit_course' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type' => 'string',
						'enum' => array_map(
							static fn ( Currency $currency ): string => $currency->value,
							Currency::cases()
						),
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

		add_filter(
			'rest_pre_insert_' . CoursePostType::POST_TYPE,
			array( self::class, 'validate_rest_price_pair' ),
			10,
			2
		);
	}

	/**
	 * Require REST price mutations to leave either a complete valid pair or no pair.
	 *
	 * One scalar may be updated when the other already exists and is valid. A new
	 * price needs both scalars, while clearing a price requires both null values.
	 *
	 * @param stdClass|WP_Error $prepared_post Prepared Course post data or an earlier validation error.
	 * @param WP_REST_Request   $request       Current REST request.
	 */
	public static function validate_rest_price_pair(
		stdClass|WP_Error $prepared_post,
		WP_REST_Request $request
	): stdClass|WP_Error {
		if ( $prepared_post instanceof WP_Error ) {
			return $prepared_post;
		}

		$meta = $request->get_param( 'meta' );

		if ( ! is_array( $meta ) ) {
			return $prepared_post;
		}

		$amount_provided   = array_key_exists( self::PRICE_AMOUNT_KEY, $meta );
		$currency_provided = array_key_exists( self::PRICE_CURRENCY_KEY, $meta );

		if ( ! $amount_provided && ! $currency_provided ) {
			return $prepared_post;
		}

		$post_id         = self::rest_post_id( $request );
		$amount_exists   = 0 < $post_id && metadata_exists( 'post', $post_id, self::PRICE_AMOUNT_KEY );
		$currency_exists = 0 < $post_id && metadata_exists( 'post', $post_id, self::PRICE_CURRENCY_KEY );
		$amount          = $amount_provided
			? $meta[ self::PRICE_AMOUNT_KEY ]
			: ( $amount_exists ? get_post_meta( $post_id, self::PRICE_AMOUNT_KEY, true ) : null );
		$currency        = $currency_provided
			? $meta[ self::PRICE_CURRENCY_KEY ]
			: ( $currency_exists ? get_post_meta( $post_id, self::PRICE_CURRENCY_KEY, true ) : null );
		$amount_exists   = $amount_provided ? null !== $amount : $amount_exists;
		$currency_exists = $currency_provided ? null !== $currency : $currency_exists;

		if ( $amount_exists !== $currency_exists ) {
			return self::invalid_rest_price(
				__( 'Course price amount and currency must be written or cleared together.', 'course-discovery' )
			);
		}

		if ( ! $amount_exists ) {
			return $prepared_post;
		}

		try {
			self::sanitize_price_amount( $amount );
			self::sanitize_price_currency( $currency );
		} catch ( InvalidArgumentException ) {
			return self::invalid_rest_price(
				__( 'Course price amount or currency is invalid.', 'course-discovery' )
			);
		}

		return $prepared_post;
	}

	/**
	 * Canonicalize a price amount before WordPress stores it.
	 *
	 * @param mixed $value Untrusted metadata value.
	 *
	 * @throws InvalidArgumentException When the value is not a decimal string.
	 */
	public static function sanitize_price_amount( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			throw new InvalidArgumentException( 'A price amount must be provided as a decimal string.' );
		}

		return Price::canonicalize_amount( $value );
	}

	/**
	 * Validate a Course price currency before WordPress stores it.
	 *
	 * @param mixed $value Untrusted metadata value.
	 *
	 * @throws InvalidArgumentException When the value is not a supported ISO 4217 currency.
	 */
	public static function sanitize_price_currency( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			throw new InvalidArgumentException( 'A price currency must be provided as a string.' );
		}

		$currency = Currency::tryFrom( $value );

		if ( null === $currency ) {
			throw new InvalidArgumentException( 'The price currency is not supported.' );
		}

		return $currency->value;
	}

	/**
	 * Validate a provider identifier before WordPress stores it.
	 *
	 * @param mixed $value Untrusted metadata value.
	 *
	 * @throws InvalidArgumentException When the value is not a positive integer.
	 */
	public static function sanitize_provider_id( mixed $value ): int {
		return ( new ProviderId( self::positive_integer( $value ) ) )->value();
	}

	/**
	 * Validate an instructor identifier before WordPress stores it.
	 *
	 * @param mixed $value Untrusted metadata value.
	 *
	 * @throws InvalidArgumentException When the value is not a positive integer.
	 */
	public static function sanitize_instructor_id( mixed $value ): int {
		return ( new InstructorId( self::positive_integer( $value ) ) )->value();
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

		return ( new StartDate( $value ) )->value();
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

	/**
	 * Resolve an existing Course ID from an update request.
	 *
	 * @param WP_REST_Request $request Current REST request.
	 */
	private static function rest_post_id( WP_REST_Request $request ): int {
		$post_id = $request->get_param( 'id' );

		return is_int( $post_id ) && 0 < $post_id ? $post_id : 0;
	}

	/**
	 * Build the stable REST error returned for an invalid logical price.
	 *
	 * @param string $message Localized validation message.
	 */
	private static function invalid_rest_price( string $message ): WP_Error {
		return new WP_Error(
			'course_discovery_invalid_price',
			$message,
			array( 'status' => 400 )
		);
	}
}
