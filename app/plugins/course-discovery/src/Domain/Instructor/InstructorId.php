<?php
/**
 * Instructor identifier.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Domain\Instructor;

use InvalidArgumentException;

/**
 * Identifies an instructor.
 */
final readonly class InstructorId {
	/**
	 * Create an instructor identifier.
	 *
	 * @param int $value Instructor identifier.
	 *
	 * @throws InvalidArgumentException When the identifier is not positive.
	 */
	public function __construct(
		private int $value
	) {
		if ( 1 > $value ) {
			throw new InvalidArgumentException( 'An instructor identifier must be a positive integer.' );
		}
	}

	/**
	 * Return the scalar identifier.
	 */
	public function value(): int {
		return $this->value;
	}
}
