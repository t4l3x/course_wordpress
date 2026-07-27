<?php
/**
 * Test-only Difficulty query condition.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Tests\Unit\Search\Support;

use OxfordInternational\CourseDiscovery\Application\Search\CourseQueryConditionInterface;

/**
 * Represents third-party Difficulty intent in extensibility tests.
 */
final readonly class DifficultyCondition implements CourseQueryConditionInterface {
	public const string KEY = DifficultyCriterion::KEY;

	/**
	 * Create a Difficulty condition.
	 *
	 * @param string $level Selected Difficulty level.
	 */
	public function __construct(
		private string $level
	) {
	}

	/**
	 * Return the stable condition key.
	 */
	public function key(): string {
		return self::KEY;
	}

	/**
	 * Return the selected Difficulty level.
	 */
	public function level(): string {
		return $this->level;
	}
}
