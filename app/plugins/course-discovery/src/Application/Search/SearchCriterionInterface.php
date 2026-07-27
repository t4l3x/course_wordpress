<?php
/**
 * Custom Course search criterion contract.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Application\Search;

/**
 * Represents one typed criterion contributed by an extension.
 *
 * Implementations should be immutable and contain validated application input,
 * never raw request data.
 */
interface SearchCriterionInterface {
	/**
	 * Return the stable criterion key.
	 */
	public function key(): string;
}
