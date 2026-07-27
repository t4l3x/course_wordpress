<?php
/**
 * Test-only Difficulty search criterion.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Tests\Unit\Search\Support;

use OxfordInternational\CourseDiscovery\Application\Search\SearchCriterionInterface;

/**
 * Represents typed third-party search input in extensibility tests.
 */
final readonly class DifficultyCriterion implements SearchCriterionInterface {
	public const string KEY = 'difficulty';

	/**
	 * Create a Difficulty criterion.
	 *
	 * @param string $level Selected Difficulty level.
	 */
	public function __construct(
		private string $level
	) {
	}

	/**
	 * Return the stable criterion key.
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
