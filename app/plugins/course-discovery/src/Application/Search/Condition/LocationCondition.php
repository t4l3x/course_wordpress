<?php
/**
 * Course Location-selection condition.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Application\Search\Condition;

use InvalidArgumentException;
use OxfordInternational\CourseDiscovery\Application\Search\CourseQueryConditionInterface;
use OxfordInternational\CourseDiscovery\Domain\Location\LocationId;

/**
 * Matches a Course whose related Providers include any selected Location.
 */
final readonly class LocationCondition implements CourseQueryConditionInterface {
	public const string KEY = 'location';

	/**
	 * Create a Location-selection condition.
	 *
	 * @param array $locations Selected Location terms.
	 *
	 * @throws InvalidArgumentException When no Location is selected.
	 *
	 * @phpstan-param list<LocationId> $locations
	 */
	public function __construct(
		private array $locations
	) {
		if ( array() === $locations ) {
			throw new InvalidArgumentException( 'A Location condition requires at least one Location.' );
		}
	}

	/**
	 * Return the stable condition key.
	 */
	public function key(): string {
		return self::KEY;
	}

	/**
	 * Return selected Locations.
	 *
	 * @return list<LocationId>
	 */
	public function locations(): array {
		return $this->locations;
	}
}
