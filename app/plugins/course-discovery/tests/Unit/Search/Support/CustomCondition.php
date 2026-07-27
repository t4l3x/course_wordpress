<?php
/**
 * Test-only custom Course query condition.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Tests\Unit\Search\Support;

use OxfordInternational\CourseDiscovery\Application\Search\CourseQueryConditionInterface;

/**
 * Represents a third-party condition in filter extensibility tests.
 */
final readonly class CustomCondition implements CourseQueryConditionInterface {
	/**
	 * Create a custom condition.
	 *
	 * @param string $key Stable test condition key.
	 */
	public function __construct(
		private string $key = 'custom'
	) {
	}

	/**
	 * Return the stable condition key.
	 */
	public function key(): string {
		return $this->key;
	}
}
