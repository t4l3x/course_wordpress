<?php
/**
 * Course search category identifier.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Domain\Category;

use InvalidArgumentException;

/**
 * Identifies a Course Category independently of its persistence backend.
 */
final readonly class CategoryId {
	/**
	 * Create a category identifier.
	 *
	 * @param int $value Category term identifier.
	 *
	 * @throws InvalidArgumentException When the identifier is not positive.
	 */
	public function __construct(
		private int $value
	) {
		if ( 1 > $value ) {
			throw new InvalidArgumentException( 'A category identifier must be a positive integer.' );
		}
	}

	/**
	 * Return the scalar identifier.
	 */
	public function value(): int {
		return $this->value;
	}
}
