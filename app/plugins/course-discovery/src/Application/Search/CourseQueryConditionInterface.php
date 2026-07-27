<?php
/**
 * Course query condition contract.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Application\Search;

/**
 * Represents one backend-independent top-level Course query condition.
 */
interface CourseQueryConditionInterface {
	/**
	 * Return the stable condition key.
	 */
	public function key(): string;
}
