<?php
/**
 * Course Provider-selection condition.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Application\Search\Condition;

use InvalidArgumentException;
use OxfordInternational\CourseDiscovery\Application\Search\CourseQueryConditionInterface;
use OxfordInternational\CourseDiscovery\Domain\Provider\ProviderId;

/**
 * Matches a Course related to any selected Provider.
 */
final readonly class ProviderCondition implements CourseQueryConditionInterface {
	public const string KEY = 'provider';

	/**
	 * Create a Provider-selection condition.
	 *
	 * @param array $providers Selected Providers.
	 *
	 * @throws InvalidArgumentException When no Provider is selected.
	 *
	 * @phpstan-param list<ProviderId> $providers
	 */
	public function __construct(
		private array $providers
	) {
		if ( array() === $providers ) {
			throw new InvalidArgumentException( 'A Provider condition requires at least one Provider.' );
		}
	}

	/**
	 * Return the stable condition key.
	 */
	public function key(): string {
		return self::KEY;
	}

	/**
	 * Return selected Providers.
	 *
	 * @return list<ProviderId>
	 */
	public function providers(): array {
		return $this->providers;
	}
}
