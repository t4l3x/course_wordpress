<?php
/**
 * Course start date.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Domain\Course;

use InvalidArgumentException;

/**
 * Represents a canonical, month-granularity course start date.
 */
final readonly class StartDate {
	/**
	 * Create a start date.
	 *
	 * @param string $value Date in YYYY-MM format.
	 *
	 * @throws InvalidArgumentException When the value is not a valid canonical month.
	 */
	public function __construct(
		private string $value
	) {
		if ( 1 !== preg_match( '/\A[1-9][0-9]{3}-(?:0[1-9]|1[0-2])\z/', $value ) ) {
			throw new InvalidArgumentException( 'A start date must use the canonical YYYY-MM format.' );
		}
	}

	/**
	 * Return the canonical date.
	 */
	public function value(): string {
		return $this->value;
	}

	/**
	 * Compare this date chronologically with another date.
	 *
	 * @param self $other Date to compare.
	 *
	 * @return int A value below zero, zero, or above zero.
	 */
	public function compare_to( self $other ): int {
		return $this->value <=> $other->value;
	}

	/**
	 * Return the canonical date.
	 */
	public function __toString(): string {
		return $this->value;
	}
}
