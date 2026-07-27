<?php
/**
 * Course text-search condition.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Application\Search\Condition;

use OxfordInternational\CourseDiscovery\Application\Search\CourseQueryConditionInterface;
use OxfordInternational\CourseDiscovery\Application\Search\SearchTerm;

/**
 * Matches a term against Course name, short description, or long description.
 */
final readonly class TextCondition implements CourseQueryConditionInterface {
	public const string KEY = 'text';

	/**
	 * Create a Course text condition.
	 *
	 * @param SearchTerm $search_term Search term.
	 */
	public function __construct(
		private SearchTerm $search_term
	) {
	}

	/**
	 * Return the stable condition key.
	 */
	public function key(): string {
		return self::KEY;
	}

	/**
	 * Return the requested search term.
	 */
	public function search_term(): SearchTerm {
		return $this->search_term;
	}
}
