<?php
/**
 * Course search location identifier.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Domain\Location;

use InvalidArgumentException;

/**
 * Identifies a Location independently from its persistence backend.
 */
final readonly class LocationId {
	/**
	 * Create a location identifier.
	 *
	 * @param int $value Location term identifier.
	 *
	 * @throws InvalidArgumentException When the identifier is not positive.
	 */
	public function __construct(
		private int $value
	) {
		if ( 1 > $value ) {
			throw new InvalidArgumentException( 'A location identifier must be a positive integer.' );
		}
	}

	/**
	 * Return the scalar identifier.
	 */
	public function value(): int {
		return $this->value;
	}
}
