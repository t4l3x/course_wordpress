<?php
/**
 * Course price.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Domain\Course;

use InvalidArgumentException;

/**
 * Represents a non-negative decimal price without floating-point arithmetic.
 */
final readonly class Price {
	/**
	 * Canonical decimal representation.
	 *
	 * @var string
	 */
	private string $decimal;

	/**
	 * Create a price from its exact decimal representation.
	 *
	 * Currency and display precision are intentionally outside this value object.
	 *
	 * @param string $decimal Decimal price.
	 *
	 * @throws InvalidArgumentException When the value is not a non-negative decimal.
	 */
	public static function from_decimal( string $decimal ): self {
		if ( 1 !== preg_match( '/\A[0-9]+(?:\.[0-9]+)?\z/', $decimal ) ) {
			throw new InvalidArgumentException( 'A price must be a non-negative decimal string.' );
		}

		$parts    = explode( '.', $decimal, 2 );
		$whole    = ltrim( $parts[0], '0' );
		$whole    = '' === $whole ? '0' : $whole;
		$fraction = isset( $parts[1] ) ? rtrim( $parts[1], '0' ) : '';

		return new self( '' === $fraction ? $whole : $whole . '.' . $fraction );
	}

	/**
	 * Return the canonical decimal representation.
	 */
	public function decimal(): string {
		return $this->decimal;
	}

	/**
	 * Store an already validated canonical decimal representation.
	 *
	 * @param string $decimal Canonical decimal price.
	 */
	private function __construct( string $decimal ) {
		$this->decimal = $decimal;
	}
}
