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
	private string $value;

	/**
	 * Create a price from a decimal string.
	 *
	 * Currency and display precision are intentionally outside this value object.
	 *
	 * @param string $value Decimal price.
	 *
	 * @throws InvalidArgumentException When the value is not a non-negative decimal.
	 */
	public function __construct( string $value ) {
		if ( 1 !== preg_match( '/\A[0-9]+(?:\.[0-9]+)?\z/', $value ) ) {
			throw new InvalidArgumentException( 'A price must be a non-negative decimal string.' );
		}

		$parts       = explode( '.', $value, 2 );
		$whole       = ltrim( $parts[0], '0' );
		$whole       = '' === $whole ? '0' : $whole;
		$fraction    = isset( $parts[1] ) ? rtrim( $parts[1], '0' ) : '';
		$this->value = '' === $fraction ? $whole : $whole . '.' . $fraction;
	}

	/**
	 * Return the canonical decimal representation.
	 */
	public function value(): string {
		return $this->value;
	}

	/**
	 * Return the canonical decimal representation.
	 */
	public function __toString(): string {
		return $this->value;
	}
}
