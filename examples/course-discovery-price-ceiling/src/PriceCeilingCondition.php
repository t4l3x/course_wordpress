<?php
/**
 * Backend-independent maximum-price condition.
 *
 * @package CourseDiscoveryPriceCeilingExample
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscoveryExample\PriceCeiling;

use OxfordInternational\CourseDiscovery\Application\Search\CourseQueryConditionInterface;
use OxfordInternational\CourseDiscovery\Domain\Course\Price;

/**
 * Represents currency-specific maximum-price intent without WordPress details.
 */
final readonly class PriceCeilingCondition implements CourseQueryConditionInterface {
	public const string KEY = PriceCeilingCriterion::KEY;

	/**
	 * Create a maximum-price condition.
	 *
	 * @param Price $maximum Maximum accepted Course price in one currency.
	 *
	 * @throws \InvalidArgumentException When WordPress cannot compare the amount exactly.
	 */
	public function __construct(
		private Price $maximum
	) {
		PriceCeilingCriterion::assert_representable( $maximum );
	}

	/**
	 * Return the stable condition key.
	 */
	public function key(): string {
		return self::KEY;
	}

	/**
	 * Return the maximum accepted Course price in one currency.
	 */
	public function maximum(): Price {
		return $this->maximum;
	}
}
