<?php
/**
 * Course identifier.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Domain\Course;

use InvalidArgumentException;

/**
 * Identifies a course.
 */
final readonly class CourseId {
	/**
	 * Create a course identifier.
	 *
	 * @param int $value Course identifier.
	 *
	 * @throws InvalidArgumentException When the identifier is not positive.
	 */
	public function __construct(
		private int $value
	) {
		if ( 1 > $value ) {
			throw new InvalidArgumentException( 'A course identifier must be a positive integer.' );
		}
	}

	/**
	 * Return the scalar identifier.
	 */
	public function value(): int {
		return $this->value;
	}
}
