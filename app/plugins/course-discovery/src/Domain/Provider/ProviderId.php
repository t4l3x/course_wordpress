<?php
/**
 * Provider identifier.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Domain\Provider;

use InvalidArgumentException;

/**
 * Identifies a provider.
 */
final readonly class ProviderId {
	/**
	 * Create a provider identifier.
	 *
	 * @param int $value Provider identifier.
	 *
	 * @throws InvalidArgumentException When the identifier is not positive.
	 */
	public function __construct(
		private int $value
	) {
		if ( 1 > $value ) {
			throw new InvalidArgumentException( 'A provider identifier must be a positive integer.' );
		}
	}

	/**
	 * Return the scalar identifier.
	 */
	public function value(): int {
		return $this->value;
	}
}
